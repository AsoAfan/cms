<?php

use App\Enums\FlashType;
use App\Exceptions\UpdateFailedException;
use App\Models\User;
use App\Services\UpdateService;
use App\Support\Flash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Updates
|--------------------------------------------------------------------------
|
| The screen a non-technical owner uses instead of downloading a zip and
| replacing files by hand, so these run against real git repositories rather
| than a faked process: the whole mechanism is git's behaviour, and a test that
| mocks it away would prove only that the mock was written to agree.
|
| Each test builds a throwaway "release branch" and a throwaway "client copy" of
| it in a scratch directory, and points the updater at those instead of at the
| checkout running the suite.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->root = sys_get_temp_dir().'/cms-update-'.Str::random(10);
    $this->origin = $this->root.'/origin';
    $this->install = $this->root.'/install';

    File::ensureDirectoryExists($this->origin);

    runGit($this->origin, ['init', '-b', 'release']);
    runGit($this->origin, ['config', 'user.email', 'ci@example.com']);
    runGit($this->origin, ['config', 'user.name', 'CI']);

    File::put($this->origin.'/version.txt', 'one');
    runGit($this->origin, ['add', '-A']);
    runGit($this->origin, ['commit', '-m', 'First release']);

    // `file://` rather than a bare path: a plain local clone is a hardlinked
    // shortcut that ignores `--depth`, and the updater always fetches shallow.
    runGit($this->root, ['clone', 'file://'.$this->origin, $this->install]);

    config([
        'updates.remote' => 'file://'.$this->origin,
        'updates.branch' => 'release',
        'updates.path' => $this->install,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/** Runs git in a directory and fails the test loudly if it does not work. */
function runGit(string $path, array $arguments): void
{
    $result = Process::path($path)->run(['git', ...$arguments]);

    expect($result->successful())->toBeTrue($result->errorOutput());
}

/** Puts a new release on the branch the client copy follows. */
function publishRelease(string $origin, string $contents, string $subject): void
{
    File::put($origin.'/version.txt', $contents);
    runGit($origin, ['add', '-A']);
    runGit($origin, ['commit', '-m', $subject]);
}

/*
|--------------------------------------------------------------------------
| The screen
|--------------------------------------------------------------------------
*/

it('opens on the installed version without going near the network', function () {
    $this->get('/settings/update')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/update')
            ->where('configured', true)
            ->where('installed.subject', 'First release')
            ->has('installed.short')
            // Nothing has been asked yet, and asking is not this request's job.
            // A screen that waits on a shop's internet looks broken.
            ->where('update.checked_at', null)
            ->where('update.stale', true)
            ->etc()
        );
});

/*
|--------------------------------------------------------------------------
| Noticing on its own
|--------------------------------------------------------------------------
|
| The check is fired from the browser and its answer is remembered, so every
| screen can carry "an update is waiting" without any of them paying for it.
|
*/

it('says so when the copy is already current', function () {
    $this->post('/settings/update/check');

    $this->get('/settings/update')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('update.available', false)
            ->where('update.stale', false)
            ->has('update.checked_at')
            ->etc()
        );
});

it('notices a new release and remembers what it changes', function () {
    publishRelease($this->origin, 'two', 'Fix the total on a discounted sale');
    publishRelease($this->origin, 'three', 'Show the supplier phone number');

    $this->post('/settings/update/check');

    // Any screen at all, not just the settings one: this is what puts the dot
    // on the sidebar while somebody is writing an invoice.
    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('update.available', true)
            ->where('update.latest.subject', 'Show the supplier phone number')
            ->where('update.changes', [
                'Show the supplier phone number',
                'Fix the total on a discounted sale',
            ])
            ->etc()
        );
});

/*
 * The browser fires the check on every navigation once the answer has gone
 * stale. Without a remembered timestamp that would be a fetch per page view.
 */
it('stops asking again while the answer is still fresh', function () {
    $this->post('/settings/update/check');

    publishRelease($this->origin, 'two', 'A release nobody has asked about yet');

    $this->post('/settings/update/check');

    $this->get('/settings/update')
        ->assertInertia(fn ($page) => $page->where('update.available', false)->etc());

    // Until somebody presses the button, which always means now.
    $this->post('/settings/update/check', ['force' => 1]);

    $this->get('/settings/update')
        ->assertInertia(fn ($page) => $page
            ->where('update.available', true)
            ->where('update.latest.subject', 'A release nobody has asked about yet')
            ->etc()
        );
});

