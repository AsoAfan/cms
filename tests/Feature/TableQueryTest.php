<?php

use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * @param  array<string, string>  $query
 * @return TableQuery<User>
 */
function usersTable(array $query = []): TableQuery
{
    return TableQuery::for(User::query(), Request::create('/users', 'GET', $query));
}

it('returns every row when nothing is requested', function () {
    User::factory()->count(3)->create();

    expect(usersTable()->paginate()->total())->toBe(3);
});

it('searches across the whitelisted columns', function () {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $byName = usersTable(['search' => 'Lovelace'])->searchable(['name', 'email'])->paginate();
    $byEmail = usersTable(['search' => 'grace@'])->searchable(['name', 'email'])->paginate();

    expect($byName->total())->toBe(1)
        ->and($byName->first()->name)->toBe('Ada Lovelace')
        ->and($byEmail->total())->toBe(1)
        ->and($byEmail->first()->name)->toBe('Grace Hopper');
});

it('searches case-insensitively', function () {
    User::factory()->create(['name' => 'Ada Lovelace']);

    expect(usersTable(['search' => 'lovelace'])->searchable(['name'])->paginate()->total())->toBe(1);
});

it('treats a blank search as no search at all', function () {
    User::factory()->count(2)->create();

    expect(usersTable(['search' => '   '])->searchable(['name'])->paginate()->total())->toBe(2);
});

it('sorts by a whitelisted column in both directions', function () {
    User::factory()->create(['name' => 'Charlie']);
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    $ascending = usersTable(['sort' => 'name'])->sortable(['name'])->paginate();
    $descending = usersTable(['sort' => 'name', 'direction' => 'desc'])->sortable(['name'])->paginate();

    expect($ascending->pluck('name')->all())->toBe(['Alice', 'Bob', 'Charlie'])
        ->and($descending->pluck('name')->all())->toBe(['Charlie', 'Bob', 'Alice']);
});

it('ignores a sort column that was never whitelisted', function () {
    User::factory()->create(['name' => 'Charlie']);
    User::factory()->create(['name' => 'Alice']);

    $rows = usersTable(['sort' => 'email'])->sortable(['name'], default: 'name')->paginate();

    // Falls back to the declared default rather than ordering by the
    // attacker-supplied column.
    expect($rows->pluck('name')->all())->toBe(['Alice', 'Charlie']);
});

it('falls back to the default sort and direction', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Charlie']);

    $rows = usersTable()->sortable(['name'], default: 'name', direction: 'desc')->paginate();

    expect($rows->pluck('name')->all())->toBe(['Charlie', 'Alice']);
});

it('sorts through a renamed key and a closure', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Charlie']);

    $renamed = usersTable(['sort' => 'full_name'])->sortable(['full_name' => 'name'])->paginate();
    $viaClosure = usersTable(['sort' => 'custom'])
        ->sortable(['custom' => fn (Builder $query, string $direction) => $query->orderBy('name', $direction)])
        ->paginate();

    expect($renamed->pluck('name')->all())->toBe(['Alice', 'Charlie'])
        ->and($viaClosure->pluck('name')->all())->toBe(['Alice', 'Charlie']);
});

it('applies an equality filter', function () {
    User::factory()->create(['email' => 'keep@example.com']);
    User::factory()->create(['email' => 'drop@example.com']);

    $rows = usersTable(['email' => 'keep@example.com'])->filterable(['email'])->paginate();

    expect($rows->total())->toBe(1)
        ->and($rows->first()->email)->toBe('keep@example.com');
});

it('applies a closure filter', function () {
    User::factory()->create(['name' => 'Ada', 'email_verified_at' => null]);
    User::factory()->create(['name' => 'Grace']);

    $rows = usersTable(['status' => 'unverified'])
        ->filterable(['status' => fn (Builder $query, string $value) => $query->whereNull('email_verified_at')])
        ->paginate();

    expect($rows->total())->toBe(1)
        ->and($rows->first()->name)->toBe('Ada');
});

it('skips filters whose parameter is absent or blank', function () {
    User::factory()->count(2)->create();

    expect(usersTable()->filterable(['email'])->paginate()->total())->toBe(2)
        ->and(usersTable(['email' => ''])->filterable(['email'])->paginate()->total())->toBe(2);
});

it('restricts to a date range on both bounds', function () {
    User::factory()->create(['name' => 'Old', 'created_at' => '2026-01-10']);
    User::factory()->create(['name' => 'Middle', 'created_at' => '2026-02-15']);
    User::factory()->create(['name' => 'New', 'created_at' => '2026-03-20']);

    $rows = usersTable(['from' => '2026-02-01', 'to' => '2026-02-28'])
        ->dateRange('created_at')
        ->paginate();

    expect($rows->total())->toBe(1)
        ->and($rows->first()->name)->toBe('Middle');
});

it('includes rows falling exactly on a range boundary', function () {
    User::factory()->create(['created_at' => '2026-02-01 09:30:00']);
    User::factory()->create(['created_at' => '2026-02-28 23:59:00']);

    $rows = usersTable(['from' => '2026-02-01', 'to' => '2026-02-28'])->dateRange('created_at')->paginate();

    expect($rows->total())->toBe(2);
});

it('paginates with a default and an overridable page size', function () {
    User::factory()->count(30)->create();

    expect(usersTable()->paginate()->perPage())->toBe(25)
        ->and(usersTable()->perPage(10)->paginate()->perPage())->toBe(10)
        ->and(usersTable(['per_page' => '5'])->paginate()->perPage())->toBe(5);
});

it('clamps an absurd page size and ignores a nonsense one', function () {
    User::factory()->count(3)->create();

    expect(usersTable(['per_page' => '100000'])->paginate()->perPage())->toBe(200)
        ->and(usersTable(['per_page' => '0'])->paginate()->perPage())->toBe(25)
        ->and(usersTable(['per_page' => 'lots'])->paginate()->perPage())->toBe(25);
});

it('keeps the query string on pagination links so filters survive a page change', function () {
    User::factory()->count(30)->sequence(fn ($sequence) => ['name' => "Ada {$sequence->index}"])->create();

    $rows = usersTable(['search' => 'Ada', 'sort' => 'name'])
        ->searchable(['name'])
        ->sortable(['name'])
        ->paginate();

    expect($rows->total())->toBe(30)
        ->and($rows->nextPageUrl())->toContain('search=Ada')->toContain('sort=name');
});

it('reads the requested page from the same request it filters with', function () {
    User::factory()->count(30)->sequence(fn ($sequence) => ['name' => sprintf('User %02d', $sequence->index)])->create();

    $page = usersTable(['page' => '2'])->sortable(['name'], default: 'name')->perPage(10)->paginate();

    expect($page->currentPage())->toBe(2)
        ->and($page->first()->name)->toBe('User 10');
});

it('reports the table state back to the frontend', function () {
    $state = usersTable([
        'search' => 'ada',
        'sort' => 'name',
        'direction' => 'desc',
        'per_page' => '50',
        'email' => 'ada@example.com',
    ])
        ->searchable(['name'])
        ->sortable(['name'])
        ->filterable(['email'])
        ->state();

    expect($state)->toBe([
        'search' => 'ada',
        'sort' => 'name',
        'direction' => 'desc',
        'per_page' => 50,
        'filters' => ['email' => 'ada@example.com'],
    ]);
});

it('reports resolved defaults when the request carries no state', function () {
    $state = usersTable()->sortable(['name'], default: 'name')->filterable(['email'])->state();

    expect($state)->toBe([
        'search' => null,
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 25,
        'filters' => [],
    ]);
});
