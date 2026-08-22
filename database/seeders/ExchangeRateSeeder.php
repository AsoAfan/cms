<?php

namespace Database\Seeders;

use App\Services\CurrencyService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * An opening rate, so a fresh install can price something in dollars
     * straight away.
     *
     * Dated far enough back that a document seeded with a historical date still
     * finds a rate in force on its own day. It is a starting figure and nothing
     * more — record what you actually trade at on Settings → Exchange rates.
     */
    public const string OPENING_USD_RATE = '1320';

    use WithoutModelEvents;

    public function __construct(private readonly CurrencyService $currencies) {}

    public function run(): void
    {
        if ($this->currencies->latestRowOn('USD') !== null) {
            return;
        }

        $this->currencies->record(
            currency: 'USD',
            rate: self::OPENING_USD_RATE,
            effectiveOn: today()->subYears(2)->toDateString(),
        );
    }
}
