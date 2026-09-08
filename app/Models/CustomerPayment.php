<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\CustomerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money a customer paid against what they owe.
 *
 * Never touches stock: the goods left when the sale was delivered, and a
 * repayment is money against the debt that sale created. An arch test keeps it
 * that way.
 *
 * Recorded once and never edited — see the migration for why. Deleting one
 * unwinds its allocations with it.
 *
 * @property int $id
 * @property int $customer_id
 * @property Money $amount
 * @property Carbon $received_on
 * @property PaymentMethod $payment_method
 * @property int|null $bank_id
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Bank|null $bank
 * @property-read Collection<int, CustomerPaymentAllocation> $allocations
 */
#[Fillable([
    'customer_id',
    'amount',
    'received_on',
    'payment_method',
    'bank_id',
    'currency',
    'exchange_rate',
    'notes',
])]
class CustomerPayment extends Model
{
    /** @use HasFactory<CustomerPaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'received_on' => 'date',
            'payment_method' => PaymentMethod::class,
            'exchange_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The account the money came into, on a card or transfer. Null on cash, and
     * on anything recorded before banks existed.
     *
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    /**
     * Whether the money came in as something other than the currency it is
     * stored in.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== app(CurrencyService::class)->base();
    }

    /**
     * The rate this payment was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }
}
