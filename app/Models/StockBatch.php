<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A FIFO layer: a quantity of one product received at one cost.
 *
 * @property int $id
 * @property int $product_id
 * @property int $received_movement_id
 * @property int $quantity_received
 * @property Money $unit_cost landed cost, fixed at receipt
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read StockMovement $receivedMovement
 * @property-read Collection<int, StockBatchConsumption> $consumptions
 */
#[Fillable([
    'product_id',
    'received_movement_id',
    'quantity_received',
    'unit_cost',
    'received_at',
])]
class StockBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => Money::class,
            'received_at' => 'datetime',
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
     * @return BelongsTo<StockMovement, $this>
     */
    public function receivedMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'received_movement_id');
    }

    /**
     * @return HasMany<StockBatchConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(StockBatchConsumption::class);
    }

    /**
     * What is left in this layer — derived, never stored.
     *
     * Uses the `consumptions_sum_quantity` aggregate when the caller loaded it
     * with `withSum`, so a FIFO pass over many batches stays one query.
     *
     * Presence of the aggregate is checked, not its truthiness. A caller that
     * constrained the aggregate — valuation as at a past date, say — gets NULL
     * back when nothing matched, and falling back to an unfiltered sum there
     * would silently ignore the constraint and report the wrong figure.
     */
    public function remainingQuantity(): int
    {
        $consumed = array_key_exists('consumptions_sum_quantity', $this->attributes)
            ? (int) $this->attributes['consumptions_sum_quantity']
            : (int) $this->consumptions()->sum('quantity');

        return $this->quantity_received - $consumed;
    }

    public function remainingValue(): Money
    {
        return $this->unit_cost->multipliedBy($this->remainingQuantity());
    }
}
