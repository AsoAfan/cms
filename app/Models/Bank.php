<?php

namespace App\Models;

use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An account non-cash money moves through.
 *
 * Named on a sale, an expense or a customer payment whenever the method is card
 * or bank transfer — see `PaymentMethod::usesBank()`, which is the only place
 * that decides which methods need one.
 *
 * @property int $id
 * @property string $name
 * @property string|null $account_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'account_number', 'notes'])]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    /**
     * The banks as every payment form receives them, in name order.
     *
     * The list is short by nature, so each screen that takes a payment carries
     * it whole rather than fetching one on first open.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return array_values(
            static::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (self $bank): array => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                ])
                ->all()
        );
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Whether anything has been paid through this account.
     *
     * The database refuses to delete a bank in this state anyway; the controller
     * asks first so the user gets a sentence rather than a 500.
     */
    public function isInUse(): bool
    {
        return $this->sales()->exists()
            || $this->expenses()->exists()
            || $this->customerPayments()->exists();
    }
}
