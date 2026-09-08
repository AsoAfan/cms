<?php

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Models\Bank;
use App\Models\BankAdjustment;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Queries\BankBalanceQuery;
use App\Support\Money;

/*
|--------------------------------------------------------------------------
| Bank balances
|--------------------------------------------------------------------------
|
| What an account holds is never stored. `BankBalanceQuery` derives it from
| everything that moved through the account, and what counts is scoped exactly
| as the report is — a sale from delivered, an invoice from the status that puts
| its goods in the ledger — so a balance and the report above it can never
| describe different sets of documents.
|
| `BankAdjustment` is the rest: the balance the account already had, and
| anything else no document explains.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->bank = Bank::factory()->create(['name' => 'Cihan Bank']);
    $this->product = Product::factory()->create(['name' => 'Blackout 117x137']);
});

function bankBalance(Bank $bank): Money
{
    return app(BankBalanceQuery::class)->get()[$bank->id] ?? Money::zero();
}

/** An invoice with one line, at the status given. */
function invoicePaidFrom(?Bank $bank, string $unitCost, PurchaseStatus $status): Purchase
{
    $purchase = Purchase::factory()->create([
        'invoiced_on' => '2026-02-01',
        'status' => $status,
        'payment_method' => $bank === null ? PaymentMethod::Cash : PaymentMethod::Transfer,
        'bank_id' => $bank?->id,
    ]);

    $purchase->lines()->create([
        'product_id' => test()->product->id,
        'quantity' => 1,
        'unit_cost' => Money::fromDecimal($unitCost),
        'discount' => Money::zero(),
    ]);

    return $purchase;
}

/*
|--------------------------------------------------------------------------
| What moves a balance
|--------------------------------------------------------------------------
*/

it('starts an account nothing has moved through at nothing', function () {
    expect(bankBalance($this->bank)->minorUnits)->toBe(0);
});

it('carries the balance an account already had', function () {
    BankAdjustment::factory()->for($this->bank)->create([
        'reason' => 'Opening balance',
        'amount' => Money::fromDecimal('1500.00'),
    ]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('1500.00');
});

it('takes money handed over on a delivered sale into the account', function () {
    Sale::factory()->delivered()->paid('120.00')->create([
        'payment_method' => PaymentMethod::Card,
        'bank_id' => $this->bank->id,
    ]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('120.00');
});

it('leaves money on a sale the customer has not received alone', function () {
    // A deposit on goods still on the shelf. The report does not count it as
    // income either — the two views stay in step.
    Sale::factory()->sentOut()->paid('120.00')->create([
        'payment_method' => PaymentMethod::Card,
        'bank_id' => $this->bank->id,
    ]);

    expect(bankBalance($this->bank)->minorUnits)->toBe(0);
});

it('takes a customer repayment into the account it was received in', function () {
    CustomerPayment::factory()
        ->forCustomer(Customer::factory()->create())
        ->of('75.00')
        ->create([
            'payment_method' => PaymentMethod::Transfer,
            'bank_id' => $this->bank->id,
        ]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('75.00');
});

it('takes an expense out of the account it was paid from', function () {
    BankAdjustment::factory()->for($this->bank)->create(['amount' => Money::fromDecimal('500.00')]);

    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory()->create(['name' => 'Rent'])->id,
        'amount' => Money::fromDecimal('200.00'),
        'payment_method' => PaymentMethod::Transfer,
        'bank_id' => $this->bank->id,
    ]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('300.00');
});

it('takes an arrived invoice out of the account it was paid from', function () {
    BankAdjustment::factory()->for($this->bank)->create(['amount' => Money::fromDecimal('500.00')]);

    invoicePaidFrom($this->bank, '180.00', PurchaseStatus::Proceed);

    expect(bankBalance($this->bank)->toDecimal())->toBe('320.00');
});

it('counts the freight invoiced with the goods as money out too', function () {
    $purchase = invoicePaidFrom($this->bank, '180.00', PurchaseStatus::Proceed);

    $purchase->additionalCosts()->create([
        'label' => 'Freight',
        'amount' => Money::fromDecimal('20.00'),
        'allocation_method' => 'by_quantity',
    ]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('-200.00');
});

it('leaves an invoice whose goods have not arrived alone', function () {
    invoicePaidFrom($this->bank, '180.00', PurchaseStatus::Ordered);

    expect(bankBalance($this->bank)->minorUnits)->toBe(0);
});

it('ignores documents that named no account at all', function () {
    Sale::factory()->delivered()->paid('120.00')->create([
        'payment_method' => PaymentMethod::Cash,
        'bank_id' => null,
    ]);

    invoicePaidFrom(null, '180.00', PurchaseStatus::Proceed);

    expect(bankBalance($this->bank)->minorUnits)->toBe(0);
});

it('keeps each account to its own money', function () {
    $other = Bank::factory()->create(['name' => 'Kurdistan International Bank']);

    BankAdjustment::factory()->for($this->bank)->create(['amount' => Money::fromDecimal('100.00')]);
    BankAdjustment::factory()->for($other)->create(['amount' => Money::fromDecimal('250.00')]);

    expect(bankBalance($this->bank)->toDecimal())->toBe('100.00')
        ->and(bankBalance($other)->toDecimal())->toBe('250.00')
        ->and(app(BankBalanceQuery::class)->total()->toDecimal())->toBe('350.00');
});

/*
|--------------------------------------------------------------------------
| Setting a balance by hand
|--------------------------------------------------------------------------
|
| Trade cannot explain the balance an account opened with, cash walked to the
| bank, interest or charges. This is where those go.
|
*/

it('adds money to an account', function () {
    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'in',
        'reason' => 'Opening balance',
        'amount' => '1000.00',
        'occurred_on' => '2026-01-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(bankBalance($this->bank)->toDecimal())->toBe('1000.00');
});

it('takes money back out, storing it signed', function () {
    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'out',
        'reason' => 'Bank charge',
        'amount' => '25.00',
        'occurred_on' => '2026-01-31',
    ])->assertSessionHasNoErrors();

    $adjustment = BankAdjustment::query()->firstOrFail();

    expect($adjustment->amount->toDecimal())->toBe('-25.00')
        ->and(bankBalance($this->bank)->toDecimal())->toBe('-25.00');
});

it('needs to be told what the money was', function () {
    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'in',
        'reason' => '',
        'amount' => '1000.00',
        'occurred_on' => '2026-01-01',
    ])->assertSessionHasErrors('reason');

    expect(BankAdjustment::query()->count())->toBe(0);
});

