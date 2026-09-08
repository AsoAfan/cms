<?php

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;

use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| The loans screen
|--------------------------------------------------------------------------
|
| Both loans in one place: goods sold and never bought on one side, money
| invoiced and never collected on the other. Neither is stored — the screen is
| two derived positions, so it can only ever agree with the documents.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->inventory = app(InventoryService::class);
    $this->customer = Customer::factory()->walkIn()->create();
    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'cost_price' => '18.00',
        'selling_price' => '44.00',
    ]);
});

function stockFor(Product $product, int $quantity, string $unitCost = '18.00'): void
{
    test()->inventory->receive(
        product: $product,
        quantity: $quantity,
        unitCost: Money::fromDecimal($unitCost),
        type: StockMovementType::Purchase,
        occurredAt: Carbon::parse('2026-01-01'),
    );
}

/**
 * @param  list<array{product_id: int, quantity: int}>  $lines
 * @param  array<string, mixed>  $overrides
 */
function sellTo(array $lines, array $overrides = []): Sale
{
    test()->post('/sales', array_merge([
        'customer_id' => test()->customer->id,
        'sold_on' => '2026-02-01',
        'status' => SaleStatus::Ordered->value,
        'payment_method' => PaymentMethod::Cash->value,
        'paid_in_full' => true,
        'notes' => null,
        'lines' => array_map(static fn (array $line): array => [
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
            'unit_price' => '44.00',
            'discount' => '0',
        ], $lines),
    ], $overrides))->assertSessionHasNoErrors();

    return Sale::query()->latest('id')->firstOrFail();
}

it('sends guests to the login screen', function () {
    auth()->logout();

    get('/loans')->assertRedirect('/login');
});

it('lists what is owed in goods, with the orders waiting on it', function () {
    stockFor($this->product, 1);

    $first = sellTo([['product_id' => $this->product->id, 'quantity' => 3]]);
    $second = sellTo([['product_id' => $this->product->id, 'quantity' => 2]]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->component('loans/index')
        ->has('goods.rows', 1)
        ->where('goods.rows.0.product', 'Blackout 117x137')
        ->where('goods.rows.0.quantity', 5)
        ->where('goods.rows.0.on_hand', 1)
        // Four to buy between the two orders, not three and one: buying what
        // each invoice is short of separately under-buys every time.
        ->where('goods.rows.0.short', 4)
        ->where('goods.rows.0.value', 7200)
        ->where('goods.items', 4)
        ->where('goods.value', 7200)
        // Oldest order first — who has been waiting longest.
        ->where('goods.rows.0.sales', [
            ['id' => $first->id, 'number' => $first->number],
            ['id' => $second->id, 'number' => $second->number],
        ])
    );
});

it('drops a product off the goods side once its stock is bought in', function () {
    sellTo([['product_id' => $this->product->id, 'quantity' => 3]]);

    stockFor($this->product, 3);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('goods.rows', 0)
        ->where('goods.items', 0)
        ->where('goods.value', 0)
    );
});

it('owes nothing in goods for a sale that has already gone out', function () {
    stockFor($this->product, 5);

    $sale = sellTo([['product_id' => $this->product->id, 'quantity' => 5]]);

    $this->post("/sales/{$sale->id}/status", ['status' => SaleStatus::OnTheWay->value]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('goods.rows', 0)
        ->where('goods.value', 0)
    );
});

it('lists what each customer owes, biggest first', function () {
    stockFor($this->product, 10);

    $small = Customer::factory()->create(['name' => 'Ahmed']);
    $large = Customer::factory()->create(['name' => 'Dara']);

    // Delivered and part paid: 132.00 invoiced, 32.00 handed over.
    sellTo([['product_id' => $this->product->id, 'quantity' => 3]], [
        'customer_id' => $small->id,
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '32.00',
    ]);

    // 220.00 invoiced, nothing handed over.
    sellTo([['product_id' => $this->product->id, 'quantity' => 5]], [
        'customer_id' => $large->id,
        'status' => SaleStatus::Proceed->value,
        'paid_in_full' => false,
        'amount_paid' => '0',
    ]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('customers.rows', 2)
        ->where('customers.rows.0.name', 'Dara')
        ->where('customers.rows.0.balance', 22000)
        ->where('customers.rows.1.name', 'Ahmed')
        ->where('customers.rows.1.balance', 10000)
        ->where('customers.total', 32000)
    );
});

it('leaves an undelivered order off the money side', function () {
    stockFor($this->product, 10);

    // Sitting at `ordered` and unpaid. Nothing is owed until the customer has
    // the goods — money on one before then is a deposit.
    sellTo([['product_id' => $this->product->id, 'quantity' => 3]], [
        'paid_in_full' => false,
        'amount_paid' => '0',
    ]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('customers.rows', 0)
        ->where('customers.total', 0)
    );
});

it('shows both sides empty on a clean set of books', function () {
    stockFor($this->product, 10);

    sellTo([['product_id' => $this->product->id, 'quantity' => 3]], [
        'status' => SaleStatus::Proceed->value,
    ]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('goods.rows', 0)
        ->has('customers.rows', 0)
        ->where('goods.value', 0)
        ->where('customers.total', 0)
    );
});

/*
|--------------------------------------------------------------------------
| Ordering what is owed
|--------------------------------------------------------------------------
|
| The list says what has to be bought, so it opens the purchase drawer already
| holding that order. The screen serves the same options the purchases screen
| does — a drawer offering different products depending on where it was opened
| from would be two forms wearing one name.
|
*/

it('carries everything the purchase drawer needs to open', function () {
    sellTo([['product_id' => $this->product->id, 'quantity' => 3]]);

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('products', 1)
        ->where('products.0.id', $this->product->id)
        // The cost the drawer fills the line in at, in the base currency.
        ->where('products.0.cost_price', '18.00')
        ->has('allocationMethods')
        ->has('statuses')
        ->has('paymentMethods')
        ->has('banks')
        ->where('nextNumber', 'PUR-00001')
    );
});

it('records the order the loans screen opened, and that clears the loan', function () {
    stockFor($this->product, 1);

    sellTo([['product_id' => $this->product->id, 'quantity' => 5]]);

    // Four short — which is the quantity the drawer opens holding.
    $this->get('/loans')->assertInertia(fn ($page) => $page->where('goods.rows.0.short', 4));

    // The invoice as the drawer would post it back, reviewed and saved.
    $this->post('/purchases', [
        'number' => 'PUR-00001',
        'invoiced_on' => '2026-02-02',
        'status' => PurchaseStatus::Proceed->value,
        'payment_method' => PaymentMethod::Cash->value,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_cost' => '18.00',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ])->assertSessionHasNoErrors();

    $this->get('/loans')->assertInertia(fn ($page) => $page
        ->has('goods.rows', 0)
        ->where('goods.value', 0)
    );
});
