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
 * One product on a sale.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $quantity
 * @property Money $unit_price
 * @property Money $discount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read Product $product
 * @property-read Collection<int, StockMovement> $stockMovements
 */
#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_price', 'discount'])]
class SaleLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => Money::class,
            'discount' => Money::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The ledger entries this line produced when the sale was posted.
     *
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function grossTotal(): Money
    {
        return $this->unit_price->multipliedBy($this->quantity);
    }

    /**
     * What was charged for this line.
     */
    public function netTotal(): Money
    {
        return $this->grossTotal()->minus($this->discount);
    }

    /**
     * What these goods cost, read back off the ledger.
     *
     * Derived rather than stored: the batches consumed are recorded exactly,
     * so this can always be replayed and can never disagree with the ledger.
     */
    public function costOfGoodsSold(): Money
    {
        $this->loadMissing('stockMovements.consumptions.batch');

        return Money::sum(
            ...$this->stockMovements->map(
                fn (StockMovement $movement): Money => $movement->costOfGoodsSold()
            )
        );
    }

    /**
     * What was made on this line.
     */
    public function grossProfit(): Money
    {
        return $this->netTotal()->minus($this->costOfGoodsSold());
    }
}
