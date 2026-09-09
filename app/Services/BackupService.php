<?php

namespace App\Services;

use App\Exceptions\BackupFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * Copies of the books.
 *
 * One mechanism serves both callers: the button on Settings → Backup, and
 * `UpdateService`, which takes one before it moves any files so a failed update
 * can be put back. They write to the same folder and prune together, so what
 * the screen lists is every copy that exists.
 *
 * A snapshot is taken with `VACUUM INTO` rather than a file copy: with a
 * write-ahead log in play the `.sqlite` file alone can be missing rows that
 * were committed minutes ago, and a backup that silently loses today's invoices
 * is worse than none. It also runs against the live connection without locking
 * the shop out mid-sale.
 *
 * Only SQLite installs are backed up here. A database server has its own backup
 * policy, run by whoever administers it, and a copy pulled out through the
 * application would be a worse one.
 */
final class BackupService
{
    /**
     * The suffix every snapshot carries, whichever caller wrote it. Also what
     * identifies one in the folder, so nothing else that lands there is offered
     * for download or deleted to make room.
     */
    private const string SUFFIX = '-database.sqlite';

    /**
     * Whether this install is one that can be backed up from in here at all.
     *
     * False on a database server, and false on the `:memory:` database the test
     * suite runs against — a database that is not a file has nothing to copy.
     */
    public function supported(): bool
    {
        $connection = DB::connection();

        return $connection->getDriverName() === 'sqlite'
            && is_file($connection->getDatabaseName());
    }

    /**
     * Writes a copy of the database aside and returns where it went.
     *
     * @throws BackupFailedException
     */
    public function take(): string
    {
        if (! $this->supported()) {
            throw BackupFailedException::notAFileDatabase();
        }

        $connection = DB::connection();
        $directory = $this->directory();

        try {
            File::ensureDirectoryExists($directory);
        } catch (Throwable $failure) {
            throw BackupFailedException::couldNotWrite($failure->getMessage());
        }

        $path = $directory.'/'.now()->format('Y-m-d_His').self::SUFFIX;

        try {
            $connection->statement('VACUUM INTO ?', [$path]);
        } catch (Throwable $vacuumFailed) {
            // Older SQLite builds have no `VACUUM INTO`, and it refuses to
            // overwrite a file that already exists. A plain copy is the weaker
            // backup, but a weaker one beats none.
            if (! File::copy($connection->getDatabaseName(), $path)) {
                throw BackupFailedException::couldNotWrite($vacuumFailed->getMessage());
            }
        }

        $this->prune();

        return $path;
    }

    /**
     * Puts a copy back over the live database.
     *
     * Used by `UpdateService` when an update fails after the files have already
     * moved. There is deliberately no button for this: restoring throws away
     * everything entered since the copy was made, and that is a decision to
     * take with support on the phone rather than from a dropdown.
     */
    public function restore(string $path): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        // Drop the open handle first; on Windows the file cannot be replaced
        // while it is held, and on every platform a live connection would go on
        // reading pages that are no longer there.
        DB::purge($connection->getName());

        File::copy($path, $database);
    }

    /**
     * Every copy on this computer, newest first.
     *
     * @return list<array{name: string, bytes: int, taken_at: string}>
     */
    public function all(): array
    {
        return array_values($this->files()
            ->map(fn (SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'bytes' => (int) $file->getSize(),
                'taken_at' => now()->setTimestamp((int) $file->getMTime())->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Resolves a backup the user asked for by name to a path on disk.
     *
     * `basename` first, and then a check that the file is one this service
     * wrote: the name arrives from the URL, and a path that escapes the folder
     * would hand out any file the web server can read.
     *
     * @throws BackupFailedException
     */
    public function pathFor(string $name): string
    {
        $name = basename($name);
        $path = $this->directory().'/'.$name;

        if (! str_ends_with($name, self::SUFFIX) || ! is_file($path)) {
            throw BackupFailedException::missing($name);
        }

        return $path;
    }

    public function directory(): string
    {
        return (string) config('backups.path');
    }

    /**
     * Keeps the newest few and deletes the rest, so a machine that is backed up
     * every morning does not slowly fill with copies of itself.
     */
    private function prune(): void
    {
        $stale = $this->files()->slice(max(1, (int) config('backups.keep')));

        foreach ($stale as $file) {
            File::delete($file->getPathname());
        }
    }

    /**
     * @return Collection<int, SplFileInfo>
     */
    private function files(): Collection
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return collect();
        }

        // Sorted by name rather than by modification time: the name is stamped
        // when the copy was taken, and a file that has been moved about keeps
        // its place in the order.
        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), self::SUFFIX))
            ->sortByDesc(fn (SplFileInfo $file): string => $file->getFilename())
            ->values();
    }
}
