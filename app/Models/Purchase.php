<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A supplier invoice.
 *
 * @property int $id
 * @property int|null $supplier_id
 * @property string $number
 * @property Carbon $invoiced_on
 * @property PurchaseStatus $status
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseLine> $lines
 * @property-read Collection<int, PurchaseAdditionalCost> $additionalCosts
 */
#[Fillable([
    'supplier_id',
    'number',
    'invoiced_on',
    'status',
    'currency',
    'exchange_rate',
    'notes',
    'posted_at',
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
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Whether this invoice was written in something other than the currency its
     * amounts are stored in.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== config('money.currency');
    }

    /**
     * The rate this invoice was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }

    /**
     * Who it was bought from, when that was worth recording.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === PurchaseStatus::Posted;
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
