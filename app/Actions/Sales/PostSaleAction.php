<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleNotPostableException;
use App\Models\Sale;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Takes a sale off the shelf.
 *
 * Each line is issued through the inventory service, which draws from the
 * oldest batches first and records exactly which ones it took. That is what
 * makes the cost of this sale a fact rather than an estimate, and it is why
 * nothing here stores a cost of its own.
 */
final class PostSaleAction
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockOnHandQuery $onHand,
    ) {}

    /**
     * @throws SaleNotPostableException
     */
    public function handle(Sale $sale): Sale
    {
        if ($sale->isPosted()) {
            throw SaleNotPostableException::alreadyPosted($sale);
        }

        $sale->load(['lines.product']);

        if ($sale->lines->isEmpty()) {
            throw SaleNotPostableException::hasNoLines($sale);
        }

        $this->assertEverythingIsInStock($sale);

        return DB::transaction(function () use ($sale): Sale {
            foreach ($sale->lines as $line) {
                try {
                    $this->inventory->issue(
                        product: $line->product,
                        quantity: $line->quantity,
                        type: StockMovementType::Sale,
                        occurredAt: $sale->sold_on->endOfDay(),
                        source: $line,
                    );
                } catch (InsufficientStockException $exception) {
                    // The pre-flight said there was enough, so something moved
                    // underneath us. Roll the whole sale back rather than post
                    // half of it.
                    throw SaleNotPostableException::notEnoughStock([$exception->getMessage()]);
                }
            }

            $sale->forceFill([
                'status' => SaleStatus::Posted,
                'posted_at' => now(),
            ])->save();

            return $sale->refresh();
        });
    }

    /**
     * Check every line before issuing any of them, so a short sale reports all
     * its problems at once instead of one per attempt.
     *
     * @throws SaleNotPostableException
     */
    private function assertEverythingIsInStock(Sale $sale): void
    {
        $asAt = $sale->sold_on->endOfDay();
        $shortages = [];

        // Several lines can name the same product, so count what the sale asks
        // for in total rather than line by line.
        $wanted = $sale->lines
            ->groupBy('product_id')
            ->map(fn ($lines): int => (int) $lines->sum('quantity'));

        foreach ($sale->lines->unique('product_id') as $line) {
            $available = $this->onHand->forProduct($line->product, $asAt);
            $needed = $wanted[$line->product_id];

            if ($available < $needed) {
                $shortages[] = sprintf(
                    '%s (%s) needs %d, has %d',
                    $line->product->name,
                    $line->product->code,
                    $needed,
                    $available,
                );
            }
        }

        if ($shortages !== []) {
            throw SaleNotPostableException::notEnoughStock($shortages);
        }
    }
}
