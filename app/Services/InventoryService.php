<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockAlreadyConsumedException;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchConsumption;
use App\Models\StockMovement;
use App\Queries\StockOnHandQuery;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only thing in the application that writes stock.
 *
 * Receiving and issuing are append-only: nothing edits a movement, a batch or
 * a consumption, so the ledger can always be replayed to explain how a figure
 * was arrived at.
 *
 * `rollback()` is the single exception, and it is a deliberate one. A document
 * that moves back down its statuses — a delivery that never arrived, an order
 * cancelled after it was marked sent — has to stop being in the ledger, and a
 * shop owner correcting yesterday's typo should not be made to read a pair of
 * offsetting entries to understand today's stock. So the document's own
 * movements are removed rather than mirrored. It undoes; it never rewrites.
 * A receipt whose goods have already been sold on cannot be undone at all.
 *
 * Costing is FIFO. An issue draws from the oldest batches first and records
 * exactly which ones it took from, which is what makes COGS a fact rather than
 * an estimate.
 */
final class InventoryService
{
    public function __construct(private readonly StockOnHandQuery $onHand) {}

    /**
     * Take stock in at a known cost per unit.
     *
     * @param  Money  $unitCost  landed cost per unit, fixed from here on
     *
     * @throws InvalidArgumentException
     */
    public function receive(
        Product $product,
        int $quantity,
        Money $unitCost,
        StockMovementType $type = StockMovementType::Adjustment,
        ?DateTimeInterface $occurredAt = null,
        ?Model $source = null,
        ?string $reason = null,
    ): StockMovement {
        if ($unitCost->isNegative()) {
            throw new InvalidArgumentException('A unit cost cannot be negative.');
        }

        return $this->receiveAtTotalCost(
            product: $product,
            quantity: $quantity,
            totalCost: $unitCost->multipliedBy(max($quantity, 0)),
            type: $type,
            occurredAt: $occurredAt,
            source: $source,
            reason: $reason,
        );
    }

    /**
     * Take stock in for a known total outlay, letting the per-unit cost fall
     * out of it.
     *
     * This is the shape a purchase actually arrives in: you know what the line
     * cost once freight and duty were spread over it, not what one unit cost.
     * The total is allocated across the units with largest-remainder rounding
     * and equal costs are grouped, so three units costing $10.00 all in become
     * two batches — two at $3.33 and one at $3.34 — and the batches add back
     * to exactly what was paid. Rounding to $3.33 each would quietly lose a
     * penny out of inventory on every such line.
     *
     * @throws InvalidArgumentException
     */
    public function receiveAtTotalCost(
        Product $product,
        int $quantity,
        Money $totalCost,
        StockMovementType $type = StockMovementType::Adjustment,
        ?DateTimeInterface $occurredAt = null,
        ?Model $source = null,
        ?string $reason = null,
    ): StockMovement {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A receipt needs a quantity of at least one.');
        }

        if ($totalCost->isNegative()) {
            throw new InvalidArgumentException('A receipt cannot cost a negative amount.');
        }

        $occurredAt = Carbon::instance($occurredAt ?? Carbon::now());

