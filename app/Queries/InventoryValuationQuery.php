<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\StockBatch;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the stock on hand is worth, at cost.
 *
 * Valuation follows the same FIFO layers COGS does: what remains is whatever
 * the most recent batches still hold, each at the cost actually paid for it.
 * Asking "as at" a past date rewinds both the receipts and the consumptions,
 * so a valuation taken today for last month agrees with the accounts then.
 */
final class InventoryValuationQuery
{
    public function forProduct(Product $product, ?DateTimeInterface $asAt = null): Money
    {
        return Money::sum(
            ...$this->remainingBatches($asAt)
                ->where('product_id', $product->id)
                ->get()
                ->map(fn (StockBatch $batch): Money => $batch->remainingValue())
        );
    }

    /**
     * Value on hand per product, keyed by product id.
     *
     * @return Collection<int, Money>
     */
    public function get(?DateTimeInterface $asAt = null): Collection
    {
        return $this->remainingBatches($asAt)
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $batches): Money => Money::sum(
                ...$batches->map(fn (StockBatch $batch): Money => $batch->remainingValue())
            ));
    }

    public function total(?DateTimeInterface $asAt = null): Money
    {
        return Money::sum(...$this->get($asAt)->values());
    }

    /**
     * Batches that had been received by the date, carrying only the
     * consumptions that had happened by then.
     *
     * @return Builder<StockBatch>
     */
    private function remainingBatches(?DateTimeInterface $asAt)
    {
        return StockBatch::query()
            ->when($asAt !== null, fn ($query) => $query->where('received_at', '<=', $asAt))
            ->withSum(
                ['consumptions' => fn ($query) => $query->when(
                    $asAt !== null,
                    fn ($consumption) => $consumption->whereHas(
                        'movement',
                        fn ($movement) => $movement->where('occurred_at', '<=', $asAt)
                    )
                )],
                'quantity'
            );
    }
}
