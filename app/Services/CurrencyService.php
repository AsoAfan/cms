<?php

namespace App\Services;

use App\Exceptions\CurrencyInUseException;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Support\ExchangeRates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the application knows about currency, in one place.
 *
 * Which currencies exist, and which one the books are kept in, are rows in
 * `currencies` rather than lines in a config file — they are facts about a
 * business, and they change when a supplier starts invoicing in something new.
 *
 * Exactly one currency is the base, and every monetary column in the
 * application is minor units of it. Rates are entered by hand on
 * Settings → Exchange rates; nothing here reaches for the network, because the
 * official rate and the rate a business actually trades at are rarely the same
 * number and it is the second one that costs an invoice correctly.
 *
 * Conversion itself belongs to the {@see ExchangeRates} value object this hands
 * out, which is framework-free and unit-testable without a database.
 */
final class CurrencyService
{
    /**
     * Rates already looked up in this request, keyed by date.
     *
     * A purchase form posts many amounts on one date, and every one of them
     * would otherwise repeat the same queries. Registered as a singleton in
     * `AppServiceProvider` so the cache lasts the request.
     *
     * @var array<string, ExchangeRates>
     */
    private array $memoisedRates = [];

    /**
     * @var array<string, Currency>|null
     */
    private ?array $memoisedCurrencies = null;

    /**
     * The currency the books are kept in.
     *
     * Falls back to `config('money.currency')` only before the first currency
     * row exists — during a fresh migration, say, when a document's column
     * default is being resolved and the seeder has not run yet.
     */
    public function base(): string
    {
        foreach ($this->all() as $currency) {
            if ($currency->is_base) {
                return $currency->code;
            }
        }

        return strtoupper((string) config('money.currency'));
    }

    public function baseCurrency(): ?Currency
    {
        return $this->all()[$this->base()] ?? null;
    }

    public function locale(): string
    {
        return (string) config('money.locale');
    }

    /**
     * Every currency on record, keyed by code, base first.
     *
     * @return array<string, Currency>
     */
    public function all(): array
    {
        if ($this->memoisedCurrencies !== null) {
            return $this->memoisedCurrencies;
        }

        $currencies = Currency::query()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get()
            ->keyBy('code')
            ->all();

        /** @var array<string, Currency> $currencies */
        return $this->memoisedCurrencies = $currencies;
    }

    /**
     * Anything already looked up is thrown away, because the answers have
     * changed. Called by every write below.
     */
    public function forget(): void
    {
        $this->memoisedCurrencies = null;
        $this->memoisedRates = [];
    }

