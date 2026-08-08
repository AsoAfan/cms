<?php

namespace App\Actions\Purchasing;

use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseNotPostableException;
use App\Models\Purchase;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a draft invoice with its lines and additional costs.
 *
 * Lines and costs are replaced wholesale rather than diffed: a draft has no
 * downstream effects to preserve, so rewriting it is simpler and cannot leave
 * a half-updated invoice behind. Once posted, none of this may run at all.
 */
final class SavePurchaseAction
{
    /**
     * @param  array{supplier_id: int, invoiced_on: string, notes: string|null}  $header
     * @param  list<array{product_id: int, quantity: int, unit_cost: string, discount: string}>  $lines
     * @param  list<array{label: string, amount: string, allocation_method: string}>  $costs
     *
     * @throws PurchaseNotPostableException
     */
    public function handle(array $header, array $lines, array $costs, ?Purchase $purchase = null): Purchase
    {
        if ($purchase?->isPosted()) {
            throw PurchaseNotPostableException::isPosted($purchase);
        }

        return DB::transaction(function () use ($header, $lines, $costs, $purchase): Purchase {
            $purchase ??= new Purchase([
                'number' => Purchase::nextNumber(),
                'status' => PurchaseStatus::Draft,
            ]);

            $purchase->fill($header)->save();

            $purchase->lines()->delete();
            $purchase->additionalCosts()->delete();

            foreach ($lines as $line) {
                $purchase->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => Money::fromDecimal($line['unit_cost']),
                    'discount' => Money::fromDecimal($line['discount']),
                ]);
            }

            foreach ($costs as $cost) {
                $purchase->additionalCosts()->create([
                    'label' => $cost['label'],
                    'amount' => Money::fromDecimal($cost['amount']),
                    'allocation_method' => CostAllocationMethod::from($cost['allocation_method']),
                ]);
            }

            return $purchase->refresh();
        });
    }
}
