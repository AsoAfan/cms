<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sale over the counter.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $sold_on
 * @property SaleStatus $status
 * @property PaymentMethod $payment_method
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SaleLine> $lines
 */
#[Fillable([
    'number',
    'sold_on',
    'status',
    'payment_method',
    'currency',
    'exchange_rate',
    'notes',
    'posted_at',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'exchange_rate' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Whether the money was taken in something other than the currency the sale
     * is stored in — dollars over the counter, typically.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== config('money.currency');
    }

    /**
     * The rate this sale was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === SaleStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === SaleStatus::Posted;
    }

    /**
     * What the customer paid. Derived, never stored.
     */
    public function total(): Money
    {
        $this->loadMissing('lines');

        return Money::sum(...$this->lines->map(fn (SaleLine $line): Money => $line->netTotal()));
    }

    /**
     * What these goods cost, from the batches the ledger actually consumed.
     *
     * Zero until the sale is posted: nothing has left the shelf yet, so there
     * is no cost to speak of.
     */
    public function costOfGoodsSold(): Money
    {
        $this->loadMissing('lines');

        return Money::sum(...$this->lines->map(fn (SaleLine $line): Money => $line->costOfGoodsSold()));
    }

    /**
     * What was made on it: takings less what the goods cost.
     */
    public function grossProfit(): Money
    {
        return $this->total()->minus($this->costOfGoodsSold());
    }

    public function totalQuantity(): int
    {
        $this->loadMissing('lines');

        return (int) $this->lines->sum('quantity');
    }

    public static function nextNumber(): string
    {
        $latest = (int) static::query()->max('id');

        return sprintf('SAL-%05d', $latest + 1);
    }
}
