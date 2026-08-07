<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * The catalogue leans on the database to keep variants coherent rather than
 * trusting every future caller to remember the rules. These prove the
 * constraints are actually enforced, not merely declared.
 */

it('refuses an item holding two values of the same option', function () {
    $width = Attribute::factory()->create(['name' => 'Width']);
    $w117 = AttributeValue::factory()->for($width, 'attribute')->create(['value' => '117cm']);
    $w168 = AttributeValue::factory()->for($width, 'attribute')->create(['value' => '168cm']);

    $variant = ProductVariant::factory()->create();

    $variant->attributeValues()->attach($w117->id, ['attribute_id' => $width->id]);

    expect(fn () => $variant->attributeValues()->attach($w168->id, ['attribute_id' => $width->id]))
        ->toThrow(QueryException::class);
});

it('refuses an option value that belongs to a different option', function () {
    $width = Attribute::factory()->create(['name' => 'Width']);
    $drop = Attribute::factory()->create(['name' => 'Drop']);
    $d137 = AttributeValue::factory()->for($drop, 'attribute')->create(['value' => '137cm']);

    $variant = ProductVariant::factory()->create();

    // Claiming "137cm" is a Width must not be storable — this is what the
    // composite foreign key exists to stop.
    expect(fn () => $variant->attributeValues()->attach($d137->id, ['attribute_id' => $width->id]))
        ->toThrow(QueryException::class);
});

it('accepts one value per option', function () {
    $width = Attribute::factory()->create(['name' => 'Width']);
    $drop = Attribute::factory()->create(['name' => 'Drop']);
    $w117 = AttributeValue::factory()->for($width, 'attribute')->create(['value' => '117cm']);
    $d137 = AttributeValue::factory()->for($drop, 'attribute')->create(['value' => '137cm']);

    $variant = ProductVariant::factory()->create();

    $variant->attributeValues()->attach([
        $w117->id => ['attribute_id' => $width->id],
        $d137->id => ['attribute_id' => $drop->id],
    ]);

    expect($variant->attributeValues()->count())->toBe(2)
        ->and($variant->load('attributeValues.attribute')->optionLabel())->toBe('117cm / 137cm');
});

it('keeps item codes unique across the whole catalogue', function () {
    ProductVariant::factory()->create(['code' => 'BEC-117-137']);

    expect(fn () => ProductVariant::factory()->create(['code' => 'BEC-117-137']))
        ->toThrow(QueryException::class);
});

it('removes a product\'s items along with the product', function () {
    $product = Product::factory()->simple()->create();

    expect(ProductVariant::query()->where('product_id', $product->id)->exists())->toBeTrue();

    $product->delete();

    expect(ProductVariant::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('refuses to delete an option value an item depends on', function () {
    $width = Attribute::factory()->create();
    $w117 = AttributeValue::factory()->for($width, 'attribute')->create();
    $variant = ProductVariant::factory()->create();

    $variant->attributeValues()->attach($w117->id, ['attribute_id' => $width->id]);

    expect(fn () => $w117->delete())->toThrow(QueryException::class);
});

it('stores item prices as integer minor units', function () {
    $variant = ProductVariant::factory()->create([
        'default_cost_price' => '12.34',
        'default_selling_price' => '19.99',
    ]);

    expect(DB::table('product_variants')->where('id', $variant->id)->value('default_cost_price'))->toBe(1234)
        ->and($variant->fresh()->default_selling_price->toDecimal())->toBe('19.99');
});

it('treats an unpriced item as null rather than zero', function () {
    $variant = ProductVariant::factory()->unpriced()->create();

    expect($variant->default_cost_price)->toBeNull()
        ->and($variant->default_selling_price)->toBeNull();
});
