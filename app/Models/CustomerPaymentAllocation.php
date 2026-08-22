<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How much of one payment settled one invoice.
 *
 * The record that makes per-invoice balances a fact rather than an inference:
 * what is left on a sale is its total, less what was paid at the time, less the
 * allocations against it.
 *
 * @property int $id
 * @property int $customer_payment_id
 * @property int $sale_id
 * @property Money $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerPayment $payment
 * @property-read Sale $sale
 */
#[Fillable(['customer_payment_id', 'sale_id', 'amount'])]
class CustomerPaymentAllocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => Money::class,
        ];
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
