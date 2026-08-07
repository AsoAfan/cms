<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

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
        'name' => 'Blackout Eyelet Curtain',
        'description' => 'Thermal blackout lining.',
        'is_active' => true,
        'variants' => [
            [
                'code' => 'BEC-STD',
                'default_cost_price' => '6.00',
                'default_selling_price' => '18.00',
                'is_active' => true,
                'attribute_value_ids' => [],
            ],
        ],
    ], $overrides);
}

/**
 * Width x Drop, as the item matrix builder would produce.
 *
 * @return array{Attribute, Attribute, Collection<int, AttributeValue>, Collection<int, AttributeValue>}
 */
function widthAndDrop(): array
{
    $width = Attribute::factory()->create(['name' => 'Width']);
    $drop = Attribute::factory()->create(['name' => 'Drop']);

    $widths = collect(['117cm', '168cm', '229cm'])->map(
        fn (string $value) => AttributeValue::factory()->for($width, 'attribute')->create(['value' => $value])
    );
    $drops = collect(['137cm', '183cm'])->map(
        fn (string $value) => AttributeValue::factory()->for($drop, 'attribute')->create(['value' => $value])
    );

    return [$width, $drop, $widths, $drops];
}

it('shows the product list', function () {
    Product::factory()->simple()->create(['name' => 'Voile Panel']);

    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/products/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Voile Panel')
        );
});

it('creates a simple product with a single variant', function () {
    $this->post('/products', productPayload())->assertRedirect();

    $product = Product::query()->firstOrFail();

    expect($product->name)->toBe('Blackout Eyelet Curtain')
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->code)->toBe('BEC-STD')
        ->and($product->variants->first()->default_selling_price->toDecimal())->toBe('18.00');
});

it('creates a product with three variants across two attributes', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload([
        'variants' => [
            ['code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
            ['code' => 'BEC-168-137', 'attribute_value_ids' => [$widths[1]->id, $drops[0]->id]],
            ['code' => 'BEC-229-183', 'attribute_value_ids' => [$widths[2]->id, $drops[1]->id]],
        ],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $product = Product::query()->with('variants.attributeValues.attribute')->firstOrFail();

    expect($product->variants)->toHaveCount(3)
        ->and($product->variants->pluck('code')->all())->toBe(['BEC-117-137', 'BEC-168-137', 'BEC-229-183'])
        ->and($product->attributesInUse()->pluck('name')->all())->toBe(['Width', 'Drop'])
        ->and($product->variants->first()->optionLabel())->toBe('117cm / 137cm');
});

it('finds a product by its SKU', function () {
    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-117-137', 'attribute_value_ids' => []],
    ]]));

    $this->get('/products?code=117')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Blackout Eyelet Curtain'));

    $this->get('/products?code=NOPE')
        ->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

it('rejects a product with no variants', function () {
    $this->post('/products', productPayload(['variants' => []]))
        ->assertSessionHasErrors('variants');

    expect(Product::query()->count())->toBe(0);
});

it('rejects a duplicate SKU within the same submission', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'SAME', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => 'SAME', 'attribute_value_ids' => [$widths[1]->id, $drops[0]->id]],
    ]]))->assertSessionHasErrors('variants.0.code');

    expect(Product::query()->count())->toBe(0);
});

it('rejects two attribute-less variants, which would be indistinguishable', function () {
    $this->post('/products', productPayload(['variants' => [
        ['code' => 'ONE', 'attribute_value_ids' => []],
        ['code' => 'TWO', 'attribute_value_ids' => []],
    ]]))->assertSessionHasErrors('variants.1.attribute_value_ids');

    expect(Product::query()->count())->toBe(0);
});

it('rejects a SKU already used by another product', function () {
    ProductVariant::factory()->create(['code' => 'TAKEN']);

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'TAKEN', 'attribute_value_ids' => []],
    ]]))->assertSessionHasErrors('variants.0.code');
});

it('rejects a variant taking two values of the same attribute', function () {
    [$width, $drop, $widths] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-X', 'attribute_value_ids' => [$widths[0]->id, $widths[1]->id]],
    ]]))->assertSessionHasErrors('variants.0.attribute_value_ids');

    expect(Product::query()->count())->toBe(0);
});

it('rejects two variants with the same combination of options', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-A', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => 'BEC-B', 'attribute_value_ids' => [$drops[0]->id, $widths[0]->id]],
    ]]))->assertSessionHasErrors('variants.1.attribute_value_ids');
});

it('rejects variants that vary along different attributes', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-A', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => 'BEC-B', 'attribute_value_ids' => [$widths[1]->id]],
    ]]))->assertSessionHasErrors();

    expect(Product::query()->count())->toBe(0);
});

