<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
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
 * Everything here is append-only. Nothing edits or deletes a movement, a batch
 * or a consumption, so the ledger can always be replayed to explain how a
 * figure was arrived at.
 *
 * Costing is FIFO. An issue draws from the oldest batches first and records
 * exactly which ones it took from, which is what makes COGS a fact rather than
 * an estimate.
 */
final class InventoryService
{
    public function __construct(private readonly StockOnHandQuery $onHand) {}

    /**
     * Take stock in, creating the FIFO batch it will later be consumed from.
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
        if ($quantity < 1) {
            throw new InvalidArgumentException('A receipt needs a quantity of at least one.');
        }

        if ($unitCost->isNegative()) {
            throw new InvalidArgumentException('A unit cost cannot be negative.');
        }

        $occurredAt = Carbon::instance($occurredAt ?? Carbon::now());

        return DB::transaction(function () use ($product, $quantity, $unitCost, $type, $occurredAt, $source, $reason): StockMovement {
            $movement = $this->writeMovement($product, $quantity, $type, $occurredAt, $source, $reason);

            StockBatch::query()->create([
                'product_id' => $product->id,
                'received_movement_id' => $movement->id,
                'quantity_received' => $quantity,
                'unit_cost' => $unitCost,
                'received_at' => $occurredAt,
            ]);

            return $movement;
        });
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
