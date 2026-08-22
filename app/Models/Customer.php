<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Who you sell to. Every sale names one.
 *
 * Carries no balance. What a customer owes is derived from their delivered
 * sales and the payments recorded against them — `App\Queries\CustomerBalanceQuery`
 * is the only place that arithmetic happens.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, CustomerPayment> $payments
 */
#[Fillable(['name', 'phone', 'email', 'address', 'notes', 'is_active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * The customer counter trade is recorded against, so that "every sale has a
     * buyer" costs nobody a keystroke. Seeded, and the sale form opens on it.
     */
    public const string WALK_IN = 'Walk-in';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The walk-in customer, created if somebody has removed or renamed it.
     *
     * A counter sale must never fail for want of a buyer to file it under, which
     * is the whole reason this row exists.
     */
    public static function walkIn(): self
    {
        return static::query()->firstOrCreate(
            ['name' => self::WALK_IN],
            ['notes' => 'Counter trade with nobody named.', 'is_active' => true],
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
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }
}
