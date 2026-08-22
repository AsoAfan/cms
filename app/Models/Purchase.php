<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A purchase invoice.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $invoiced_on
 * @property PurchaseStatus $status
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PurchaseLine> $lines
 * @property-read Collection<int, PurchaseAdditionalCost> $additionalCosts
 *
 * Aggregates the read models add with `withSum`, so a page of invoices costs a
 * fixed number of queries rather than one per row. Present only when the query
 * asked for them, and null when nothing matched — cast with `(int)`, never
 * through a model cast, which does not apply to an aggregate alias.
 * @property-read int|null $goods_minor_units
 * @property-read int|null $additional_minor_units
 */
#[Fillable([
    'number',
    'invoiced_on',
    'status',
    'currency',
    'exchange_rate',
    'notes',
    'committed_at',
])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoiced_on' => 'date',
            'status' => PurchaseStatus::class,
            'exchange_rate' => 'integer',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * Whether this invoice was written in something other than the currency its
     * amounts are stored in.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== app(CurrencyService::class)->base();
    }

    /**
     * The rate this invoice was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /**
     * @return HasMany<PurchaseAdditionalCost, $this>
     */
    public function additionalCosts(): HasMany
    {
        return $this->hasMany(PurchaseAdditionalCost::class);
    }

    /**
     * Whether the goods on this invoice are in the stock ledger.
     *
     * Read off `committed_at` rather than the status, because the two are
     * settled one after the other and this is the one that says what the
     * ledger actually holds.
     */
    public function isCommitted(): bool
    {
        return $this->committed_at !== null;
    }

    /**
     * Whether the status it is at says the goods should be in the ledger.
     */
    public function shouldHoldStock(): bool
    {
        return $this->status->holdsStock();
    }

    /**
     * What the goods came to, before invoice-wide costs.
     */
    public function goodsTotal(): Money
    {
        $this->loadMissing('lines');

        return Money::sum(...$this->lines->map(fn (PurchaseLine $line): Money => $line->netTotal()));
    }

    /**
     * Freight, duty and the like.
     */
    public function additionalCostsTotal(): Money
    {
        $this->loadMissing('additionalCosts');

        return Money::sum(
            ...$this->additionalCosts->map(fn (PurchaseAdditionalCost $cost): Money => $cost->amount)
        );
    }

    /**
     * What the invoice comes to. Derived, never stored.
     */
    public function total(): Money
    {
        return $this->goodsTotal()->plus($this->additionalCostsTotal());
    }

    /**
     * How many units this invoice brings in.
     */
    public function totalQuantity(): int
    {
        $this->loadMissing('lines');

        return (int) $this->lines->sum('quantity');
    }

    /**
     * The next filing reference, e.g. PUR-00007.
     */
    public static function nextNumber(): string
    {
        $latest = (int) static::query()->max('id');

        return sprintf('PUR-%05d', $latest + 1);
    }
}