        return DB::transaction(function () use ($product, $quantity, $totalCost, $type, $occurredAt, $source, $reason): StockMovement {
            $movement = $this->writeMovement($product, $quantity, $type, $occurredAt, $source, $reason);

            foreach ($this->batchCosts($totalCost, $quantity) as $unitCost => $units) {
                StockBatch::query()->create([
                    'product_id' => $product->id,
                    'received_movement_id' => $movement->id,
                    'quantity_received' => $units,
                    'unit_cost' => Money::fromMinorUnits($unitCost),
                    'received_at' => $occurredAt,
                ]);
            }

            return $movement;
        });
    }

    /**
     * Spread a total across units, then group units that cost the same.
     *
     * Returns unit cost in minor units => how many units carry it, cheapest
     * first, which is also the order FIFO will consume them in.
     *
     * @return array<int, int>
     */
    private function batchCosts(Money $totalCost, int $quantity): array
    {
        $grouped = [];

        foreach ($totalCost->split($quantity) as $unitCost) {
            $grouped[$unitCost->minorUnits] = ($grouped[$unitCost->minorUnits] ?? 0) + 1;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Take stock out, drawing from the oldest batches first.
     *
     * @throws InsufficientStockException when the ledger cannot cover it
     * @throws InvalidArgumentException
     */
    public function issue(
        Product $product,
        int $quantity,
        StockMovementType $type = StockMovementType::Adjustment,
        ?DateTimeInterface $occurredAt = null,
        ?Model $source = null,
        ?string $reason = null,
    ): StockMovement {
        if ($quantity < 1) {
            throw new InvalidArgumentException('An issue needs a quantity of at least one.');
        }

        $occurredAt = Carbon::instance($occurredAt ?? Carbon::now());

        return DB::transaction(function () use ($product, $quantity, $type, $occurredAt, $source, $reason): StockMovement {
            $allocations = $this->allocate($product, $quantity, $occurredAt);

            $movement = $this->writeMovement($product, -$quantity, $type, $occurredAt, $source, $reason);

            foreach ($allocations as [$batch, $taken]) {
                StockBatchConsumption::query()->create([
                    'stock_batch_id' => $batch->id,
                    'stock_movement_id' => $movement->id,
                    'quantity' => $taken,
                ]);
            }

            return $movement;
        });
    }

    /**
     * Correct the books to match a physical count.
     *
     * A positive correction is a receipt and so needs a cost — stock that
     * appears from nowhere still has to be worth something, or inventory
     * valuation quietly understates. A negative correction is an issue and
     * takes its cost from the batches it consumes.
     *
     * @throws InsufficientStockException
     * @throws InvalidArgumentException
     */
    public function adjust(
        Product $product,
        int $delta,
        string $reason,
        ?Money $unitCost = null,
        ?DateTimeInterface $occurredAt = null,
    ): StockMovement {
        if ($delta === 0) {
            throw new InvalidArgumentException('An adjustment has to change the quantity.');
        }

        if ($delta > 0) {
            if ($unitCost === null) {
                throw new InvalidArgumentException(
                    'Adding stock needs a unit cost, otherwise it cannot be valued.'
                );
            }

            return $this->receive(
                product: $product,
                quantity: $delta,
                unitCost: $unitCost,
                type: StockMovementType::Adjustment,
                occurredAt: $occurredAt,
                reason: $reason,
            );
        }

        return $this->issue(
            product: $product,
            quantity: abs($delta),
            type: StockMovementType::Adjustment,
            occurredAt: $occurredAt,
            reason: $reason,
        );
    }

    /**
     * Take everything one document line put into the ledger back out again.
     *
     * The only method here that removes anything, and the only way a purchase
     * or a sale can be edited or deleted after its stock has moved. What it
     * undoes depends on which direction the movement went:
     *
     * - A **receipt** takes its batches with it, so the goods stop existing
     *   rather than becoming stock nobody can account for. Refused outright if
     *   any of those batches has been drawn on — see the exception.
     * - An **issue** drops its consumptions, which hands the quantity back to
     *   the batches it took them from at the cost it took them at. Later sales
     *   keep the allocations they already recorded; only what this line took
     *   comes back.
     *
     * Silent when the line never reached the ledger, so callers can undo
     * without first working out whether there is anything to undo.
     *
     * @throws StockAlreadyConsumedException
     */
    public function rollback(Model $source): void
    {
        $movements = StockMovement::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->with(['product', 'batches.consumptions'])
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($movements): void {
            foreach ($movements as $movement) {
                foreach ($movement->batches as $batch) {
                    if ($batch->consumptions->isNotEmpty()) {
                        throw StockAlreadyConsumedException::for($movement->product);
                    }

                    $batch->delete();
                }

                $movement->consumptions()->delete();
                $movement->delete();
            }
        });
    }

    /**
     * Work out which batches an issue draws from, oldest first.
     *
     * Only batches received on or before the issue date are eligible: stock
     * that had not arrived yet cannot have been sold.
     *
     * @return list<array{StockBatch, int}>
     *
     * @throws InsufficientStockException
     */
    private function allocate(Product $product, int $quantity, Carbon $occurredAt): array
    {
        $batches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('received_at', '<=', $occurredAt)
            ->withSum('consumptions', 'quantity')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $outstanding = $quantity;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($outstanding === 0) {
                break;
            }

            $available = $batch->remainingQuantity();

            if ($available < 1) {
                continue;
            }

            $taken = min($outstanding, $available);
            $allocations[] = [$batch, $taken];
            $outstanding -= $taken;
        }

        if ($outstanding > 0) {
            throw InsufficientStockException::for(
                $product,
                $quantity,
                $this->onHand->forProduct($product, $occurredAt),
            );
        }

        return $allocations;
    }

    private function writeMovement(
        Product $product,
        int $quantity,
        StockMovementType $type,
        Carbon $occurredAt,
        ?Model $source,
        ?string $reason,
    ): StockMovement {
        $movement = new StockMovement([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'reason' => $reason,
        ]);

        if ($source !== null) {
            $movement->source()->associate($source);
        }

        $movement->save();

        return $movement;
    }
}
