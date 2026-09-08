<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the append-only stock ledger.
 *
 * Never update or delete one of these. To correct a mistake, write another
 * movement in the opposite direction.
 *
 * @property int $id
 * @property int $product_id
 * @property int $quantity signed: positive receives, negative issues
 * @property StockMovementType $type
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon $occurred_at
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Collection<int, StockBatch> $batches
 * @property-read Collection<int, StockBatchConsumption> $consumptions
 */
#[Fillable(['product_id', 'quantity', 'type', 'occurred_at', 'reason'])]
class StockMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'occurred_at' => 'datetime',
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
     * What caused this movement — a purchase line, a sale line, or nothing at
     * all for a standalone adjustment.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The batches this movement created, if it was a receipt.
     *
     * Usually one. A receipt splits into several when its landed cost does not
     * divide evenly across the units — two at $3.33 and one at $3.34 rather
     * than three at $3.33 and a lost penny.
     *
     * @return HasMany<StockBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class, 'received_movement_id');
    }

    /**
     * What this receipt cost in total, across whatever batches it produced.
     */
    public function receiptCost(): Money
    {
        if (! $this->isReceipt()) {
            return Money::zero();
        }

        $this->loadMissing('batches');

        return Money::sum(
            ...$this->batches->map(
                fn (StockBatch $batch): Money => $batch->unit_cost->multipliedBy($batch->quantity_received)
            )
        );
    }

    /**
     * The batches this movement drew from, if it was an issue.
     *
     * @return HasMany<StockBatchConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(StockBatchConsumption::class);
    }

    public function isReceipt(): bool
    {
        return $this->quantity > 0;
    }

    public function isIssue(): bool
    {
        return $this->quantity < 0;
    }

    /**
     * What this issue actually cost, from the batches it drew on.
     *
     * Exact by construction: it is the sum of each allocation's quantity times
     * that batch's recorded landed cost, never an average.
     */
    public function costOfGoodsSold(): Money
    {
        if (! $this->isIssue()) {
            return Money::zero();
        }

        $this->loadMissing('consumptions.batch');

        return Money::sum(
            ...$this->consumptions->map(
                fn (StockBatchConsumption $consumption): Money => $consumption->cost()
            )
        );
    }
}