it('refuses a movement of nothing', function (string $amount) {
    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'in',
        'reason' => 'Opening balance',
        'amount' => $amount,
        'occurred_on' => '2026-01-01',
    ])->assertSessionHasErrors('amount');

    expect(BankAdjustment::query()->count())->toBe(0);
})->with(['0.00', '-50.00']);

it('refuses a direction that is neither in nor out', function () {
    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'sideways',
        'reason' => 'Opening balance',
        'amount' => '10.00',
        'occurred_on' => '2026-01-01',
    ])->assertSessionHasErrors('direction');
});

it('puts the balance back when a movement is removed', function () {
    $adjustment = BankAdjustment::factory()->for($this->bank)->create([
        'amount' => Money::fromDecimal('1000.00'),
    ]);

    $this->delete("/settings/bank-balance/{$adjustment->id}")->assertRedirect();

    expect(BankAdjustment::query()->count())->toBe(0)
        ->and(bankBalance($this->bank)->minorUnits)->toBe(0);
});

it('refuses to delete an account that has been set by hand', function () {
    BankAdjustment::factory()->for($this->bank)->create();

    $this->delete("/settings/banks/{$this->bank->id}")->assertRedirect();

    expect(Bank::query()->count())->toBe(1);
});

it('keeps guests away from balances', function () {
    auth()->logout();

    $this->post("/settings/banks/{$this->bank->id}/balance", [
        'direction' => 'in',
        'reason' => 'Opening balance',
        'amount' => '10.00',
        'occurred_on' => '2026-01-01',
    ])->assertRedirect('/login');

    expect(BankAdjustment::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| On the screens
|--------------------------------------------------------------------------
*/

it('shows each account and what it holds on the settings screen', function () {
    BankAdjustment::factory()->for($this->bank)->create([
        'reason' => 'Opening balance',
        'amount' => Money::fromDecimal('1000.00'),
    ]);

    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory()->create(['name' => 'Rent'])->id,
        'amount' => Money::fromDecimal('250.00'),
        'payment_method' => PaymentMethod::Transfer,
        'bank_id' => $this->bank->id,
    ]);

    $this->get('/settings/banks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/banks')
            ->where('banks.0.balance', 75_000)
            ->where('banks.0.adjustments_count', 1)
            ->where('balanceTotal', 75_000)
            ->has('adjustments', 1)
            ->where('adjustments.0.reason', 'Opening balance')
            ->where('adjustments.0.bank', 'Cihan Bank')
            ->has('directions', 2)
        );
});

it('shows the balances on the report screen, whatever period it is showing', function () {
    BankAdjustment::factory()->for($this->bank)->create([
        // Years before any period the report offers: a balance is a position,
        // not something that happened in the window.
        'occurred_on' => '2020-01-01',
        'amount' => Money::fromDecimal('400.00'),
    ]);

    $this->get('/reports?preset=this_month')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('bankBalances.accounts', 1)
            ->where('bankBalances.accounts.0.name', 'Cihan Bank')
            ->where('bankBalances.accounts.0.balance', 40_000)
            ->where('bankBalances.total', 40_000)
        );
});
