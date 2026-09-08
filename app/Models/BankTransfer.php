<?php

namespace App\Models;

use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\BankTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money moved between two of the business's own accounts.
 *
 * One row covering both sides, so the total the business holds cannot change
 * when money moves inside it — see the migration for why that is a schema
 * property rather than a rule. `BankBalanceQuery` takes the amount off
 * `from_bank_id` and puts it on `to_bank_id`.
 *
 * This is NOT income or outcome, and nothing in reporting counts it: no money
 * entered or left the business.
 *
 * @property int $id
 * @property int $from_bank_id
 * @property int $to_bank_id
 * @property Money $amount
 * @property Carbon $occurred_on
 * @property string|null $reason
 * @property string $currency
 * @property int $exchange_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Bank $fromBank
 * @property-read Bank $toBank
 */
#[Fillable([
    'from_bank_id',
    'to_bank_id',
    'amount',
    'occurred_on',
    'reason',
    'currency',
    'exchange_rate',
])]
class BankTransfer extends Model
{
    /** @use HasFactory<BankTransferFactory> */
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
    public function fromBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'from_bank_id');
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function toBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'to_bank_id');
    }

    /**
     * How it reads on screen: which account to which.
     */
    public function route(): string
    {
        return "{$this->fromBank->name} → {$this->toBank->name}";
    }

    /**
     * Whether this was moved in something other than the currency it is stored
     * in.
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
