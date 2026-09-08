<?php

namespace App\Models;

use App\Enums\BankAdjustmentDirection;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\BankAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money put into or taken out of an account by hand.
 *
 * Trade explains most of what an account holds — sales taken into it, stock and
 * expenses paid out of it, repayments received. This is everything else: the
 * balance the account already had, cash walked to the bank, interest, charges.
 *
 * `amount` is SIGNED base-currency minor units: positive in, negative out. A
 * row is a fact about the account, so a wrong one is deleted and recorded
 * again rather than edited.
 *
 * @property int $id
 * @property int $bank_id
 * @property string $reason
 * @property Money $amount
 * @property Carbon $occurred_on
 * @property string $currency
 * @property int $exchange_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Bank $bank
 */
#[Fillable([
    'bank_id',
    'reason',
    'amount',
    'occurred_on',
    'currency',
    'exchange_rate',
])]
class BankAdjustment extends Model
{
    /** @use HasFactory<BankAdjustmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'occurred_on' => 'date',
            'exchange_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Which way this one went, read off the sign rather than a second column
     * that could disagree with it.
     */
    public function direction(): BankAdjustmentDirection
    {
        return BankAdjustmentDirection::of($this->amount);
    }

    /**
     * Whether this was recorded in something other than the currency it is
     * stored in.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== app(CurrencyService::class)->base();
    }

    /**
     * The rate this was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }
}
