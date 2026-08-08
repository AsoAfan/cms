<?php

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Blackout Eyelet Curtain 117x137',
        'description' => 'Thermal blackout lining.',
        'cost_price' => '24000',
        'selling_price' => '58000',
    ], $overrides);
}

it('shows the product list', function () {
    Product::factory()->create(['name' => 'Voile Panel']);

    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/products/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Voile Panel')
            ->has('suppliers')
            ->has('paymentMethods')
        );
});

it('carries the quantity on hand, derived from the ledger', function () {
    $product = Product::factory()->create();

    app(InventoryService::class)->receive(
        $product,
        7,
        Money::fromDecimal('5.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $this->get('/products')
        ->assertInertia(fn ($page) => $page->where('rows.data.0.quantity', 7));
});

it('shows a never-stocked product as zero rather than blank', function () {
    Product::factory()->create();

    $this->get('/products')
        ->assertInertia(fn ($page) => $page->where('rows.data.0.quantity', 0));
});

it('creates a product', function () {
    $this->post('/products', productPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->name)->toBe('Blackout Eyelet Curtain 117x137')
        ->and($product->description)->toBe('Thermal blackout lining.')
        ->and($product->cost_price->toDecimal())->toBe('24000.00')
        ->and($product->selling_price->toDecimal())->toBe('58000.00');
});

it('requires a name', function () {
    $this->post('/products', ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Product::query()->count())->toBe(0);
});

it('keeps names unique across the catalogue', function () {
    Product::factory()->create(['name' => 'Voile Panel 117 Ivory']);

    $this->post('/products', productPayload(['name' => 'Voile Panel 117 Ivory']))
        ->assertSessionHasErrors('name');

    expect(Product::query()->count())->toBe(1);
});

it('lets a product keep its own name when updated', function () {
    $product = Product::factory()->create(['name' => 'Voile Panel']);

    $this->put("/products/{$product->id}", productPayload([
        'name' => 'Voile Panel',
        'selling_price' => '68500',
    ]))->assertSessionHasNoErrors();

    expect($product->fresh()->selling_price->toDecimal())->toBe('68500.00');
});

it('refuses a duplicate name at the database level too', function () {
    Product::factory()->create(['name' => 'Voile Panel']);

    expect(fn () => Product::factory()->create(['name' => 'Voile Panel']))
        ->toThrow(QueryException::class);
});

it('rejects a price with more precision than a cent', function (string $field) {
    $this->post('/products', productPayload([$field => '18000.005']))
        ->assertSessionHasErrors($field);
})->with(['cost_price', 'selling_price']);

it('rejects a negative price', function () {
    $this->post('/products', productPayload(['selling_price' => '-1.00']))
        ->assertSessionHasErrors('selling_price');
});

it('insists on both a cost and a selling price', function (string $field) {
    $this->post('/products', productPayload([$field => '']))
        ->assertSessionHasErrors($field);

    expect(Product::query()->count())->toBe(0);
})->with(['cost_price', 'selling_price']);

it('refuses a selling price of nothing', function () {
    $this->post('/products', productPayload(['selling_price' => '0']))
        ->assertSessionHasErrors('selling_price');
});

it('allows a cost of nothing, because a free sample really is free', function () {
    $this->post('/products', productPayload(['cost_price' => '0']))
        ->assertSessionHasNoErrors();

    expect(Product::query()->firstOrFail()->cost_price->isZero())->toBeTrue();
});

it('stores prices as integer minor units', function () {
    $this->post('/products', productPayload(['cost_price' => '12.34']));

    expect(DB::table('products')->value('cost_price'))->toBe(1234);
});

it('updates a product', function () {
    $product = Product::factory()->create();

    $this->put("/products/{$product->id}", productPayload([
        'name' => 'Premium Blackout',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Premium Blackout');
});

it('searches by name and by description', function () {
    Product::factory()->create([
        'name' => 'Blackout Eyelet Curtain',
        'description' => 'Thermal lining.',
    ]);
    Product::factory()->create([
        'name' => 'Curtain Hook Pack',
        'description' => 'Fifty per bag.',
    ]);

    $this->get('/products?search=blackout')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Blackout Eyelet Curtain'));

    $this->get('/products?search=fifty')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Curtain Hook Pack'));
});

it('sorts by price', function () {
    Product::factory()->create(['name' => 'Cheap', 'selling_price' => '5000']);
    Product::factory()->create(['name' => 'Dear', 'selling_price' => '90000']);

    $this->get('/products?sort=selling_price&direction=desc')
        ->assertInertia(fn ($page) => $page->where('rows.data.0.name', 'Dear'));
});

it('sorts by quantity on hand, which no column stores', function () {
    $empty = Product::factory()->create(['name' => 'Nothing on the shelf']);
    $stocked = Product::factory()->create(['name' => 'Plenty on the shelf']);

    app(InventoryService::class)->receive(
        $stocked,
        12,
        Money::fromDecimal('5.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $this->get('/products?sort=quantity&direction=desc')
        ->assertInertia(fn ($page) => $page
            ->where('rows.data.0.name', $stocked->name)
            ->where('rows.data.0.quantity', 12)
            // A product that never moved still has to appear, as a zero.
            ->where('rows.data.1.name', $empty->name)
            ->where('rows.data.1.quantity', 0)
        );
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    $this->delete("/products/{$product->id}")->assertRedirect('/products');

    expect(Product::query()->count())->toBe(0);
});

it('refuses to delete a product that has stock history', function () {
    $product = Product::factory()->create();

    app(InventoryService::class)->receive(
        $product,
        5,
        Money::fromDecimal('5.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    // The ledger must never describe a product that is gone. The screen says
    // so rather than letting the foreign key surface as a 500.
    $this->delete("/products/{$product->id}")->assertRedirect();

    expect(Product::query()->count())->toBe(1);
});

it('has no create or edit page — both are drawers over the list', function () {
    $product = Product::factory()->create();

    $this->get('/products/create')->assertNotFound();
    $this->get("/products/{$product->id}/edit")->assertNotFound();
});

it('has no stock screen', function () {
    $this->get('/stock')->assertNotFound();
    $this->post('/stock', [])->assertNotFound();
});

it('keeps guests out of the catalogue', function () {
    auth()->logout();

    $this->get('/products')->assertRedirect('/login');
    $this->post('/products', productPayload())->assertRedirect('/login');
});
