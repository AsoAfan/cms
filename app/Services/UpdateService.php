<?php

namespace App\Services;

use App\Exceptions\UpdateFailedException;
use App\Support\Release;
use App\Support\UpdateCheck;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Updates the installation in place.
 *
 * The client's copy is a shallow git checkout of a branch CI publishes with
 * `vendor/` and `public/build` already built into it, so an update is a fetch,
 * a reset and a migration — no Composer and no Node on a machine whose owner
 * has never heard of either.
 *
 * Two rules hold everything else together:
 *
 * - **Nothing is half-applied.** The database is backed up before the files
 *   move, and any failure after that point resets the checkout and restores the
 *   backup. A client left on a half-updated copy cannot diagnose it and cannot
 *   undo it.
 * - **The credential never leaves this class.** On a private repository the
 *   remote URL carries a token; it is passed to git per command rather than
 *   written into `.git/config`, and every line of git output is redacted before
 *   it can reach a flash message, a log or an exception.
 *
 * Artisan steps run in-process rather than as `php artisan ...`: there is no
 * portable way to find the PHP binary from inside a request, and the migrator
 * reads the migrations directory when it runs, so it sees the files the reset
 * just wrote.
 */
final class UpdateService
{
    /**
     * Held for the length of an update so a double-click cannot start a second
     * one on top of the first. A file rather than the cache, because
     * `optimize:clear` is part of the update and would drop a cache lock
     * halfway through.
     */
    private const string LOCK = 'update.lock';

    public function isConfigured(): bool
    {
        return $this->remote() !== '';
    }

    /**
     * The release this copy is running, or null if it is not a git checkout.
     */
    public function installed(): ?Release
    {
        try {
            $result = $this->git(['log', '-1', '--format='.self::FORMAT], $this->checkTimeout());
        } catch (Throwable) {
            // No git on the machine at all. The screen says so rather than
            // failing to load, because "you cannot update" is the very thing
            // the user came here to find out.
            return null;
        }

        return $result->successful()
            ? Release::fromGitLine($result->output())
            : null;
    }

    /**
     * Asks the release branch what the newest version is.
     *
     * @throws UpdateFailedException
     */
    public function check(): UpdateCheck
    {
        $this->ensureUsable();

        $installed = $this->installed();
        $latest = $this->fetch();

        return new UpdateCheck($installed, $latest, $this->changesBetween($installed, $latest));
    }

