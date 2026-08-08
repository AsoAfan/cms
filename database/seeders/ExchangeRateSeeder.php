<?php

namespace Database\Seeders;

use App\Actions\Currency\SyncExchangeRatesAction;
use App\Exceptions\ExchangeRateSyncFailedException;
use App\Services\CurrencyService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ExchangeRateSeeder extends Seeder
{
    /**
     * A fallback rate, used only when the published one cannot be fetched.
     *
     * Roughly what a dollar has been worth in dinars, and dated far enough back
     * that a document seeded with a historical date still finds a rate in force
     * on its own day. `currency:sync` replaces it the moment it can reach the
     * feed.
     */
    public const string FALLBACK_USD_RATE = '1320';

    use WithoutModelEvents;

    public function __construct(
        private readonly SyncExchangeRatesAction $sync,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * Fetch today's published rates so a fresh install can price something
     * immediately.
     *
     * Falls back rather than failing: seeding must work on a machine with no
     * network, and a stale rate is fixed by the next sync. Nothing here is a
     * preference — see SyncExchangeRatesAction for why no rate is ever typed in.
     */
    public function run(): void
    {
        try {
            $this->sync->handle();

            return;
        } catch (ExchangeRateSyncFailedException $exception) {
            // Logged rather than printed: the seeder also runs from tests, where
            // there is no console to write to.
            Log::warning('Could not fetch published exchange rates while seeding; using the fallback rate.', [
                'reason' => $exception->getMessage(),
            ]);
        }

        // Only the dollar has a sensible figure to guess at, and it is the only
        // foreign currency this application trades in. Anything else stays
        // absent, which is what keeps it off the currency dropdowns until a
        // real rate arrives.
        if ($this->currencies->latestRowOn('USD') === null) {
            $this->currencies->record(
                currency: 'USD',
                rate: self::FALLBACK_USD_RATE,
                effectiveOn: today()->subYears(2)->toDateString(),
            );
        }
    }
}
