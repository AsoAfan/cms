<?php

namespace App\Models;

use App\Support\ExchangeRates;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What one unit of a foreign currency was worth in the base currency on a date,
 * as published.
 *
 * Written only by `currency:sync`. Nothing in the application offers a way to
 * type a rate in: it is a fact about the market, not a preference.
 *
 * `rate` is the fixed-point integer defined by {@see ExchangeRates::SCALE}; read
 * it through `decimalRate()` rather than dividing by hand.
 *
 * @property int $id
 * @property string $currency
 * @property int $rate
 * @property Carbon $effective_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'currency',
    'rate',
    'effective_on',
])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'effective_on' => 'date',
        ];
    }

    /**
     * The rate as it reads on screen: "1320.5".
     */
    public function decimalRate(): string
    {
        return ExchangeRates::rateToDecimal($this->rate);
    }
}