it('rejects a price with more precision than a cent', function () {
    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-X', 'default_selling_price' => '18.005', 'attribute_value_ids' => []],
    ]]))->assertSessionHasErrors('variants.0.default_selling_price');
});

it('accepts a variant with no price at all', function () {
    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-X', 'default_cost_price' => null, 'default_selling_price' => null, 'attribute_value_ids' => []],
    ]]))->assertSessionHasNoErrors();

    expect(ProductVariant::query()->firstOrFail()->default_cost_price)->toBeNull();
});

it('writes nothing when any variant fails validation', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'GOOD', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => '', 'attribute_value_ids' => [$widths[1]->id, $drops[0]->id]],
    ]]))->assertSessionHasErrors();

    expect(Product::query()->count())->toBe(0)
        ->and(ProductVariant::query()->count())->toBe(0);
});

it('shows the edit screen with the product and its options', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
    ]]));

    $product = Product::query()->firstOrFail();

    $this->get("/products/{$product->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/products/edit')
            ->where('product.name', 'Blackout Eyelet Curtain')
            ->has('product.variants', 1)
            ->where('product.variants.0.label', '117cm / 137cm')
            ->has('attributes', 2)
        );
});

it('updates a product and reprices a variant', function () {
    $this->post('/products', productPayload());
    $product = Product::query()->with('variants')->firstOrFail();
    $variant = $product->variants->first();

    $this->put("/products/{$product->id}", productPayload([
        'name' => 'Premium Blackout Curtain',
        'variants' => [[
            'id' => $variant->id,
            'code' => 'BEC-STD',
            'default_cost_price' => '7.50',
            'default_selling_price' => '22.00',
            'attribute_value_ids' => [],
        ]],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Premium Blackout Curtain')
        ->and($variant->fresh()->default_selling_price->toDecimal())->toBe('22.00')
        ->and(ProductVariant::query()->count())->toBe(1);
});

it('adds and removes variants on update', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => 'BEC-168-137', 'attribute_value_ids' => [$widths[1]->id, $drops[0]->id]],
    ]]));

    $product = Product::query()->with('variants')->firstOrFail();
    $kept = $product->variants->firstWhere('code', 'BEC-117-137');

    $this->put("/products/{$product->id}", productPayload(['variants' => [
        ['id' => $kept->id, 'code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
        ['code' => 'BEC-229-183', 'attribute_value_ids' => [$widths[2]->id, $drops[1]->id]],
    ]]))->assertSessionHasNoErrors();

    expect($product->fresh()->variants->pluck('code')->sort()->values()->all())
        ->toBe(['BEC-117-137', 'BEC-229-183']);
});

it('leaves an existing variant\'s options alone when they are resubmitted differently', function () {
    [$width, $drop, $widths, $drops] = widthAndDrop();

    $this->post('/products', productPayload(['variants' => [
        ['code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[0]->id, $drops[0]->id]],
    ]]));

    $product = Product::query()->with('variants')->firstOrFail();
    $variant = $product->variants->first();

    // What a SKU *is* is fixed at creation; only its SKU text, prices and
    // active flag can change afterwards.
    $this->put("/products/{$product->id}", productPayload(['variants' => [
        ['id' => $variant->id, 'code' => 'BEC-117-137', 'attribute_value_ids' => [$widths[1]->id, $drops[1]->id]],
    ]]))->assertSessionHasNoErrors();

    expect($variant->fresh()->load('attributeValues.attribute')->optionLabel())->toBe('117cm / 137cm');
});

it('filters the list by status', function () {
    Product::factory()->simple()->create(['name' => 'Current']);
    Product::factory()->simple()->inactive()->create(['name' => 'Archived']);

    $this->get('/products?status=inactive')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Archived'));

    $this->get('/products?status=active')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Current'));
});

it('searches products by name', function () {
    Product::factory()->simple()->create(['name' => 'Voile Panel']);
    Product::factory()->simple()->create(['name' => 'Curtain Hook Pack']);

    $this->get('/products?search=voile')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Voile Panel'));
});

it('deletes a product and its variants', function () {
    $product = Product::factory()->simple()->create();

    $this->delete("/products/{$product->id}")->assertRedirect('/products');

    expect(Product::query()->count())->toBe(0)
        ->and(ProductVariant::query()->count())->toBe(0);
});

it('keeps guests out of the catalogue', function () {
    auth()->logout();

    $this->get('/products')->assertRedirect('/login');
    $this->post('/products', productPayload())->assertRedirect('/login');
});
