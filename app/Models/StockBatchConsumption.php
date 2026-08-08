<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One FIFO allocation: this issue took this much from this batch.
 *
 * @property int $id
 * @property int $stock_batch_id
 * @property int $stock_movement_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StockBatch $batch
 * @property-read StockMovement $movement
 */
#[Fillable(['stock_batch_id', 'stock_movement_id', 'quantity'])]
class StockBatchConsumption extends Model
{
    /**
     * @return BelongsTo<StockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /**
     * What this allocation cost: quantity at the batch's landed unit cost.
     */
    public function cost(): Money
    {
        return $this->batch->unit_cost->multipliedBy($this->quantity);
    }
}
