<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Open the books in something.
     *
     * A starting pair from `config('money.seed_currencies')`, with
     * `config('money.currency')` as the base. They are ordinary rows from here:
     * add, remove and re-base them on Settings → Exchange rates.
     */
    public function run(): void
    {
        $base = strtoupper((string) config('money.currency'));

        /** @var list<array{code: string, name: string, symbol: string, fraction_digits: int}> $seeds */
        $seeds = config('money.seed_currencies', []);

        foreach ($seeds as $seed) {
            $code = strtoupper($seed['code']);

            Currency::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $seed['name'],
                    'symbol' => $seed['symbol'],
                    'fraction_digits' => $seed['fraction_digits'],
                    'is_base' => $code === $base,
                ]
            );
        }

        // Whatever the config named, the books must be kept in something.
        if (! Currency::query()->where('is_base', true)->exists()) {
            Currency::query()->oldest('id')->first()?->update(['is_base' => true]);
        }
    }
}
