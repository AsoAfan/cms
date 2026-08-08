<?php

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->category = ExpenseCategory::factory()->create(['name' => 'Rent']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function expensePayload(array $overrides = []): array
{
    return array_merge([
        'expense_category_id' => test()->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Transfer->value,
        'notes' => 'Paid to the landlord.',
    ], $overrides);
}

it('lists expenses with the total for what is shown', function () {
    Expense::factory()->for($this->category, 'category')->create([
        'amount' => Money::fromDecimal('750.00'),
        'spent_on' => '2026-02-01',
    ]);
    Expense::factory()->for($this->category, 'category')->create([
        'amount' => Money::fromDecimal('250.00'),
        'spent_on' => '2026-02-05',
    ]);

    $this->get('/expenses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/index')
            ->has('rows.data', 2)
            ->where('filteredTotal', 100000)
            ->has('categories')
            ->has('paymentMethods', 3)
        );
});

it('records an expense', function () {
    $this->post('/expenses', expensePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $expense = Expense::query()->firstOrFail();

    expect($expense->amount->toDecimal())->toBe('750.00')
        ->and($expense->payment_method)->toBe(PaymentMethod::Transfer)
        ->and($expense->title)->toBe('February rent')
        ->and($expense->category->name)->toBe('Rent');
});

it('stores the amount as integer minor units', function () {
    $this->post('/expenses', expensePayload(['amount' => '12.34']));

    expect(DB::table('expenses')->value('amount'))->toBe(1234);
});

it('needs a category, a title, an amount, a date and a payment method', function () {
    $this->post('/expenses', [])->assertSessionHasErrors([
        'expense_category_id',
        'title',
        'amount',
        'spent_on',
        'payment_method',
    ]);

    expect(Expense::query()->count())->toBe(0);
});

it('refuses an amount of nothing or less', function (string $amount) {
    $this->post('/expenses', expensePayload(['amount' => $amount]))
        ->assertSessionHasErrors('amount');
})->with(['0', '0.00', '-5.00']);

it('refuses an amount with more precision than a cent', function () {
    $this->post('/expenses', expensePayload(['amount' => '12.345']))
        ->assertSessionHasErrors('amount');
});

it('updates an expense', function () {
    $expense = Expense::factory()->for($this->category, 'category')->create();

    $this->put("/expenses/{$expense->id}", expensePayload(['amount' => '900.00']))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->amount->toDecimal())->toBe('900.00');
});

it('deletes an expense', function () {
    $expense = Expense::factory()->for($this->category, 'category')->create();

    $this->delete("/expenses/{$expense->id}")->assertRedirect();

    expect(Expense::query()->count())->toBe(0);
});

it('filters by date range, and totals only what is shown', function () {
    Expense::factory()->for($this->category, 'category')->create([
        'amount' => Money::fromDecimal('100.00'),
        'spent_on' => '2026-01-15',
    ]);
    Expense::factory()->for($this->category, 'category')->create([
        'amount' => Money::fromDecimal('250.00'),
        'spent_on' => '2026-02-10',
    ]);
    Expense::factory()->for($this->category, 'category')->create([
        'amount' => Money::fromDecimal('400.00'),
        'spent_on' => '2026-03-20',
    ]);

    $this->get('/expenses?from=2026-02-01&to=2026-02-28')
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('filteredTotal', 25000)
        );
});

it('filters by category', function () {
    $wages = ExpenseCategory::factory()->create(['name' => 'Salaries']);

    Expense::factory()->for($this->category, 'category')->create(['amount' => Money::fromDecimal('750.00')]);
    Expense::factory()->for($wages, 'category')->create(['amount' => Money::fromDecimal('1200.00')]);

    $this->get("/expenses?expense_category_id={$wages->id}")
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('filteredTotal', 120000)
        );
});

it('searches by title and notes', function () {
    Expense::factory()->for($this->category, 'category')->create(['title' => 'February rent', 'notes' => null]);
    Expense::factory()->for($this->category, 'category')->create(['title' => 'Van diesel', 'notes' => 'Motorway run']);

    $this->get('/expenses?search=rent')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1));

    $this->get('/expenses?search=motorway')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1));
});

it('adds a category', function () {
    $this->post('/expense-categories', ['name' => 'Insurance'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ExpenseCategory::query()->where('name', 'Insurance')->exists())->toBeTrue();
});

it('refuses a duplicate category name', function () {
    $this->post('/expense-categories', ['name' => 'Rent'])->assertSessionHasErrors('name');

    expect(ExpenseCategory::query()->count())->toBe(1);
});

it('renames a category', function () {
    $this->put("/expense-categories/{$this->category->id}", ['name' => 'Premises'])
        ->assertSessionHasNoErrors();

    expect($this->category->fresh()->name)->toBe('Premises');
});

it('deletes an unused category', function () {
    $spare = ExpenseCategory::factory()->create();

    $this->delete("/expense-categories/{$spare->id}")->assertRedirect();

    expect(ExpenseCategory::query()->whereKey($spare->id)->exists())->toBeFalse();
});

it('refuses to delete a category with expenses against it', function () {
    Expense::factory()->for($this->category, 'category')->create();

    $this->delete("/expense-categories/{$this->category->id}")->assertRedirect();

    expect(ExpenseCategory::query()->whereKey($this->category->id)->exists())->toBeTrue();
});

it('never touches stock', function () {
    $this->post('/expenses', expensePayload());
    $expense = Expense::query()->firstOrFail();
    $this->put("/expenses/{$expense->id}", expensePayload(['amount' => '900.00']));
    $this->delete("/expenses/{$expense->id}");

    // Rent is a cost when it is paid; buying goods only becomes a cost when
    // they are sold. The two must never meet.
    expect(StockMovement::query()->count())->toBe(0);
});

it('keeps guests out of expenses', function () {
    auth()->logout();

    $this->get('/expenses')->assertRedirect('/login');
    $this->post('/expenses', expensePayload())->assertRedirect('/login');
});
