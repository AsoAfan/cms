<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One product on a supplier invoice.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property int $quantity
 * @property Money $unit_cost
 * @property Money $discount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purchase $purchase
 * @property-read Product $product
 * @property-read Collection<int, StockMovement> $stockMovements
 */
#[Fillable(['purchase_id', 'product_id', 'quantity', 'unit_cost', 'discount'])]
class PurchaseLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => Money::class,
            'discount' => Money::class,
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The ledger entries this line produced when the invoice was posted.
     *
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    /**
     * What the supplier charged for this line before the discount.
     */
    public function grossTotal(): Money
    {
        return $this->unit_cost->multipliedBy($this->quantity);
    }

    /**
     * What this line comes to, before invoice-wide costs are spread over it.
     */
    public function netTotal(): Money
    {
        return $this->grossTotal()->minus($this->discount);
    }
}
