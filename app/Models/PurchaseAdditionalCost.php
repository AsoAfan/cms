<?php

namespace App\Models;

use App\Enums\CostAllocationMethod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cost belonging to the whole invoice — freight, duty, handling.
 *
 * @property int $id
 * @property int $purchase_id
 * @property string $label
 * @property Money $amount
 * @property CostAllocationMethod $allocation_method
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purchase $purchase
 */
#[Fillable(['purchase_id', 'label', 'amount', 'allocation_method'])]
class PurchaseAdditionalCost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'allocation_method' => CostAllocationMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
