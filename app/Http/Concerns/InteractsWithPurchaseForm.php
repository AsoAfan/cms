<?php

namespace App\Http\Concerns;

use App\Enums\CostAllocationMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Models\Bank;
use App\Models\Product;
use App\Models\Purchase;

/**
 * Everything the purchase drawer needs to open, for any screen that mounts it.
 *
 * The drawer is not the purchases screen's alone: the loans screen opens it
 * prefilled with the goods it owes, so an order can be placed from the list
 * that says one is needed. Both screens serve the same props from here, because
 * a drawer that offered different products or different statuses depending on
 * where it was opened from would be two forms wearing one name.
 */
trait InteractsWithPurchaseForm
{
    /**
     * @return array<string, mixed>
     */
    protected function purchaseFormOptions(): array
    {
        return [
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'cost_price'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'cost_price' => $product->cost_price->toDecimal(),
                ]),
            'allocationMethods' => collect(CostAllocationMethod::cases())->map(
                fn (CostAllocationMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'description' => $method->description(),
                ]
            ),
            'statuses' => PurchaseStatus::options(),
            // How the supplier was paid, and out of which account — the same
            // two options every screen that takes a payment carries.
            'paymentMethods' => PaymentMethod::options(),
            'banks' => Bank::options(),
        ];
    }

    /**
     * The options plus the reference the next invoice will be filed under, for
     * a screen that only ever writes a new one.
     *
     * @return array<string, mixed>
     */
    protected function newPurchaseOptions(): array
    {
        return [
            ...$this->purchaseFormOptions(),
            'nextNumber' => Purchase::nextNumber(),
        ];
    }
}
