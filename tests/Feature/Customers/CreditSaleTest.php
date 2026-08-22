<?php

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Queries\CashFlowQuery;
use App\Queries\CustomerBalanceQuery;
use App\Services\InventoryService;
use App\Support\Money;
use App\Support\ReportPeriod;

/*
|--------------------------------------------------------------------------
| Selling on credit through the sale screen
|--------------------------------------------------------------------------
|
| The sale screen is where a loan is created: a buyer is named, and whatever is
| short of the total goes on their account. These cover the way in, and the two
| income figures a credit sale produces — what was sold, and what was collected.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->ahmed = Customer::factory()->create(['name' => 'Ahmed Karim']);
    $this->curtain = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'selling_price' => 5000,
    ]);

    // Something on the shelf, so a sale that has gone out can actually issue.
    // Through the service, which is the only thing that writes stock.
    app(InventoryService::class)->receive(
        product: $this->curtain,
        quantity: 100,
        unitCost: Money::fromDecimal('20.00'),
        occurredAt: new DateTimeImmutable('2026-07-01'),
    );

    $this->balances = app(CustomerBalanceQuery::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function creditSalePayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => test()->ahmed->id,
        'sold_on' => '2026-08-01',
        // Ordered, so nothing has to be on the shelf for these to pass: the
        // money side of a sale is what is under test here, not the ledger.
        'status' => SaleStatus::Ordered->value,
        'payment_method' => 'cash',
        'paid_in_full' => true,
        'lines' => [
            ['product_id' => test()->curtain->id, 'quantity' => 2, 'unit_price' => '50.00'],
        ],
    ], $overrides);
}

it('requires a buyer on every sale', function () {
    $payload = creditSalePayload();
    unset($payload['customer_id']);

    $this->post('/sales', $payload)->assertSessionHasErrors('customer_id');

    expect(Sale::query()->count())->toBe(0);
});

it('records the sale against the customer who bought it', function () {
    $this->post('/sales', creditSalePayload())->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->customer_id)->toBe($this->ahmed->id)
        ->and($sale->customer->name)->toBe('Ahmed Karim');
});

it('takes the paid-in-full amount from the lines rather than the client', function () {
    $this->post('/sales', creditSalePayload([
        'lines' => [
            ['product_id' => $this->curtain->id, 'quantity' => 3, 'unit_price' => '33.33'],
        ],
    ]))->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->amount_paid->toDecimal())->toBe('99.99')
        ->and($sale->total()->toDecimal())->toBe('99.99');
});

it('puts the shortfall on the customer account when part paid', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '40.00',
    ]))->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->amount_paid->toDecimal())->toBe('40.00')
        ->and($sale->outstanding()->toDecimal())->toBe('60.00')
        ->and($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('60.00');
});

it('puts the whole invoice on the account when nothing was paid', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '0',
    ]))->assertSessionHasNoErrors();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('100.00');
});

it('refuses to record more paid than the invoice comes to', function () {
    $this->post('/sales', creditSalePayload([
        'paid_in_full' => false,
        'amount_paid' => '150.00',
    ]))->assertSessionHasErrors('amount_paid');

    expect(Sale::query()->count())->toBe(0);
});

it('owes nothing until the goods are the customer\'s', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Ordered->value,
        'paid_in_full' => false,
        'amount_paid' => '0',
    ]))->assertSessionHasNoErrors();

    expect($this->balances->forCustomer($this->ahmed)->toDecimal())->toBe('0.00');
});

it('offers customers and the walk-in first on the sale screen', function () {
    Customer::factory()->walkIn()->create();

    // The drawer lives on the list, so the list is what carries its options.
    $this->get('/sales')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->has('customers', 2)
            ->where('customers.0.name', Customer::WALK_IN)
        );
});

it('shows what is owed on the invoice and in the list', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '25.00',
    ]))->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    $this->get("/sales/{$sale->id}")
        ->assertInertia(fn ($page) => $page
            ->where('sale.customer', 'Ahmed Karim')
            ->where('sale.amount_paid', 2500)
            ->where('sale.paid_to_date', 2500)
            ->where('sale.outstanding', 7500)
            ->where('sale.delivered', true)
        );

    $this->get('/sales')
        ->assertInertia(fn ($page) => $page
            ->where('rows.data.0.customer', 'Ahmed Karim')
            ->where('rows.data.0.outstanding', 7500)
        );
});

it('reports what was sold and what was collected as two different figures', function () {
    // Delivered, half paid: 100.00 sold, 50.00 through the door.
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '50.00',
    ]))->assertSessionHasNoErrors();

    $august = ReportPeriod::between('2026-08-01', '2026-08-31');
    $cashFlow = app(CashFlowQuery::class)->get($august);

    expect($cashFlow['income']->toDecimal())->toBe('100.00')
        ->and($cashFlow['collected']->toDecimal())->toBe('50.00');

    // The repayment lands in the period it was received, not the sale's.
    $sale = Sale::query()->firstOrFail();

    $this->post("/customers/{$this->ahmed->id}/payments", [
        'amount' => '50.00',
        'received_on' => '2026-09-03',
        'payment_method' => 'cash',
        'allocations' => [['sale_id' => $sale->id, 'amount' => '50.00']],
    ])->assertSessionHasNoErrors();

    $september = ReportPeriod::between('2026-09-01', '2026-09-30');

    expect(app(CashFlowQuery::class)->get($august)['collected']->toDecimal())->toBe('50.00')
        ->and(app(CashFlowQuery::class)->get($september)['income']->toDecimal())->toBe('0.00')
        ->and(app(CashFlowQuery::class)->get($september)['collected']->toDecimal())->toBe('50.00');
});

it('leaves an undelivered sale out of both income figures', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Ordered->value,
        'paid_in_full' => true,
    ]))->assertSessionHasNoErrors();

    $cashFlow = app(CashFlowQuery::class)->get(ReportPeriod::between('2026-08-01', '2026-08-31'));

    expect($cashFlow['income']->toDecimal())->toBe('0.00')
        ->and($cashFlow['collected']->toDecimal())->toBe('0.00');
});

it('puts what customers owe on the report and the dashboard', function () {
    $this->post('/sales', creditSalePayload([
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '0',
    ]))->assertSessionHasNoErrors();

    $this->get('/reports')->assertInertia(fn ($page) => $page->where('owed', 10000));
    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('owed', 10000));
});
