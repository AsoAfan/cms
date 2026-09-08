<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\StockMovement;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How much stock exists, derived by summing the ledger.
 *
 * There is no balance column to read, and deliberately so: a stored balance
 * can disagree with the movements that produced it, and then neither can be
 * trusted. Summing is always right.
 */
final class StockOnHandQuery
{
    /**
     * Quantity on hand for one product, optionally as at a past date.
     */
    public function forProduct(Product $product, ?DateTimeInterface $asAt = null): int
    {
        return (int) $this->base($asAt)
            ->where('product_id', $product->id)
            ->sum('quantity');
    }

    /**
     * Quantity on hand for every product that has ever moved, keyed by
     * product id. Products with no history are absent, not zero.
     *
     * @return Collection<int, int>
     */
    public function get(?DateTimeInterface $asAt = null): Collection
    {
        return $this->base($asAt)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity);
    }

    /**
     * @return Builder<StockMovement>
     */
    private function base(?DateTimeInterface $asAt)
    {
        return StockMovement::query()
            ->when($asAt !== null, fn ($query) => $query->where('occurred_at', '<=', $asAt));
    }
}
