<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Support\ExchangeRates;
use Illuminate\Support\Carbon;

/**
 * Everything the application knows about currency, in one place.
 *
 * The base currency is the only one anything is stored in. This service exists
 * to answer two questions: which currencies may an amount be typed or viewed in,
 * and what were they worth on a given date. Conversion itself belongs to the
 * {@see ExchangeRates} value object this hands out.
 *
 * Rates are read from the `exchange_rates` table and never from the network — a
 * page request must not depend on an external service being up. Filling that
 * table is `SyncExchangeRatesAction`'s job, on a schedule. Nobody types a rate
 * in: there is no screen for it and no method here that takes one from a user.
 */
final class CurrencyService
{
    /**
     * Rates already looked up in this request, keyed by date.
     *
     * A purchase form posts many amounts on one date, and every one of them
     * would otherwise repeat the same two queries.
     *
     * @var array<string, ExchangeRates>
     */
    private array $memoised = [];

    public function base(): string
    {
        return strtoupper((string) config('money.currency'));
    }

    public function locale(): string
    {
        return (string) config('money.locale');
    }

    /**
     * Every configured currency's display metadata, keyed by code.
     *
     * @return array<string, array{name: string, symbol: string, fraction_digits: int}>
     */
    public function currencies(): array
    {
        /** @var array<string, array{name: string, symbol: string, fraction_digits: int}> $configured */
        $configured = config('money.currencies', []);

        return $configured;
    }

    /**
     * The currencies an amount may actually be entered in on a given date: the
     * base currency, plus any with a rate on record.
     *
     * Form Requests validate against this, so a currency the sync has never
     * fetched a rate for can never be submitted and
     * `MissingExchangeRateException` stays an internal guard rather than a 500
     * a user can trigger.
     *
     * @return list<string>
     */
    public function enterable(?string $on = null): array
    {
        return $this->ratesOn($on)->currencies();
    }

    /**
     * The rates in force on a date — or today, if none is given.
     *
     * "In force" means the newest rate on or before that date, so a document
     * dated last month converts at last month's rate rather than today's, and a
     * day the sync did not run carries the previous day's figure forward.
     */
    public function ratesOn(?string $on = null): ExchangeRates
    {
        $on = $on === null ? today()->toDateString() : Carbon::parse($on)->toDateString();

        return $this->memoised[$on] ??= ExchangeRates::for(
            base: $this->base(),
            rates: $this->rateRowsOn($on),
            on: $on,
        );
    }

    /**
     * The rate to use for one currency on one date, or null if none is on
     * record yet.
     */
    public function latestRowOn(string $currency, ?string $on = null): ?ExchangeRate
    {
        $on = $on === null ? today()->toDateString() : Carbon::parse($on)->toDateString();

        return ExchangeRate::query()
            ->where('currency', strtoupper($currency))
            ->whereDate('effective_on', '<=', $on)
            ->orderByDesc('effective_on')
            ->first();
    }

    /**
     * Everything the frontend needs to format an amount and convert one for
     * display, for the shared Inertia prop.
     *
     * @return list<array{code: string, name: string, symbol: string, fraction_digits: int, rate: int, rate_on: string|null}>
     */
    public function displayCurrencies(): array
    {
        $base = $this->base();
        $today = today()->toDateString();
        $options = [];

        foreach ($this->currencies() as $code => $meta) {
            $code = strtoupper((string) $code);

            // The base currency first: it is the default everywhere, and a list
            // that opens on it reads as "dinars, or one of these". It is also
            // its own unit, so it has no rate and needs none.
            if ($code === $base) {
                array_unshift($options, [
                    'code' => $code,
                    'name' => $meta['name'],
                    'symbol' => $meta['symbol'],
                    'fraction_digits' => $meta['fraction_digits'],
                    'rate' => ExchangeRates::SCALE,
                    'rate_on' => null,
                ]);

                continue;
            }

            $row = $this->latestRowOn($code, $today);

            // A currency with no rate on record is not on offer. Showing it
            // would invite someone to type a price nothing could convert.
            if ($row === null) {
                continue;
            }

            $options[] = [
                'code' => $code,
                'name' => $meta['name'],
                'symbol' => $meta['symbol'],
                'fraction_digits' => $meta['fraction_digits'],
                'rate' => $row->rate,
                'rate_on' => $row->effective_on->toDateString(),
            ];
        }

        return $options;
    }

    /**
     * Write a fetched rate. Called by `SyncExchangeRatesAction` and by nothing
     * else — the argument is a decimal string because that is what the feed's
     * re-based figure is formatted to.
     */
    public function record(string $currency, string $rate, string $effectiveOn): ExchangeRate
    {
        $this->memoised = [];

        $currency = strtoupper($currency);
        $effectiveOn = Carbon::parse($effectiveOn)->toDateString();
        $rate = ExchangeRates::rateFromDecimal($rate);

        // Deliberately not `updateOrCreate`: its lookup would compare
        // `effective_on = '2026-08-08'` against the stored
        // '2026-08-08 00:00:00' and never match, so today's rate would be
        // inserted again every run until the unique index refused it.
        $existing = ExchangeRate::query()
            ->where('currency', $currency)
            ->whereDate('effective_on', $effectiveOn)
            ->first();

        if ($existing !== null) {
            $existing->update(['rate' => $rate]);

            return $existing;
        }

        return ExchangeRate::query()->create([
            'currency' => $currency,
            'effective_on' => $effectiveOn,
            'rate' => $rate,
        ]);
    }

    /**
     * The scaled rate for each foreign currency in force on the given date.
     *
     * @return array<string, int>
     */
    private function rateRowsOn(string $on): array
    {
        $rates = [];

        foreach (array_keys($this->currencies()) as $currency) {
            $currency = strtoupper((string) $currency);

            if ($currency === $this->base()) {
                continue;
            }

            $row = $this->latestRowOn($currency, $on);

            if ($row !== null) {
                $rates[$currency] = $row->rate;
            }
        }

        return $rates;
    }
}
