<?php

namespace App\Actions\Catalog;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a catalogue entry together with its stock items.
 *
 * Create and update are the same operation here — a product is only ever
 * meaningful alongside the items it is sold as, so the two are written
 * together in one transaction or not at all.
 *
 * An item's options are fixed at creation. Once an item exists, "117cm" is
 * part of what that item *is*; letting it become "168cm" would silently
 * rewrite the meaning of every purchase and sale already recorded against it.
 * Recoding or repricing is fine; changing what it is means a new item.
 */
final class SaveProductAction
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     variants: array<int, array{
     *         id?: int|null,
     *         code: string,
     *         default_cost_price?: string|int|null,
     *         default_selling_price?: string|int|null,
     *         is_active?: bool,
     *         attribute_value_ids?: array<int, int|string>,
     *     }>,
     * }  $data
     */
    public function handle(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product): Product {
            $product ??= new Product;

            $product->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ])->save();

            $this->syncVariants($product, $data['variants']);

            return $product->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach ($variants as $payload) {
            $variant = $this->saveVariant($product, $payload);
            $keptIds[] = $variant->id;
        }

        // Items dropped from the form are removed. The foreign keys added in
        // Phase 3+ will refuse this for any item with stock history, so
        // history can never be orphaned by an edit here.
        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveVariant(Product $product, array $payload): ProductVariant
    {
        $existingId = $payload['id'] ?? null;

        /** @var ProductVariant|null $variant */
        $variant = $existingId === null
            ? null
            : $product->variants()->whereKey($existingId)->first();

        $isNew = $variant === null;
        $variant ??= $product->variants()->make();

        $variant->fill([
            'code' => $payload['code'],
            'default_cost_price' => $this->money($payload['default_cost_price'] ?? null),
            'default_selling_price' => $this->money($payload['default_selling_price'] ?? null),
            'is_active' => $payload['is_active'] ?? true,
        ])->save();

        if ($isNew) {
            $this->attachAttributeValues($variant, (array) ($payload['attribute_value_ids'] ?? []));
        }

        return $variant;
    }

    /**
     * @param  array<int, int|string>  $valueIds
     */
    private function attachAttributeValues(ProductVariant $variant, array $valueIds): void
    {
        if ($valueIds === []) {
            return;
        }

        $values = AttributeValue::query()->findMany($valueIds);

        // The pivot carries attribute_id so the database can enforce one value
        // per option per item; it is derived here, never trusted from the
        // request.
        $variant->attributeValues()->attach(
            $values->mapWithKeys(fn (AttributeValue $value): array => [
                $value->id => ['attribute_id' => $value->attribute_id],
            ])->all()
        );
    }

    private function money(string|int|null $amount): ?Money
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return Money::fromDecimal($amount);
    }
}
