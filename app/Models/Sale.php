<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sale, to a named customer.
 *
 * `amount_paid` is what they handed over at the time — a fact of the
 * transaction, like a line's `unit_price`. Anything short of the total is a loan
 * against the customer, settled later by `CustomerPayment`s allocated to this
 * invoice. What is still owed is derived from those three facts and stored
 * nowhere.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $number
 * @property Carbon $sold_on
 * @property SaleStatus $status
 * @property PaymentMethod $payment_method
 * @property int|null $bank_id
 * @property Money $amount_paid
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Bank|null $bank
 * @property-read Collection<int, SaleLine> $lines
 * @property-read Collection<int, CustomerPaymentAllocation> $paymentAllocations
 *
 * Aggregates the read models add with `withSum`, so a page of invoices costs a
 * fixed number of queries rather than one per row. Present only when the query
 * asked for them, and null when nothing matched — cast with `(int)`, never
 * through a model cast, which does not apply to an aggregate alias.
 * @property-read int|null $net_minor_units
 * @property-read int|null $allocated_minor_units
 */
#[Fillable([
    'customer_id',
    'number',
    'sold_on',
    'status',
    'payment_method',
    'bank_id',
    'amount_paid',
    'currency',
    'exchange_rate',
    'notes',
    'committed_at',
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
            'amount_paid' => Money::class,
            'exchange_rate' => 'integer',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * Who bought it. Counter trade is the walk-in customer's — every sale has a
     * buyer, and the sale form opens on that one so it costs no keystrokes.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The account the money came into, on a card or transfer sale.
     *
     * Null on cash, and on anything recorded before banks existed.
     *
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Whether the money was taken in something other than the currency the sale
     * is stored in — dollars over the counter, typically.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== app(CurrencyService::class)->base();
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

    /**
     * The later payments applied to this invoice.
     *
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    /**
     * Whether the goods on this sale have left the stock ledger.
     *
     * Read off `committed_at` rather than the status: it is the one that says
     * what the ledger actually holds.
     */
    public function isCommitted(): bool
    {
        return $this->committed_at !== null;
    }

    /**
     * Whether the status it is at says the goods should be gone.
     */
    public function shouldReleaseStock(): bool
    {
        return $this->status->releasesStock();
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
     * Whether the goods are the customer's.
     *
     * This is the threshold for money. A delivered sale is revenue, and whatever
     * is unpaid on it is a debt; an `ordered` one is a quote, and one
     * `on_the_way` has left the shelf but is not yet the customer's. Neither of
     * those owes anybody anything.
     */
    public function isDelivered(): bool
    {
        return $this->status === SaleStatus::Proceed;
    }

    /**
     * Everything the customer has put towards this invoice: what they handed
     * over at the time of sale, plus every later payment allocated to it.
     */
    public function paidToDate(): Money
    {
        $this->loadMissing('paymentAllocations');

        return Money::sum(
            $this->amount_paid,
            ...$this->paymentAllocations->map(
                fn (CustomerPaymentAllocation $allocation): Money => $allocation->amount
            )
        );
    }

    /**
     * What is still owed on this invoice — the customer's loan against it.
     *
     * Derived from the lines, the money handed over and the allocations, so it
     * can never disagree with any of them. Undelivered sales owe nothing, however
     * much or little has been paid on them: money against an order not yet
     * handed over is a deposit, not a debt.
     */
    public function outstanding(): Money
    {
        if (! $this->isDelivered()) {
            return Money::zero();
        }

        return $this->total()->minus($this->paidToDate());
    }

    public function isSettled(): bool
    {
        return ! $this->outstanding()->isPositive();
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
