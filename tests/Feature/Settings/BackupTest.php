<?php

use App\Enums\FlashType;
use App\Models\User;
use App\Support\Flash;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Backup
|--------------------------------------------------------------------------
|
| The button is only worth having if what it writes is a database somebody can
| open, so the tests that matter read the copy back rather than asserting a file
| appeared. That needs a real one to copy: `booksInAFile()` migrates a second,
| file-backed connection and points the application at it for the length of the
| test. Tests that do not need a file stay on the suite's `:memory:` database,
| which is also exactly the "nothing here to copy" case.
|
*/

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/cms-backup-'.Str::random(10);

    File::ensureDirectoryExists($this->root);

    config(['backups.path' => $this->root.'/backups']);

    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    // Before anything else: `RefreshDatabase` rolls back the transaction it
    // opened on whatever `database.default` names when the test ends, and marks
    // the entire suite un-migrated if it finds a connection without one. A test
    // that moved the default has to move it back or it breaks the next file.
    config(['database.default' => 'sqlite']);

    File::deleteDirectory($this->root);
});

/**
 * Moves the application onto a file-backed database with the real schema, so
 * there is something on disk to copy. Returns where it went.
 */
function booksInAFile(string $root): string
{
    $database = $root.'/database.sqlite';

    File::put($database, '');

    config([
        'database.connections.books' => [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    Artisan::call('migrate', ['--database' => 'books', '--force' => true]);

    config(['database.default' => 'books']);

    test()->seed(CurrencySeeder::class);
    test()->actingAs(User::factory()->create());

    return $database;
}

/** Every copy sitting in the backups folder, newest first. */
function backupFiles(string $root): array
{
    $directory = $root.'/backups';

    if (! is_dir($directory)) {
        return [];
    }

    return collect(File::files($directory))
        ->map(fn ($file): string => $file->getPathname())
        ->sortDesc()
        ->values()
        ->all();
}

/*
|--------------------------------------------------------------------------
| Taking one
|--------------------------------------------------------------------------
*/

it('writes a copy of the database that can be opened and read', function () {
    booksInAFile($this->root);

    $customer = DB::table('customers')->insertGetId([
        'name' => 'Backed up before the fire',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->post(route('settings.backup.store'))
        ->assertRedirect(route('settings.backup.index'))
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Success->value,
            'message' => 'Backup saved. Download it and keep the file somewhere other than this computer.',
        ]);

    $files = backupFiles($this->root);

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('-database.sqlite');

    // The point of the whole feature: the file is a database, and what was
    // recorded when the button was pressed is in it.
    $copy = new PDO('sqlite:'.$files[0]);
    $name = $copy->query("select name from customers where id = {$customer}")->fetchColumn();

    expect($name)->toBe('Backed up before the fire');
});

it('says so rather than failing when the records are not in a file', function () {
    // The suite's own `:memory:` database, which is what a server install looks
    // like from in here as well: a connection with no file behind it.
    $this->post(route('settings.backup.store'))
        ->assertRedirect(route('settings.backup.index'))
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Error->value,
            'message' => 'This copy keeps its records on a database server, so backups are handled there rather than here.',
        ]);

    expect(backupFiles($this->root))->toBeEmpty();
});

it('deletes the oldest copies once there are more than it keeps', function () {
    booksInAFile($this->root);

    config(['backups.keep' => 2]);

    $directory = $this->root.'/backups';
    File::ensureDirectoryExists($directory);

    foreach (['2020-01-01_000000', '2020-01-02_000000'] as $stamp) {
        File::put($directory.'/'.$stamp.'-database.sqlite', 'old');
    }

    // Anything else living in the folder is left alone: pruning only ever
    // deletes copies this application wrote.
    File::put($directory.'/notes.txt', 'keep me');

    $this->post(route('settings.backup.store'));

    expect(backupFiles($this->root))->toHaveCount(3)
        ->and(File::exists($directory.'/2020-01-02_000000-database.sqlite'))->toBeTrue()
        ->and(File::exists($directory.'/2020-01-01_000000-database.sqlite'))->toBeFalse()
        ->and(File::exists($directory.'/notes.txt'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The screen
|--------------------------------------------------------------------------
*/

it('lists the copies on the machine, newest first', function () {
    $directory = $this->root.'/backups';
    File::ensureDirectoryExists($directory);

    File::put($directory.'/2024-03-01_090000-database.sqlite', 'older');
    File::put($directory.'/2024-05-01_090000-database.sqlite', 'newer');

    $this->get(route('settings.backup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/backup')
            ->where('supported', false)
            ->where('backups.0.name', '2024-05-01_090000-database.sqlite')
            ->where('backups.1.name', '2024-03-01_090000-database.sqlite')
            ->where('backups.1.bytes', 5)
        );
});

it('requires signing in', function () {
    auth()->logout();

    $this->get(route('settings.backup.index'))->assertRedirect(route('login'));
    $this->post(route('settings.backup.store'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Getting one off the machine
|--------------------------------------------------------------------------
*/

it('hands a copy over as a download', function () {
    $directory = $this->root.'/backups';
    File::ensureDirectoryExists($directory);
    File::put($directory.'/2024-05-01_090000-database.sqlite', 'the books');

    $response = $this->get(route('settings.backup.download', '2024-05-01_090000-database.sqlite'));

    $response->assertOk()->assertDownload('2024-05-01_090000-database.sqlite');

    expect($response->streamedContent())->toBe('the books');
});

it('refuses a name that is not a backup on this machine', function (string $name) {
    $directory = $this->root.'/backups';
    File::ensureDirectoryExists($directory);
    File::put($directory.'/notes.txt', 'not yours');

    $this->get('/settings/backup/'.$name)
        ->assertRedirect(route('settings.backup.index'))
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Error->value,
            'message' => 'That backup is no longer on this computer. It may have been deleted to make room for newer ones.',
        ]);
})->with([
    'a file in the folder that is not a backup' => 'notes.txt',
    'a backup that has been pruned away' => '2019-01-01_000000-database.sqlite',
]);

// A name is a filename, never a path. The route pattern is the first line of
// that and the reason this never reaches the controller — the second is
// `BackupService::pathFor()`, because a route pattern is easy to widen later
// without noticing what it was holding back.
it('cannot be walked out of the backups folder', function () {
    $this->get('/settings/backup/'.'..%2F..%2F.env')->assertNotFound();
});
