<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\SaleLine;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Puts a sale's goods back on the shelf, when it moves back to `ordered` or is
 * edited or deleted after being sent out.
 *
 * Each line hands its quantity back to the batches it drew from, at the cost
 * it drew them at, so the FIFO layers end up exactly as they were before. Any
 * sale that took stock after this one keeps the allocation it recorded — only
 * what this sale took comes back.
 *
 * Always possible, unlike reverting a purchase: goods coming back cannot be
 * blocked by anything downstream. Idempotent.
 */
final class RevertSaleAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Sale $sale): Sale
    {
        if (! $sale->isCommitted()) {
            return $sale;
        }

        $sale->load('lines');

        return DB::transaction(function () use ($sale): Sale {
            $sale->lines->each(
                fn (SaleLine $line) => $this->inventory->rollback($line)
            );

            $sale->forceFill(['committed_at' => null])->save();

            return $sale->refresh();
        });
    }
}
