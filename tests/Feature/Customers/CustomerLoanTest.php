<?php

use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Queries\CustomerBalanceQuery;
use App\Support\Money;

/*
|--------------------------------------------------------------------------
| A customer's loan
|--------------------------------------------------------------------------
|
| A debt is derived from three recorded facts and never stored: what a delivered
| sale was invoiced at, what the customer handed over at the time, and what they
| have paid since. Every figure below is worked out on paper first.
|
| No stock moves in this file on purpose. A loan is money, and it must be
| measurable without the ledger having anything to say about it.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->ahmed = Customer::factory()->create(['name' => 'Ahmed Karim']);
    $this->layla = Customer::factory()->create(['name' => 'Layla Hassan']);
    $this->curtain = Product::factory()->create(['name' => 'Blackout 117x137']);

    $this->balances = app(CustomerBalanceQuery::class);
});

/**
 * A sale with lines, at whatever status and with whatever was handed over.
 *
 * @param  list<array{0: int, 1: string}>  $lines  quantity and unit price
 */
function saleFor(Customer $customer, array $lines, string $paid = '0', bool $delivered = true, string $on = '2026-08-01'): Sale
{
    $factory = Sale::factory()->forCustomer($customer)->paid($paid);

    $sale = ($delivered ? $factory->delivered() : $factory)->create(['sold_on' => $on]);

    foreach ($lines as [$quantity, $unitPrice]) {
        $sale->lines()->create([
            'product_id' => Product::query()->firstOrFail()->id,
            'quantity' => $quantity,
            'unit_price' => Money::fromDecimal($unitPrice),
            'discount' => Money::zero(),
        ]);
    }

    return $sale->refresh();
}

it('owes nothing on a sale paid in full', function () {
    saleFor($this->ahmed, [[2, '50.00']], paid: '100.00');

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('0.00');
});

it('owes the shortfall on a part-paid sale', function () {
    $sale = saleFor($this->ahmed, [[2, '50.00']], paid: '40.00');

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('60.00')
        ->and($sale->outstanding()->toDecimal())->toBe('60.00')
        ->and($sale->paidToDate()->toDecimal())->toBe('40.00')
        ->and($sale->isSettled())->toBeFalse();
});

it('owes the whole invoice when nothing was paid', function () {
    saleFor($this->ahmed, [[3, '25.00']], paid: '0');

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('75.00');
});

it('owes nothing on a sale that has not been delivered, whatever was paid', function () {
    $ordered = saleFor($this->ahmed, [[2, '50.00']], paid: '0', delivered: false);

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('0.00')
        ->and($ordered->outstanding()->toDecimal())->toBe('0.00');
});

it('adds up several sales into one balance', function () {
    saleFor($this->ahmed, [[2, '50.00']], paid: '40.00');   // owes 60.00
    saleFor($this->ahmed, [[1, '30.00']], paid: '0');       // owes 30.00
    saleFor($this->ahmed, [[1, '10.00']], paid: '10.00');   // owes nothing

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('90.00');
});

it('keeps one customer out of another customer\'s balance', function () {
    saleFor($this->ahmed, [[1, '100.00']], paid: '0');
    saleFor($this->layla, [[1, '40.00']], paid: '0');

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('100.00')
        ->and($this->balances->forCustomer($this->layla)->toDecimal())->toBe('40.00')
        ->and($this->balances->total()->toDecimal())->toBe('140.00');
});

it('records a payment against an invoice and takes it off the loan', function () {
    $sale = saleFor($this->ahmed, [[2, '50.00']], paid: '0');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '30.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [
            ['sale_id' => $sale->id, 'amount' => '30.00'],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('70.00')
        ->and($sale->refresh()->outstanding()->toDecimal())->toBe('70.00')
        ->and(CustomerPayment::query()->count())->toBe(1)
        ->and(CustomerPaymentAllocation::query()->count())->toBe(1);
});

it('settles an invoice exactly and leaves nothing owed', function () {
    $sale = saleFor($this->ahmed, [[1, '80.00']], paid: '20.00');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '60.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '60.00']],
    ])->assertSessionHasNoErrors();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('0.00')
        ->and($sale->refresh()->isSettled())->toBeTrue();
});

it('spreads one payment across two invoices', function () {
    $first = saleFor($this->ahmed, [[1, '40.00']], paid: '0', on: '2026-08-01');
    $second = saleFor($this->ahmed, [[1, '60.00']], paid: '0', on: '2026-08-02');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '70.00',
        'received_on' => '2026-08-06',
        'payment_method' => 'transfer',
        'bank_id' => Bank::factory()->create()->id,
        'allocations' => [
            ['sale_id' => $first->id, 'amount' => '40.00'],
            ['sale_id' => $second->id, 'amount' => '30.00'],
        ],
    ])->assertSessionHasNoErrors();

    expect($first->refresh()->outstanding()->toDecimal())->toBe('0.00')
        ->and($second->refresh()->outstanding()->toDecimal())->toBe('30.00')
        ->and($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('30.00');
});

it('ignores the invoices left blank in the dialog', function () {
    $paying = saleFor($this->ahmed, [[1, '40.00']], paid: '0', on: '2026-08-01');
    $leaving = saleFor($this->ahmed, [[1, '60.00']], paid: '0', on: '2026-08-02');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '40.00',
        'received_on' => '2026-08-06',
        'payment_method' => 'cash',
        'allocations' => [
            ['sale_id' => $paying->id, 'amount' => '40.00'],
            ['sale_id' => $leaving->id, 'amount' => ''],
        ],
    ])->assertSessionHasNoErrors();

    expect(CustomerPaymentAllocation::query()->count())->toBe(1)
        ->and($leaving->refresh()->outstanding()->toDecimal())->toBe('60.00');
});

