<?php

use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Banks
|--------------------------------------------------------------------------
|
| A bank is where non-cash money moved. `PaymentMethod::usesBank()` is the one
| place that decides which methods need one, and every screen that takes a
| payment is checked against it here — a rule enforced on three of four forms is
| a rule that leaves untraceable rows on the fourth.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->bank = Bank::factory()->create(['name' => 'Cihan Bank']);
    $this->category = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $this->product = Product::factory()->create([
        'name' => 'Blackout Eyelet Curtain 117x137',
        'cost_price' => '18.00',
        'selling_price' => '44.00',
    ]);
});

/*
|--------------------------------------------------------------------------
| Managing the list
|--------------------------------------------------------------------------
*/

it('lists the banks with what has been paid through each', function () {
    Expense::factory()
        ->for($this->category, 'category')
        ->create(['bank_id' => $this->bank->id, 'payment_method' => PaymentMethod::Transfer]);

    $this->get('/settings/banks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/banks')
            ->has('banks', 1)
            ->where('banks.0.name', 'Cihan Bank')
            ->where('banks.0.expenses_count', 1)
            ->where('banks.0.sales_count', 0)
            ->where('banks.0.payments_count', 0)
        );
});

it('adds a bank', function () {
    $this->post('/settings/banks', [
        'name' => 'Kurdistan International Bank',
        'account_number' => '0123456789',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $bank = Bank::query()->where('name', 'Kurdistan International Bank')->firstOrFail();

    expect($bank->account_number)->toBe('0123456789');
});

it('needs a name', function () {
    $this->post('/settings/banks', ['name' => ''])->assertSessionHasErrors('name');

    expect(Bank::query()->count())->toBe(1);
});

it('refuses a bank that is already on the list', function () {
    $this->post('/settings/banks', ['name' => 'Cihan Bank'])->assertSessionHasErrors('name');

    expect(Bank::query()->count())->toBe(1);
});

it('renames a bank without disturbing what was paid through it', function () {
    $expense = Expense::factory()
        ->for($this->category, 'category')
        ->create(['bank_id' => $this->bank->id, 'payment_method' => PaymentMethod::Transfer]);

    $this->put("/settings/banks/{$this->bank->id}", ['name' => 'Cihan Bank — Erbil'])
        ->assertSessionHasNoErrors();

    expect($this->bank->fresh()->name)->toBe('Cihan Bank — Erbil')
        ->and($expense->fresh()->bank_id)->toBe($this->bank->id);
});

it('deletes a bank nothing was paid through', function () {
    $this->delete("/settings/banks/{$this->bank->id}")->assertRedirect();

    expect(Bank::query()->count())->toBe(0);
});

it('refuses to delete a bank with money against it', function () {
    Expense::factory()
        ->for($this->category, 'category')
        ->create(['bank_id' => $this->bank->id, 'payment_method' => PaymentMethod::Transfer]);

    $this->delete("/settings/banks/{$this->bank->id}")->assertRedirect();

    // Never deleted, and never quietly detached from its history either.
    expect(Bank::query()->count())->toBe(1);
});

it('keeps guests out of the bank list', function () {
    auth()->logout();

    $this->get('/settings/banks')->assertRedirect('/login');
    $this->post('/settings/banks', ['name' => 'Somewhere'])->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| Naming one on a payment
|--------------------------------------------------------------------------
|
| Card and transfer require a bank; cash refuses one. Checked on every form
| that takes a payment, because the rule is only worth having everywhere.
|
*/

it('records the bank an expense was paid out of', function () {
    $this->post('/expenses', [
        'expense_category_id' => $this->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Transfer->value,
        'bank_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    expect(Expense::query()->firstOrFail()->bank_id)->toBe($this->bank->id);
});

it('refuses a card or transfer with no bank named', function (string $method) {
    $this->post('/expenses', [
        'expense_category_id' => $this->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => $method,
    ])->assertSessionHasErrors('bank_id');

    expect(Expense::query()->count())->toBe(0);
})->with([PaymentMethod::Card->value, PaymentMethod::Transfer->value]);

it('refuses a bank on cash, which did not go through one', function () {
    $this->post('/expenses', [
        'expense_category_id' => $this->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Cash->value,
        'bank_id' => $this->bank->id,
    ])->assertSessionHasErrors('bank_id');

    expect(Expense::query()->count())->toBe(0);
});

it('takes cash with the bank field left empty', function () {
    $this->post('/expenses', [
        'expense_category_id' => $this->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Cash->value,
        // What the form sends when the field is on screen but not filled in.
        'bank_id' => '',
    ])->assertSessionHasNoErrors();

    expect(Expense::query()->firstOrFail()->bank_id)->toBeNull();
});

it('refuses a bank that is not on the list', function () {
    $this->post('/expenses', [
        'expense_category_id' => $this->category->id,
        'title' => 'February rent',
        'amount' => '750.00',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Transfer->value,
        'bank_id' => $this->bank->id + 99,
    ])->assertSessionHasErrors('bank_id');
});

it('records the bank a sale was paid into', function () {
    app(InventoryService::class)->receive(
        $this->product,
        10,
        Money::fromDecimal('18.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $this->post('/sales', [
        'customer_id' => Customer::walkIn()->id,
        'sold_on' => '2026-02-01',
        'status' => 'proceed',
        'payment_method' => PaymentMethod::Card->value,
        'bank_id' => $this->bank->id,
        'paid_in_full' => true,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => '44.00',
            'discount' => '0',
        ]],
    ])->assertSessionHasNoErrors();

    expect(Sale::query()->firstOrFail()->bank_id)->toBe($this->bank->id);
});

it('refuses a card sale with no bank named', function () {
    $this->post('/sales', [
        'customer_id' => Customer::walkIn()->id,
        'sold_on' => '2026-02-01',
        'status' => 'proceed',
        'payment_method' => PaymentMethod::Card->value,
        'paid_in_full' => true,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => '44.00',
            'discount' => '0',
        ]],
    ])->assertSessionHasErrors('bank_id');

    expect(Sale::query()->count())->toBe(0);
});

it('records the bank on a quick sale from the catalogue', function () {
    app(InventoryService::class)->receive(
        $this->product,
        10,
        Money::fromDecimal('18.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 1,
        'unit_price' => '44.00',
        'payment_method' => PaymentMethod::Card->value,
        'bank_id' => $this->bank->id,
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    expect(Sale::query()->firstOrFail()->bank_id)->toBe($this->bank->id);
});

it('refuses a quick sale on card with no bank named', function () {
    app(InventoryService::class)->receive(
        $this->product,
        10,
        Money::fromDecimal('18.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 1,
        'unit_price' => '44.00',
        'payment_method' => PaymentMethod::Card->value,
        'sold_on' => '2026-02-01',
    ])->assertSessionHasErrors('bank_id');

    expect(Sale::query()->count())->toBe(0);
});

it('records the bank a customer repayment came into', function () {
    $customer = Customer::factory()->create(['name' => 'Ahmed Karim']);

    $sale = Sale::factory()->forCustomer($customer)->delivered()->paid('0')->create([
        'sold_on' => '2026-08-01',
    ]);
    $sale->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => Money::fromDecimal('50.00'),
        'discount' => Money::zero(),
    ]);

    $this->post("/customers/{$customer->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-08-06',
        'payment_method' => PaymentMethod::Transfer->value,
        'bank_id' => $this->bank->id,
        'allocations' => [['sale_id' => $sale->id, 'amount' => '50.00']],
    ])->assertSessionHasNoErrors();

    expect(CustomerPayment::query()->firstOrFail()->bank_id)->toBe($this->bank->id);
});

it('refuses a transfer repayment with no bank named', function () {
    $customer = Customer::factory()->create(['name' => 'Ahmed Karim']);

    $sale = Sale::factory()->forCustomer($customer)->delivered()->paid('0')->create([
        'sold_on' => '2026-08-01',
    ]);
    $sale->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => Money::fromDecimal('50.00'),
        'discount' => Money::zero(),
    ]);

    $this->post("/customers/{$customer->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-08-06',
        'payment_method' => PaymentMethod::Transfer->value,
        'allocations' => [['sale_id' => $sale->id, 'amount' => '50.00']],
    ])->assertSessionHasErrors('bank_id');

    expect(CustomerPayment::query()->count())->toBe(0);
});

it('sends the bank with each payment method, so a form knows when to ask', function () {
    $this->get('/expenses')
        ->assertInertia(fn ($page) => $page
            ->has('paymentMethods', 3)
            ->where('paymentMethods.0.value', 'cash')
            ->where('paymentMethods.0.uses_bank', false)
            ->where('paymentMethods.1.uses_bank', true)
            ->where('paymentMethods.2.uses_bank', true)
            ->has('banks', 1)
        );
});
