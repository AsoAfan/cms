<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists attributes with their options', function () {
    $colour = Attribute::factory()->create(['name' => 'Width']);
    AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '117cm']);

    $this->get('/attributes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/attributes/index')
            ->has('rows.data', 1)
            ->has('rows.data.0.values', 1)
            ->where('rows.data.0.values.0.value', '117cm')
        );
});

it('creates an attribute with its options', function () {
    $this->post('/attributes', ['name' => 'Width', 'values' => ['117cm', '168cm']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $attribute = Attribute::query()->with('values')->firstOrFail();

    expect($attribute->name)->toBe('Width')
        ->and($attribute->values->pluck('value')->all())->toBe(['117cm', '168cm']);
});

it('requires at least one option', function () {
    $this->post('/attributes', ['name' => 'Width', 'values' => []])->assertSessionHasErrors('values');

    expect(Attribute::query()->count())->toBe(0);
});

it('rejects duplicate options in one submission', function () {
    $this->post('/attributes', ['name' => 'Width', 'values' => ['117cm', '117CM']])
        ->assertSessionHasErrors('values.0');
});

it('rejects a duplicate attribute name', function () {
    Attribute::factory()->create(['name' => 'Width']);

    $this->post('/attributes', ['name' => 'Width', 'values' => ['117cm']])->assertSessionHasErrors('name');
});

it('adds and removes options on update', function () {
    $colour = Attribute::factory()->create(['name' => 'Width']);
    AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '117cm']);
    AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '168cm']);

    $this->put("/attributes/{$colour->id}", ['name' => 'Width', 'values' => ['117cm', '229cm']])
        ->assertSessionHasNoErrors();

    expect($colour->fresh()->values->pluck('value')->sort()->values()->all())->toBe(['117cm', '229cm']);
});

it('keeps the id of an option that survives an update', function () {
    $colour = Attribute::factory()->create(['name' => 'Width']);
    $black = AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '117cm']);

    $this->put("/attributes/{$colour->id}", ['name' => 'Width', 'values' => ['117cm', '229cm']]);

    expect(AttributeValue::query()->whereKey($black->id)->value('value'))->toBe('117cm');
});

it('refuses to remove an option a variant still carries', function () {
    $colour = Attribute::factory()->create(['name' => 'Width']);
    $black = AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '117cm']);
    AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '168cm']);

    ProductVariant::factory()->create()->attributeValues()->attach($black->id, ['attribute_id' => $colour->id]);

    $this->put("/attributes/{$colour->id}", ['name' => 'Width', 'values' => ['168cm']])
        ->assertSessionHasErrors('values');

    expect(AttributeValue::query()->whereKey($black->id)->exists())->toBeTrue();
});

it('renames an attribute without touching its options', function () {
    $colour = Attribute::factory()->create(['name' => 'Width']);
    $black = AttributeValue::factory()->for($colour, 'attribute')->create(['value' => '117cm']);

    ProductVariant::factory()->create()->attributeValues()->attach($black->id, ['attribute_id' => $colour->id]);

    $this->put("/attributes/{$colour->id}", ['name' => 'Widths', 'values' => ['117cm']])
        ->assertSessionHasNoErrors();

    expect($colour->fresh()->name)->toBe('Widths')
        ->and(AttributeValue::query()->whereKey($black->id)->exists())->toBeTrue();
});

it('deletes an unused attribute and its options', function () {
    $colour = Attribute::factory()->create();
    AttributeValue::factory()->for($colour, 'attribute')->create();

    $this->delete("/attributes/{$colour->id}")->assertRedirect();

    expect(Attribute::query()->count())->toBe(0)
        ->and(AttributeValue::query()->count())->toBe(0);
});

it('refuses to delete an attribute a variant depends on', function () {
    $colour = Attribute::factory()->create();
    $black = AttributeValue::factory()->for($colour, 'attribute')->create();

    ProductVariant::factory()->create()->attributeValues()->attach($black->id, ['attribute_id' => $colour->id]);

    $this->delete("/attributes/{$colour->id}")->assertRedirect();

    expect(Attribute::query()->count())->toBe(1);
});
