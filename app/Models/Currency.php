<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A currency this business deals in.
 *
 * Exactly one is the base, and every monetary column in the application is
 * minor units of it. The rest can be typed into any money field, provided a
 * rate for them is on record.
 *
 * The `code` is the identity: documents and exchange rates reference it, not the
 * id, so a figure stays readable without a join.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $fraction_digits
 * @property bool $is_base
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ExchangeRate> $rates
 */
#[Fillable([
    'code',
    'name',
    'symbol',
    'fraction_digits',
    'is_base',
])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fraction_digits' => 'integer',
            'is_base' => 'boolean',
        ];
    }

    /**
     * Every rate quoted for this currency against the base.
     *
     * @return HasMany<ExchangeRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency', 'code');
    }
}