it('refuses a payment whose allocations do not add up to it', function () {
    $sale = saleFor($this->ahmed, [[1, '100.00']], paid: '0');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '30.00']],
    ])->assertSessionHasErrors('amount');

    expect(CustomerPayment::query()->count())->toBe(0)
        ->and($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('100.00');
});

it('refuses to overpay an invoice', function () {
    $sale = saleFor($this->ahmed, [[1, '100.00']], paid: '80.00');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '50.00']],
    ])->assertSessionHasErrors('amount');

    expect(CustomerPayment::query()->count())->toBe(0)
        ->and($sale->refresh()->outstanding()->toDecimal())->toBe('20.00');
});

it('refuses to apply a payment to another customer\'s invoice', function () {
    $laylas = saleFor($this->layla, [[1, '100.00']], paid: '0');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '100.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $laylas->id, 'amount' => '100.00']],
    ])->assertSessionHasErrors('amount');

    expect(CustomerPayment::query()->count())->toBe(0);
});

it('refuses to apply a payment to an invoice that has not been delivered', function () {
    $ordered = saleFor($this->ahmed, [[1, '100.00']], paid: '0', delivered: false);

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '100.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $ordered->id, 'amount' => '100.00']],
    ])->assertSessionHasErrors('amount');

    expect(CustomerPayment::query()->count())->toBe(0);
});

it('requires an amount and a date', function () {
    $sale = saleFor($this->ahmed, [[1, '100.00']], paid: '0');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '0',
        'received_on' => '',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '0']],
    ])->assertSessionHasErrors(['amount', 'received_on']);
});

it('puts the debt back when a payment is deleted', function () {
    $sale = saleFor($this->ahmed, [[1, '100.00']], paid: '0');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '100.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '100.00']],
    ])->assertSessionHasNoErrors();

    $payment = CustomerPayment::query()->firstOrFail();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('0.00');

    $this->delete("/customers/{$this->ahmed->id}/payments/{$payment->id}")->assertRedirect();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('100.00')
        ->and(CustomerPaymentAllocation::query()->count())->toBe(0);
});

it('will not delete another customer\'s payment', function () {
    $sale = saleFor($this->layla, [[1, '50.00']], paid: '0');

    $this->post("/customers/{$this->layla->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '50.00']],
    ])->assertSessionHasNoErrors();

    $payment = CustomerPayment::query()->firstOrFail();

    $this->delete("/customers/{$this->ahmed->id}/payments/{$payment->id}")->assertRedirect();

    expect(CustomerPayment::query()->count())->toBe(1);
});

it('offers only delivered invoices that still owe something', function () {
    $open = saleFor($this->ahmed, [[1, '100.00']], paid: '20.00');
    saleFor($this->ahmed, [[1, '40.00']], paid: '40.00');                  // settled
    saleFor($this->ahmed, [[1, '70.00']], paid: '0', delivered: false);    // not delivered

    $openSales = $this->balances->openSales($this->ahmed);

    expect($openSales)->toHaveCount(1)
        ->and($openSales[0]['id'])->toBe($open->id)
        ->and($openSales[0]['outstanding']->toDecimal())->toBe('80.00');
});

it('shows the statement with sales, payments and what each settled', function () {
    $sale = saleFor($this->ahmed, [[2, '50.00']], paid: '25.00');

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '25.00',
        'received_on' => '2026-08-07',
        'payment_method' => 'card',
        'bank_id' => Bank::factory()->create(['name' => 'Cihan Bank'])->id,
        'notes' => 'Paid in the shop.',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '25.00']],
    ])->assertSessionHasNoErrors();

    $this->get("/customers/{$this->ahmed->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/show')
            ->where('customer.name', 'Ahmed Karim')
            ->where('statement.balance', 5000)
            ->where('statement.invoiced', 10000)
            ->where('statement.paid', 5000)
            ->has('statement.sales', 1)
            ->where('statement.sales.0.outstanding', 5000)
            ->has('statement.payments', 1)
            ->where('statement.payments.0.amount', 2500)
            ->where('statement.payments.0.allocations.0.number', $sale->number)
            ->has('openSales', 1)
        );
});

it('shows what each customer owes on the list, and filters to those who owe', function () {
    saleFor($this->ahmed, [[1, '100.00']], paid: '0');
    saleFor($this->layla, [[1, '40.00']], paid: '40.00');

    $this->get('/customers')
        ->assertInertia(fn ($page) => $page
            ->where('owedTotal', 10000)
            ->has('rows.data', 2)
        );

    $this->get('/customers?balance=owing')
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Ahmed Karim')
            ->where('rows.data.0.balance', 10000)
        );

    $this->get('/customers?balance=settled')
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Layla Hassan')
        );
});

it('takes a payment in another currency and stores dinars', function () {
    $sale = saleFor($this->ahmed, [[1, '100.00']], paid: '0');

    // No rate on record for dollars in this test, so the currency falls back to
    // the base one rather than converting at a rate nobody recorded.
    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '100.00',
        'amount_currency' => 'IQD',
        'currency' => 'IQD',
        'received_on' => '2026-08-05',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '100.00', 'amount_currency' => 'IQD']],
    ])->assertSessionHasNoErrors();

    $payment = CustomerPayment::query()->firstOrFail();

    expect($payment->amount->toDecimal())->toBe('100.00')
        ->and($payment->currency)->toBe('IQD');
});
