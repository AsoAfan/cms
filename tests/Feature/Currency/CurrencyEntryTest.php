<?php

use App\Enums\CostAllocationMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ExchangeRates;

/*
|--------------------------------------------------------------------------
| Entering money in a currency that is not the one it is stored in
|--------------------------------------------------------------------------
|
| The rule this whole file exists to pin: whatever an amount is TYPED in, what
| lands in the database is base-currency minor units, converted once at the rate
| in force on the document's own date. Everything downstream — the FIFO ledger,
| every report query, every CSV — reads that one currency and knows nothing
| about any other.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create(['name' => 'Blackout Eyelet Curtain 117x137']);

    // 1,320 dinars to the dollar, in force well before anything here is dated.
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();
});

it('converts a purchase typed in dollars into dinars', function () {
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => '18.50',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $purchase = Purchase::query()->with('lines')->firstOrFail();

    // $18.50 × 1,320 = 24,420 dinars, to the dinar.
    expect($purchase->lines->first()->unit_cost->toDecimal())->toBe('24420.00')
        ->and($purchase->currency)->toBe('USD')
        ->and($purchase->exchange_rate)->toBe(1_320_000_000)
        ->and($purchase->exchangeRate())->toBe('1320');
});

it('records the base currency and a rate of one when nothing is converted', function () {
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => '24420',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->with('lines')->firstOrFail();

    expect($purchase->currency)->toBe('IQD')
        ->and($purchase->exchange_rate)->toBe(ExchangeRates::SCALE)
        ->and($purchase->lines->first()->unit_cost->toDecimal())->toBe('24420.00');
});

it('converts each amount by its own currency, not the document header', function () {
    // A dollar-priced invoice with a discount and a freight charge that were
    // both settled locally, in dinars. This is the case the per-field switcher
    // exists for.
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => '18.50',
            'discount' => '5000',
            'discount_currency' => 'IQD',
        ]],
        'additional_costs' => [[
            'label' => 'Freight',
            'amount' => '30000',
            'amount_currency' => 'IQD',
            'allocation_method' => CostAllocationMethod::ByQuantity->value,
        ]],
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->with(['lines', 'additionalCosts'])->firstOrFail();

    expect($purchase->lines->first()->unit_cost->toDecimal())->toBe('24420.00')
        ->and($purchase->lines->first()->discount->toDecimal())->toBe('5000.00')
        ->and($purchase->additionalCosts->first()->amount->toDecimal())->toBe('30000.00');
});

it('puts the converted cost on the shelf when the goods arrive', function () {
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Proceed->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_cost' => '18.50',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasNoErrors();

    // The ledger never learns a currency existed: it holds dinars, like
    // everything else.
    expect(StockBatch::query()->sum('quantity_received'))->toBe(4)
        ->and(StockBatch::query()->firstOrFail()->unit_cost->toDecimal())->toBe('24420.00');
});

it('converts a sale paid in dollars and records that it was', function () {
    $this->post('/sales', [
        'sold_on' => '2026-02-01',
        'customer_id' => Customer::walkIn()->id,
        'status' => SaleStatus::Ordered->value,
        'payment_method' => PaymentMethod::Cash->value,
        'paid_in_full' => true,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => '44.00',
            'discount' => '0',
        ]],
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->with('lines')->firstOrFail();

    expect($sale->lines->first()->unit_price->toDecimal())->toBe('58080.00')
        ->and($sale->currency)->toBe('USD')
        ->and($sale->exchange_rate)->toBe(1_320_000_000);
});

it('converts an expense paid in dollars', function () {
    $category = ExpenseCategory::factory()->create();

    $this->post('/expenses', [
        'expense_category_id' => $category->id,
        'title' => 'Imported display stands',
        'amount' => '250.00',
        'currency' => 'USD',
        'spent_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Cash->value,
        'notes' => null,
    ])->assertSessionHasNoErrors();

    $expense = Expense::query()->firstOrFail();

    expect($expense->amount->toDecimal())->toBe('330000.00')
        ->and($expense->currency)->toBe('USD')
        ->and($expense->isForeignCurrency())->toBeTrue();
});

it('converts a product price typed in dollars', function () {
    $this->post('/products', [
        'name' => 'Imported Voile Panel',
        'description' => null,
        'cost_price' => '6.50',
        'cost_price_currency' => 'USD',
        'selling_price' => '21000',
        'selling_price_currency' => 'IQD',
    ])->assertSessionHasNoErrors();

    $product = Product::query()->where('name', 'Imported Voile Panel')->firstOrFail();

    // One field in dollars, its neighbour in dinars — switching one leaves the
    // other alone.
    expect($product->cost_price->toDecimal())->toBe('8580.00')
        ->and($product->selling_price->toDecimal())->toBe('21000.00');
});

it('converts a back-dated invoice at the rate in force on its own date', function () {
    // The dinar weakened in March. An invoice dated in January must still cost
    // at January's rate, or entering old paperwork rewrites history.
    ExchangeRate::factory()->at('1500')->on('2026-03-01')->create();

    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => '10.00',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->with('lines')->firstOrFail();

    expect($purchase->lines->first()->unit_cost->toDecimal())->toBe('13200.00')
        ->and($purchase->exchange_rate)->toBe(1_320_000_000);
});

it('uses the newer rate for a document dated after it', function () {
    ExchangeRate::factory()->at('1500')->on('2026-03-01')->create();

    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-03-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => '10.00',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasNoErrors();

    expect(Purchase::query()->with('lines')->firstOrFail()->lines->first()->unit_cost->toDecimal())
        ->toBe('15000.00');
});

it('refuses a currency nobody has recorded a rate for', function () {
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'EUR',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => '10.00',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasErrors('currency');

    expect(Purchase::query()->count())->toBe(0);
});

it('refuses a per-field currency nobody has a rate for', function () {
    $this->post('/products', [
        'name' => 'Imported Voile Panel',
        'description' => null,
        'cost_price' => '6.50',
        'cost_price_currency' => 'EUR',
        'selling_price' => '21000',
    ])->assertSessionHasErrors('cost_price_currency');

    expect(Product::query()->where('name', 'Imported Voile Panel')->exists())->toBeFalse();
});

it('converts a quick purchase and a quick sale from the catalogue', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'supplier_id' => $this->supplier->id,
        'quantity' => 5,
        'unit_cost' => '18.00',
        'unit_cost_currency' => 'USD',
        'currency' => 'USD',
        'invoiced_on' => '2026-01-15',
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->with('lines')->firstOrFail();

    expect($purchase->lines->first()->unit_cost->toDecimal())->toBe('23760.00')
        ->and($purchase->currency)->toBe('USD');

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 2,
        'unit_price' => '44.00',
        'unit_price_currency' => 'USD',
        'currency' => 'USD',
        'payment_method' => PaymentMethod::Cash->value,
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->with('lines')->firstOrFail();

    expect($sale->lines->first()->unit_price->toDecimal())->toBe('58080.00')
        ->and($sale->currency)->toBe('USD');
});

it('still validates the amount as typed, in its own currency', function () {
    // Two decimal places is a rule about what was typed, not about what it
    // becomes: $18.005 is not a price anybody can pay.
    $this->post('/purchases', [
        'supplier_id' => $this->supplier->id,
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => null,
        'currency' => 'USD',
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => '18.005',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasErrors('lines.0.unit_cost');
});

it('offers only currencies with a usable rate to the frontend', function () {
    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('currency.base', 'IQD')
        ->where('currency.currencies.0.code', 'IQD')
        ->where('currency.currencies.0.rate', ExchangeRates::SCALE)
        ->where('currency.currencies.1.code', 'USD')
        ->where('currency.currencies.1.rate', 1_320_000_000)
        ->has('currency.currencies', 2)
    );
});

it('drops a currency from the list when it has no rate at all', function () {
    ExchangeRate::query()->delete();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('currency.currencies', 1)
        ->where('currency.currencies.0.code', 'IQD')
    );
});

it('shows figures in the base currency unless a cookie says otherwise', function () {
    $this->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currency.display', 'IQD'));

    // Unencrypted, because the switcher writes it from the browser — which is
    // why `display_currency` is exempt from cookie encryption in bootstrap/app.php.
    $this->withUnencryptedCookie('display_currency', 'USD')
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currency.display', 'USD'));
});

it('ignores a display cookie naming a currency that is not on offer', function () {
    $this->withUnencryptedCookie('display_currency', 'EUR')
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currency.display', 'IQD'));
});
