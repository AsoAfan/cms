<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money spent running the business.
 *
 * Never touches stock — see the migration for why, and `ArchTest` for the
 * rule that keeps it that way.
 *
 * @property int $id
 * @property int $expense_category_id
 * @property string $title
 * @property Money $amount
 * @property Carbon $spent_on
 * @property PaymentMethod $payment_method
 * @property int|null $bank_id
 * @property string $currency
 * @property int $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExpenseCategory $category
 * @property-read Bank|null $bank
 */
#[Fillable([
    'expense_category_id',
    'title',
    'amount',
    'spent_on',
    'payment_method',
    'bank_id',
    'currency',
    'exchange_rate',
    'notes',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'spent_on' => 'date',
            'payment_method' => PaymentMethod::class,
            'exchange_rate' => 'integer',
        ];
    }

    /**
     * Whether this was paid in something other than the currency it is stored
     * in.
     */
    public function isForeignCurrency(): bool
    {
        return $this->currency !== app(CurrencyService::class)->base();
    }

    /**
     * The rate this expense was converted at, as it reads on screen.
     */
    public function exchangeRate(): string
    {
        return ExchangeRates::rateToDecimal($this->exchange_rate);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * The account this was paid out of, on a card or transfer. Null on cash, and
     * on anything recorded before banks existed.
     *
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