    /**
     * The currencies an amount may actually be entered in on a given date: the
     * base currency, plus any with a rate in force.
     *
     * Form Requests validate against this, so a currency nobody has recorded a
     * rate for can never be submitted and `MissingExchangeRateException` stays
     * an internal guard rather than a 500 a user can trigger.
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
     * rate stands until a newer one is recorded.
     */
    public function ratesOn(?string $on = null): ExchangeRates
    {
        $on = $this->asDate($on);

        return $this->memoisedRates[$on] ??= ExchangeRates::for(
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
        return ExchangeRate::query()
            ->where('currency', strtoupper($currency))
            ->whereDate('effective_on', '<=', $this->asDate($on))
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

        foreach ($this->all() as $currency) {
            if ($currency->code === $base) {
                // The base is its own unit, so it has no rate and needs none.
                $options[] = [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'fraction_digits' => $currency->fraction_digits,
                    'rate' => ExchangeRates::SCALE,
                    'rate_on' => null,
                ];

                continue;
            }

            $row = $this->latestRowOn($currency->code, $today);

            // A currency with no rate on record is not on offer. Showing it
            // would invite someone to type a price nothing could convert.
            if ($row === null) {
                continue;
            }

            $options[] = [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'fraction_digits' => $currency->fraction_digits,
                'rate' => $row->rate,
                'rate_on' => $row->effective_on->toDateString(),
            ];
        }

        return $options;
    }

    /**
     * Record what a currency is worth in the base currency, from a date.
     *
     * The rate is a decimal string as it reads on the form: "1320.5".
     */
    public function record(string $currency, string $rate, string $effectiveOn): ExchangeRate
    {
        $this->forget();

        $currency = strtoupper($currency);
        $effectiveOn = $this->asDate($effectiveOn);
        $rate = ExchangeRates::rateFromDecimal($rate);

        // Deliberately not `updateOrCreate`: its lookup would compare
        // `effective_on = '2026-08-08'` against the stored
        // '2026-08-08 00:00:00' and never match, so a rate would be inserted
        // again on every save until the unique index refused it.
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
     * Add a currency this business deals in.
     */
    public function add(string $code, string $name, string $symbol, int $fractionDigits): Currency
    {
        $this->forget();

        return Currency::query()->create([
            'code' => strtoupper($code),
            'name' => $name,
            'symbol' => $symbol,
            'fraction_digits' => $fractionDigits,
            // The very first currency is the base by default: books have to be
            // kept in something.
            'is_base' => Currency::query()->count() === 0,
        ]);
    }

    /**
     * Move the base currency.
     *
     * This re-denominates the books. Every stored amount is minor units of the
     * base, and each was recorded at a rate that was current when it happened —
     * so there is no single rate that could restate the history correctly, and
     * converting at today's would quietly rewrite what past invoices cost.
     *
     * It is therefore only allowed while there is no money on record. After
     * that the answer is a new set of books, not a setting.
     *
     * Rates go with it: they were quotes against the old base and mean nothing
     * against the new one.
     *
     * @throws CurrencyInUseException
     */
    public function makeBase(Currency $currency): void
    {
        if ($currency->is_base) {
            return;
        }

        if ($this->hasRecordedMoney()) {
            throw CurrencyInUseException::cannotChangeBase($this->base());
        }

        $this->forget();

        DB::transaction(function () use ($currency): void {
            Currency::query()->where('is_base', true)->update(['is_base' => false]);
            $currency->update(['is_base' => true]);

            // Every rate quoted the old base. None of them is true of the new
            // one, and a stale rate is worse than a missing one.
            ExchangeRate::query()->delete();
        });
    }

    /**
     * Remove a currency, and every rate quoted for it.
     *
     * @throws CurrencyInUseException
     */
    public function remove(Currency $currency): void
    {
        if ($currency->is_base) {
            throw CurrencyInUseException::isBase($currency->code);
        }

        if ($this->isOnADocument($currency->code)) {
            throw CurrencyInUseException::isOnADocument($currency->code);
        }

        $this->forget();

        // Rates cascade with it — see the exchange_rates migration.
        $currency->delete();
    }

    /**
     * Whether anything financial has been recorded yet.
     *
     * Purchases, sales and expenses are the three documents that carry an
     * amount. If none exists, the books are empty and the base is still a
     * setup decision rather than a restatement.
     */
    public function hasRecordedMoney(): bool
    {
        return Purchase::query()->exists()
            || Sale::query()->exists()
            || Expense::query()->exists();
    }

    /**
     * Whether any document was written in this currency.
     */
    public function isOnADocument(string $code): bool
    {
        $code = strtoupper($code);

        return Purchase::query()->where('currency', $code)->exists()
            || Sale::query()->where('currency', $code)->exists()
            || Expense::query()->where('currency', $code)->exists();
    }

    /**
     * The scaled rate for each foreign currency in force on the given date.
     *
     * @return array<string, int>
     */
    private function rateRowsOn(string $on): array
    {
        $base = $this->base();
        $rates = [];

        foreach ($this->all() as $currency) {
            if ($currency->code === $base) {
                continue;
            }

            $row = $this->latestRowOn($currency->code, $on);

            if ($row !== null) {
                $rates[$currency->code] = $row->rate;
            }
        }

        return $rates;
    }

    private function asDate(?string $on): string
    {
        return $on === null
            ? today()->toDateString()
            : Carbon::parse($on)->toDateString();
    }
}
