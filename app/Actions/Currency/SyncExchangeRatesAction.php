<?php

namespace App\Actions\Currency;

use App\Exceptions\ExchangeRateSyncFailedException;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetch today's published rates and record them.
 *
 * This is the ONLY thing in the application that calls the rate service, and it
 * runs from a schedule or a button — never from a page request. Screens read the
 * `exchange_rates` table, so the application keeps working when the service is
 * down or the machine is offline.
 *
 * The feed quotes everything against its own base (USD by default), which need
 * not be ours. What is wanted is base-per-foreign, so each rate is re-based:
 * `rate(X) = published[BASE] / published[X]`. With an IQD base and USD at 1, a
 * published IQD figure of 1310 is simply 1310 dinars to the dollar.
 *
 * This is also the only writer of `exchange_rates`. There is no way to type a
 * rate in by hand, on purpose: a rate is a fact about the market rather than a
 * preference, and a screen for entering one is a screen for entering a wrong
 * one that then costs every invoice behind it.
 */
final class SyncExchangeRatesAction
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * @return Collection<int, ExchangeRate> one row per foreign currency, as written
     *
     * @throws ExchangeRateSyncFailedException
     */
    public function handle(?string $effectiveOn = null): Collection
    {
        $endpoint = (string) config('money.rates.endpoint');
        $effectiveOn ??= today()->toDateString();

        $published = $this->fetch($endpoint);
        $base = $this->currencies->base();

        $foreign = array_values(array_diff(array_keys($this->currencies->currencies()), [$base]));
        $missing = array_values(array_filter(
            [$base, ...$foreign],
            fn (string $code): bool => ! isset($published[$code]) || $published[$code] <= 0,
        ));

        if ($missing !== []) {
            throw ExchangeRateSyncFailedException::missingCurrencies($endpoint, ...$missing);
        }

        // All or nothing: a partial sync would leave some currencies on today's
        // rate and others on last week's, and nothing on screen would say so.
        return DB::transaction(function () use ($published, $base, $foreign, $effectiveOn): Collection {
            $written = new Collection;

            foreach ($foreign as $currency) {
                $written->push($this->currencies->record(
                    currency: $currency,
                    rate: $this->rebase($published[$base], $published[$currency]),
                    effectiveOn: $effectiveOn,
                ));
            }

            return $written;
        });
    }

    /**
     * The published rates, keyed by uppercase currency code.
     *
     * @return array<string, float>
     *
     * @throws ExchangeRateSyncFailedException
     */
    private function fetch(string $endpoint): array
    {
        try {
            $response = Http::timeout((int) config('money.rates.timeout', 10))
                ->acceptJson()
                ->get($endpoint);
        } catch (Throwable $exception) {
            throw ExchangeRateSyncFailedException::unreachable($endpoint, $exception->getMessage());
        }

        if ($response->failed()) {
            throw ExchangeRateSyncFailedException::unreachable($endpoint, "HTTP {$response->status()}");
        }

        $rates = $response->json('rates');

        if (! is_array($rates) || $rates === []) {
            throw ExchangeRateSyncFailedException::unusableResponse($endpoint);
        }

        $published = [];

        foreach ($rates as $code => $rate) {
            if (is_numeric($rate)) {
                $published[strtoupper((string) $code)] = (float) $rate;
            }
        }

        return $published;
    }

    /**
     * Re-base a published pair into base-per-foreign, as a decimal string.
     *
     * The division is the one place a float is unavoidable — it is what the feed
     * hands over. Formatting to the rate's own precision here is what turns it
     * back into something exact, and `ExchangeRates::rateFromDecimal()` parses
     * the string rather than the float.
     */
    private function rebase(float $publishedBase, float $publishedForeign): string
    {
        return number_format(
            $publishedBase / $publishedForeign,
            ExchangeRates::RATE_PRECISION,
            '.',
            ''
        );
    }
}
