<?php

namespace App\Models;

use App\Enums\PaymentMethod;
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
 * @property Money $amount
 * @property Carbon $spent_on
 * @property PaymentMethod $payment_method
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExpenseCategory $category
 */
#[Fillable([
    'expense_category_id',
    'amount',
    'spent_on',
    'payment_method',
    'reference',
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
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