/*
|--------------------------------------------------------------------------
| Installing
|--------------------------------------------------------------------------
*/

it('installs the newest release', function () {
    publishRelease($this->origin, 'two', 'Fix the total on a discounted sale');

    $released = now()->format('j M Y');

    $this->post('/settings/update')
        ->assertRedirect('/settings/update')
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Success->value,
            'message' => "Updated. This copy is now the version from {$released}.",
        ]);

    expect(File::get($this->install.'/version.txt'))->toBe('two');
});

it('takes the notice down as soon as the update lands', function () {
    publishRelease($this->origin, 'two', 'Fix the total on a discounted sale');

    $this->post('/settings/update/check');

    $this->get('/settings/update')
        ->assertInertia(fn ($page) => $page->where('update.available', true)->etc());

    $this->post('/settings/update');

    $this->get('/settings/update')
        ->assertInertia(fn ($page) => $page->where('update.available', false)->etc());
});

it('does nothing when there is nothing to install', function () {
    $this->post('/settings/update')
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Info->value,
            'message' => 'You already have the newest version.',
        ]);

    expect(File::get($this->install.'/version.txt'))->toBe('one');
});

/*
|--------------------------------------------------------------------------
| When it goes wrong
|--------------------------------------------------------------------------
*/

it('puts the files back when the update cannot be finished', function () {
    publishRelease($this->origin, 'two', 'A release that will not migrate');

    // The files have already moved by the time migrations run, which is the
    // only window where a client can be left on a half-applied copy.
    Artisan::shouldReceive('call')->with('optimize:clear')->andReturn(0);
    Artisan::shouldReceive('call')
        ->with('migrate', ['--force' => true])
        ->andThrow(new RuntimeException('SQLSTATE[HY000]: near "creat": syntax error'));

    expect(fn () => app(UpdateService::class)->apply())
        ->toThrow(UpdateFailedException::class, 'was undone');

    expect(File::get($this->install.'/version.txt'))->toBe('one');
});

it('refuses to update a copy that has no update source', function () {
    config(['updates.remote' => '']);

    $this->post('/settings/update')
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Error->value,
            'message' => 'This copy is not set up to receive updates. Ask whoever installed it to fill in UPDATE_REMOTE.',
        ]);
});

/*
 * The background check runs unattended, on whatever screen the user happens to
 * be on. A shop whose internet is down must not be told about it in a toast
 * every time they open a page — but somebody who pressed the button is waiting
 * for an answer and gets one.
 */
it('stays quiet when a check nobody asked for cannot reach the server', function () {
    config([
        'updates.remote' => 'https://x-access-token:s3cret@127.0.0.1:1/owner/repo.git',
        'updates.check_timeout' => 10,
    ]);

    $this->post('/settings/update/check')
        ->assertInertiaFlashMissing(Flash::KEY);

    $this->post('/settings/update/check', ['force' => 1])
        ->assertRedirect('/settings/update')
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Error->value,
            'message' => 'Could not reach the update server. Check the internet connection and try again.',
        ]);
});

/*
 * The remote URL is the credential on a private repository, and git echoes the
 * URL it was handed in most of its failures. Nothing that reaches a screen, a
 * flash message or a log file may carry it.
 */
it('keeps the repository token out of everything it reports', function () {
    config([
        'updates.remote' => 'https://x-access-token:s3cret@127.0.0.1:1/owner/repo.git',
        'updates.check_timeout' => 10,
    ]);

    try {
        app(UpdateService::class)->check();
        $this->fail('The check should not have succeeded.');
    } catch (UpdateFailedException $failure) {
        expect($failure->detail)->not->toContain('s3cret')
            ->and($failure->getMessage())->not->toContain('s3cret');
    }

    $this->get('/settings/update')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'remote',
            'https://•••@127.0.0.1:1/owner/repo.git'
        ));
});