    /**
     * Installs the newest release, or does nothing if there is not one.
     *
     * Re-checks rather than trusting what the screen was showing: the button
     * may have been sitting on an open tab since yesterday.
     *
     * @return UpdateCheck What was installed before, and what is installed now.
     *
     * @throws UpdateFailedException
     */
    public function apply(): UpdateCheck
    {
        $lock = $this->acquireLock();

        try {
            $this->ensureUsable();

            $installed = $this->installed();
            $latest = $this->fetch();
            $changes = $this->changesBetween($installed, $latest);

            if ($latest->is($installed)) {
                $this->remember([
                    'available' => false,
                    'latest' => $latest->toArray(),
                    'changes' => [],
                    'failed' => false,
                ]);

                return new UpdateCheck($installed, $latest, $changes);
            }

            $backup = $this->backUpDatabase();

            try {
                $this->mustRun(['reset', '--hard', $latest->sha], $this->applyTimeout());

                // Caches first: the migrations about to run should read the
                // configuration and events that came with the new code, not the
                // compiled copies of the old.
                Artisan::call('optimize:clear');
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $failure) {
                $this->rollBack($installed, $backup, $failure);
            }

            // The badge must go out the moment the update lands. Nothing needs
            // asking: what was just installed is what was on offer.
            $this->remember([
                'available' => false,
                'latest' => $latest->toArray(),
                'changes' => [],
                'failed' => false,
            ]);

            return new UpdateCheck($installed, $latest, $changes);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Noticing on its own
    |--------------------------------------------------------------------------
    |
    | Every screen carries the answer to "is anything waiting?" as a shared
    | prop, so the sidebar can say so without anybody going looking. That answer
    | is always the remembered one: this half of the class must never touch the
    | network, or every page load in the shop would wait on the internet.
    |
    | Refreshing it is the browser's job — see `UpdateNotice` — which is what
    | keeps a slow or missing connection off the critical path of a page.
    |
    */

    private const string ANNOUNCEMENT = 'updates.announcement';

    /**
     * What is already known about waiting updates. Reads a cache entry and
     * nothing else.
     *
     * @return array{can_check: bool, checked_at: string|null, stale: bool, available: bool, latest: array<string, string>|null, changes: list<string>}
     */
    public function announcement(): array
    {
        $remembered = Cache::get(self::ANNOUNCEMENT);
        $remembered = is_array($remembered) ? $remembered : [];

        $checkedAt = $remembered['checked_at'] ?? null;

        return [
            'can_check' => $this->isConfigured(),
            'checked_at' => $checkedAt,
            'stale' => $this->isConfigured() && $this->isStale(
                is_string($checkedAt) ? $checkedAt : null,
                (bool) ($remembered['failed'] ?? false),
            ),
            'available' => (bool) ($remembered['available'] ?? false),
            'latest' => $remembered['latest'] ?? null,
            'changes' => $remembered['changes'] ?? [],
        ];
    }

    /**
     * Asks the release branch what is there, and remembers the answer.
     *
     * Does nothing while the remembered answer is still fresh, so the browser
     * asking on every navigation costs one cache read. `$force` is the button
     * on the settings screen, which must always mean now.
     *
     * @return array{can_check: bool, checked_at: string|null, stale: bool, available: bool, latest: array<string, string>|null, changes: list<string>}
     *
     * @throws UpdateFailedException
     */
    public function refresh(bool $force = false): array
    {
        if (! $force && ! $this->announcement()['stale']) {
            return $this->announcement();
        }

        try {
            $check = $this->check();
        } catch (UpdateFailedException $failure) {
            // Remember that the attempt happened, or an offline machine would
            // retry on every single navigation.
            $this->remember([
                ...(is_array(Cache::get(self::ANNOUNCEMENT)) ? Cache::get(self::ANNOUNCEMENT) : []),
                'failed' => true,
            ]);

            throw $failure;
        }

        $this->remember([
            'available' => ! $check->isUpToDate(),
            'latest' => $check->latest->toArray(),
            'changes' => $check->changes,
            'failed' => false,
        ]);

        return $this->announcement();
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function remember(array $answer): void
    {
        // No TTL: an expired entry would take "last checked yesterday" away
        // with it, and staleness is a question about `checked_at`, not about
        // whether the cache still holds anything.
        Cache::forever(self::ANNOUNCEMENT, [
            ...$answer,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function isStale(?string $checkedAt, bool $failed): bool
    {
        if ($checkedAt === null) {
            return true;
        }

        $wait = $failed
            ? (int) config('updates.retry_after')
            : (int) config('updates.check_every') * 60;

        return Carbon::parse($checkedAt)->addMinutes($wait)->isPast();
    }

    /*
    |--------------------------------------------------------------------------
    | Git
    |--------------------------------------------------------------------------
    */

    /** Sha, ISO commit date and subject, split on the ASCII unit separator. */
    private const string FORMAT = '%H%x1f%cI%x1f%s';

    /**
     * Brings the release branch down and reads its tip.
     *
     * Shallow: the client needs the newest release, not the project's history.
     * The depth is what makes a list of "what's new" possible at all, so it is
     * the same number as the history the screen will show.
     *
     * @throws UpdateFailedException
     */
    private function fetch(): Release
    {
        $result = $this->git([
            'fetch',
            '--depth='.config('updates.history'),
            $this->remote(),
            $this->branch(),
        ], $this->checkTimeout());

        if ($result->failed()) {
            throw UpdateFailedException::checkFailed($this->reason($result));
        }

        $tip = $this->git(['log', '-1', '--format='.self::FORMAT, 'FETCH_HEAD'], $this->checkTimeout());

        $release = $tip->successful() ? Release::fromGitLine($tip->output()) : null;

        if ($release === null) {
            throw UpdateFailedException::checkFailed($this->reason($tip));
        }

        return $release;
    }

    /**
     * The release subjects between what is installed and what is available.
     *
     * Best-effort by design: a shallow checkout can lack the common ancestor
     * the range needs, and an empty list costs the user a paragraph of detail
     * rather than the update itself.
     *
     * @return list<string>
     */
    private function changesBetween(?Release $installed, Release $latest): array
    {
        if ($installed === null || $installed->is($latest)) {
            return [];
        }

        $result = $this->git([
            'log',
            '--format=%s',
            '--max-count='.config('updates.history'),
            "{$installed->sha}..{$latest->sha}",
        ], $this->checkTimeout());

        if ($result->failed()) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode("\n", $result->output())),
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments, int $timeout): ProcessResult
    {
        return Process::path($this->path())
            ->env([
                // Git must never stop and ask for a password. On a private
                // repository with an expired token it would otherwise sit at a
                // prompt nobody can see until the timeout expires, which reads
                // as "the update hangs" rather than "the token needs renewing".
                'GIT_TERMINAL_PROMPT' => '0',
                'GIT_ASKPASS' => 'echo',
                'GCM_INTERACTIVE' => 'never',
                'GIT_SSH_COMMAND' => 'ssh -o BatchMode=yes',
            ])
            ->timeout($timeout)
            // An array command is passed to the OS as argv, so the remote URL
            // never reaches a shell that could log it or mangle a token
            // containing shell metacharacters.
            ->run([$this->binary(), ...$arguments]);
    }

    /**
     * @param  list<string>  $arguments
     *
     * @throws UpdateFailedException
     */
    private function mustRun(array $arguments, int $timeout): ProcessResult
    {
        $result = $this->git($arguments, $timeout);

        if ($result->failed()) {
            throw UpdateFailedException::downloadFailed($this->reason($result));
        }

        return $result;
    }

    /**
     * @throws UpdateFailedException
     */
    private function ensureUsable(): void
    {
        if (! $this->isConfigured()) {
            throw UpdateFailedException::notConfigured();
        }

        try {
            $version = $this->git(['--version'], $this->checkTimeout());
        } catch (Throwable) {
            throw UpdateFailedException::gitMissing();
        }

        if ($version->failed()) {
            throw UpdateFailedException::gitMissing();
        }

        if ($this->git(['rev-parse', '--git-dir'], $this->checkTimeout())->failed()) {
            throw UpdateFailedException::notAGitCheckout($this->path());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Undo
    |--------------------------------------------------------------------------
    */

    /**
     * Puts the files and the books back exactly as they were, then reports the
     * original failure rather than the rollback.
     *
     * @throws UpdateFailedException
     */
    private function rollBack(?Release $installed, ?string $backup, Throwable $cause): never
    {
        try {
            if ($installed !== null) {
                $this->mustRun(['reset', '--hard', $installed->sha], $this->applyTimeout());
            }

            if ($backup !== null) {
                $this->restoreDatabase($backup);
            }

            Artisan::call('optimize:clear');
        } catch (Throwable $rollbackFailure) {
            throw UpdateFailedException::rollbackFailed(
                $this->redact($cause->getMessage()."\n".$rollbackFailure->getMessage())
            );
        }

        throw UpdateFailedException::rolledBack($this->redact($cause->getMessage()));
    }

    /*
    |--------------------------------------------------------------------------
    | The books
    |--------------------------------------------------------------------------
    */

    /**
     * Copies the database aside before anything is touched.
     *
     * `VACUUM INTO` rather than a file copy: with a write-ahead log in play the
     * `.sqlite` file alone can be missing committed rows, and a backup that
     * silently loses today's invoices is worse than none. Only SQLite installs
     * get one — a server database is somebody else's backup policy.
     */
    private function backUpDatabase(): ?string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return null;
        }

        // `:memory:` is a database name that is not a file, which is what the
        // test suite runs on and what there is nothing to copy aside.
        $database = $connection->getDatabaseName();

        if (! is_file($database)) {
            return null;
        }

        $directory = (string) config('updates.backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.now()->format('Y-m-d_His').'-database.sqlite';

        try {
            $connection->statement('VACUUM INTO ?', [$path]);
        } catch (Throwable) {
            File::copy($database, $path);
        }

        $this->pruneBackups($directory);

        return $path;
    }

    private function restoreDatabase(string $backup): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        // Drop the open handle first; on Windows the file cannot be replaced
        // while it is held, and on every platform a live connection would go on
        // reading pages that are no longer there.
        DB::purge($connection->getName());

        File::copy($backup, $database);
    }

    /**
     * Keeps the newest few and deletes the rest. A client's disk is not a
     * backup archive, and a folder of a hundred copies of the books is its own
     * kind of confusing.
     */
    private function pruneBackups(string $directory): void
    {
        $backups = collect(File::files($directory))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '-database.sqlite'))
            ->sortByDesc(fn ($file): string => $file->getFilename())
            ->slice((int) config('updates.keep_backups'));

        foreach ($backups as $backup) {
            File::delete($backup->getPathname());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Plumbing
    |--------------------------------------------------------------------------
    */

    /**
     * @return resource
     *
     * @throws UpdateFailedException
     */
    private function acquireLock()
    {
        $directory = storage_path('app/private');
        File::ensureDirectoryExists($directory);

        $handle = fopen($directory.'/'.self::LOCK, 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw UpdateFailedException::alreadyRunning();
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Git's own words, trimmed to something a toast can hold.
     */
    private function reason(ProcessResult $result): string
    {
        $output = trim($result->errorOutput()) ?: trim($result->output());

        return $this->redact(mb_strimwidth($output, 0, 400, '…'));
    }

    /**
     * Strips the credential out of anything on its way to a screen or a log.
     *
     * Git echoes the URL it was handed in most of its failure messages, and on
     * a private install that URL is the repository token.
     */
    private function redact(string $text): string
    {
        $remote = $this->remote();

        if ($remote !== '') {
            $text = str_replace($remote, $this->safeRemote(), $text);
        }

        return (string) preg_replace('#([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1•••@', $text);
    }

    /** The remote with its credential removed, safe to show a user. */
    public function safeRemote(): string
    {
        return (string) preg_replace('#([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1•••@', $this->remote());
    }

    private function remote(): string
    {
        return trim((string) config('updates.remote'));
    }

    private function branch(): string
    {
        return (string) config('updates.branch');
    }

    private function path(): string
    {
        return (string) config('updates.path');
    }

    private function binary(): string
    {
        return (string) config('updates.git');
    }

    private function checkTimeout(): int
    {
        return (int) config('updates.check_timeout');
    }

    private function applyTimeout(): int
    {
        return (int) config('updates.apply_timeout');
    }
}
