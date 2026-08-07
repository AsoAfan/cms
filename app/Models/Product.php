<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The catalogue entry a customer recognises. Price and stock live on the item,
 * which is the real stock-keeping entity.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProductVariant> $variants
 */
#[Fillable(['name', 'description', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The stock items this product is sold as. Called "variants" in the schema
     * for Eloquent's benefit; the UI calls them items.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The options this product varies along, derived from its items rather
     * than stored — the items are the single source of truth.
     *
     * @return SupportCollection<int, Attribute>
     */
    public function attributesInUse(): SupportCollection
    {
        return $this->variants
            ->flatMap(fn (ProductVariant $variant): Collection => $variant->attributeValues)
            ->map(fn (AttributeValue $value): Attribute => $value->attribute)
            ->unique('id')
            ->sortBy('id')
            ->values();
    }
}
