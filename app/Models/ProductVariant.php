<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A stock item. Purchases, sales and the stock ledger all reference this,
 * never the product, so a "simple" product is just a product with one item.
 *
 * @property int $id
 * @property int $product_id
 * @property string $code
 * @property Money|null $default_cost_price
 * @property Money|null $default_selling_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Collection<int, AttributeValue> $attributeValues
 */
#[Fillable(['product_id', 'code', 'default_cost_price', 'default_selling_price', 'is_active'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_cost_price' => Money::class,
            'default_selling_price' => Money::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The values that make this variant distinct — "Red", "Large".
     *
     * The pivot carries `attribute_id` so the database can enforce one value
     * per attribute; always supply it when attaching.
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        // Named explicitly: Eloquent would otherwise infer the alphabetical
        // `attribute_value_product_variant`.
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_value')
            ->withPivot('attribute_id');
    }

    /**
     * Human-readable item name — "117cm / 137cm", or the product name when the
     * product has no options at all.
     *
     * Ordered by when each option was created rather than alphabetically:
     * a curtain reads "Width / Drop", and sorting by name would render that
     * backwards.
     */
    public function optionLabel(): string
    {
        $values = $this->attributeValues
            ->sortBy(fn (AttributeValue $value): int => $value->attribute_id)
            ->map(fn (AttributeValue $value): string => $value->value);

        return $values->isEmpty() ? $this->product->name : $values->join(' / ');
    }
}
