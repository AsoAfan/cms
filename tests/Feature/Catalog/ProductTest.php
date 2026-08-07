<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        'code' => 'BEC-117-137',
        'description' => 'Thermal blackout lining.',
        'default_cost_price' => '18.00',
        'default_selling_price' => '44.00',
        'is_active' => true,
    ], $overrides);
}

it('shows the product list', function () {
    Product::factory()->create(['name' => 'Voile Panel', 'code' => 'VOI-117']);

    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/products/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Voile Panel')
            ->where('rows.data.0.code', 'VOI-117')
        );
});

it('creates a product', function () {
    $this->post('/products', productPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->name)->toBe('Blackout Eyelet Curtain 117x137')
        ->and($product->code)->toBe('BEC-117-137')
        ->and($product->default_cost_price->toDecimal())->toBe('18.00')
        ->and($product->default_selling_price->toDecimal())->toBe('44.00')
        ->and($product->is_active)->toBeTrue();
});

it('requires a name and a code', function () {
    $this->post('/products', ['name' => '', 'code' => ''])
        ->assertSessionHasErrors(['name', 'code']);

    expect(Product::query()->count())->toBe(0);
});

it('keeps codes unique across the catalogue', function () {
    Product::factory()->create(['code' => 'TAKEN']);

    $this->post('/products', productPayload(['code' => 'TAKEN']))
        ->assertSessionHasErrors('code');

    expect(Product::query()->count())->toBe(1);
});

it('lets a product keep its own code when updated', function () {
    $product = Product::factory()->create(['code' => 'BEC-117-137']);

    $this->put("/products/{$product->id}", productPayload([
        'code' => 'BEC-117-137',
        'name' => 'Renamed',
    ]))->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Renamed');
});

it('rejects a price with more precision than a cent', function (string $field) {
    $this->post('/products', productPayload([$field => '18.005']))
        ->assertSessionHasErrors($field);
})->with(['default_cost_price', 'default_selling_price']);

it('rejects a negative price', function () {
    $this->post('/products', productPayload(['default_selling_price' => '-1.00']))
        ->assertSessionHasErrors('default_selling_price');
});

it('accepts a product with no prices at all', function () {
    $this->post('/products', productPayload([
        'default_cost_price' => '',
        'default_selling_price' => '',
    ]))->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->default_cost_price)->toBeNull()
        ->and($product->default_selling_price)->toBeNull();
});

it('stores prices as integer minor units', function () {
    $this->post('/products', productPayload(['default_cost_price' => '12.34']));

    expect(DB::table('products')->value('default_cost_price'))->toBe(1234);
});

it('shows the edit screen', function () {
    $product = Product::factory()->create(['name' => 'Voile Panel']);

    $this->get("/products/{$product->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/products/edit')
            ->where('product.name', 'Voile Panel')
            ->where('product.code', $product->code)
            ->where('product.default_cost_price', $product->default_cost_price->toDecimal())
        );
});

it('updates a product', function () {
    $product = Product::factory()->create();

    $this->put("/products/{$product->id}", productPayload([
        'name' => 'Premium Blackout',
        'code' => $product->code,
        'default_selling_price' => '52.00',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Premium Blackout')
        ->and($product->fresh()->default_selling_price->toDecimal())->toBe('52.00');
});

it('searches by name and by code', function () {
    Product::factory()->create(['name' => 'Blackout Eyelet Curtain', 'code' => 'BEC-117-137']);
    Product::factory()->create(['name' => 'Curtain Hook Pack', 'code' => 'HOOK-50']);

    $this->get('/products?search=blackout')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.code', 'BEC-117-137'));

    $this->get('/products?search=hook-50')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Curtain Hook Pack'));
});

it('filters the list by status', function () {
    Product::factory()->create(['name' => 'Current']);
    Product::factory()->inactive()->create(['name' => 'Archived']);

    $this->get('/products?status=inactive')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Archived'));

    $this->get('/products?status=active')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Current'));
});

it('sorts by price', function () {
    Product::factory()->create(['name' => 'Cheap', 'default_selling_price' => '5.00']);
    Product::factory()->create(['name' => 'Dear', 'default_selling_price' => '90.00']);

    $this->get('/products?sort=default_selling_price&direction=desc')
        ->assertInertia(fn ($page) => $page->where('rows.data.0.name', 'Dear'));
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    $this->delete("/products/{$product->id}")->assertRedirect('/products');

    expect(Product::query()->count())->toBe(0);
});

it('refuses a duplicate code at the database level too', function () {
    Product::factory()->create(['code' => 'BEC-117-137']);

    expect(fn () => Product::factory()->create(['code' => 'BEC-117-137']))
        ->toThrow(QueryException::class);
});

it('keeps guests out of the catalogue', function () {
    auth()->logout();

    $this->get('/products')->assertRedirect('/login');
    $this->post('/products', productPayload())->assertRedirect('/login');
});
