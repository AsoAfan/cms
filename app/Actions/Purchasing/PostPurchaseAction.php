<?php

namespace App\Actions\Purchasing;

use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Exceptions\PurchaseNotPostableException;
use App\Models\Purchase;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Turns a draft invoice into stock.
 *
 * Freight and duty belong to the goods, not beside them: a curtain that cost
 * $18 with $2 of freight on it cost $20, and selling it at $44 made $24, not
 * $26. So each invoice-wide cost is spread across the lines before anything
 * reaches the ledger, and the batches are written at that landed cost.
 *
 * Spreading uses largest-remainder allocation, so the amounts handed to the
 * lines add back to exactly what was on the invoice. The action asserts that
 * before it writes anything — if the arithmetic ever drifted, every figure
 * built on it afterwards would be wrong, and failing loudly here is far
 * cheaper than discovering it in a year-end report.
 */
final class PostPurchaseAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @throws PurchaseNotPostableException
     */
    public function handle(Purchase $purchase): Purchase
    {
        if ($purchase->isPosted()) {
            throw PurchaseNotPostableException::alreadyPosted($purchase);
        }

        $purchase->load(['lines.product', 'additionalCosts']);

        if ($purchase->lines->isEmpty()) {
            throw PurchaseNotPostableException::hasNoLines($purchase);
        }

        $landed = $this->landedTotals($purchase);

        return DB::transaction(function () use ($purchase, $landed): Purchase {
            foreach ($purchase->lines as $line) {
                $this->inventory->receiveAtTotalCost(
                    product: $line->product,
                    quantity: $line->quantity,
                    totalCost: $landed[$line->id],
                    type: StockMovementType::Purchase,
                    occurredAt: $purchase->invoiced_on->startOfDay(),
                    source: $line,
                    reason: null,
                );
            }

            $purchase->forceFill([
                'status' => PurchaseStatus::Posted,
                'posted_at' => now(),
            ])->save();

            return $purchase->refresh();
        });
    }

    /**
     * What each line actually cost once the invoice-wide costs are spread
     * over it, keyed by line id.
     *
     * @return array<int, Money>
     */
    public function landedTotals(Purchase $purchase): array
    {
        $lines = $purchase->lines;

        /** @var array<int, Money> $totals */
        $totals = $lines
            ->mapWithKeys(fn (PurchaseLine $line): array => [$line->id => $line->netTotal()])
            ->all();

        foreach ($purchase->additionalCosts as $cost) {
            foreach ($this->spread($cost, $lines) as $lineId => $share) {
                $totals[$lineId] = $totals[$lineId]->plus($share);
            }
        }

        $this->assertReconciles($purchase, $totals);

        return $totals;
    }

    /**
     * Split one invoice-wide cost across the lines, keyed by line id.
     *
     * @param  Collection<int, PurchaseLine>  $lines
     * @return array<int, Money>
     */
    private function spread(PurchaseAdditionalCost $cost, $lines): array
    {
        $weights = $lines->map(fn (PurchaseLine $line): int => match ($cost->allocation_method) {
            CostAllocationMethod::ByValue => $line->netTotal()->minorUnits,
            CostAllocationMethod::ByQuantity => $line->quantity,
        })->all();

        // A cost spread by value over lines that are all worth nothing has no
        // sensible proportion to follow, so fall back to spreading it evenly.
        if (array_sum($weights) === 0) {
            $weights = array_fill(0, $lines->count(), 1);
        }

        $shares = $cost->amount->allocate($weights);

        return $lines->values()
            ->mapWithKeys(fn (PurchaseLine $line, int $index): array => [$line->id => $shares[$index]])
            ->all();
    }

    /**
     * The landed totals must add up to the invoice, to the penny.
     *
     * @param  array<int, Money>  $totals
     */
    private function assertReconciles(Purchase $purchase, array $totals): void
    {
        $allocated = Money::sum(...array_values($totals));
        $invoice = $purchase->total();

        if (! $allocated->equals($invoice)) {
            throw new LogicException(sprintf(
                'Landed cost allocation for %s came to %s but the invoice is %s.',
                $purchase->number,
                $allocated->toDecimal(),
                $invoice->toDecimal(),
            ));
        }
    }
}
