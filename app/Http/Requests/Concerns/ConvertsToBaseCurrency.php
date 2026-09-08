<?php

namespace App\Http\Requests\Concerns;

use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Converts amounts as typed into the base currency, once.
 *
 * This is the ONLY place in the application that converts currency on the way
 * in. Everything past a Form Request — Actions, Services, Models, report Queries,
 * CSV — deals in base-currency minor units and does not know that another
 * currency exists. That is what keeps a report's figures comparable and the FIFO
 * ledger currency-blind.
 *
 * Every money field may carry a sibling `{field}_currency` key, so one field on
 * a form can be typed in dollars while its neighbours stay in dinars. A field
 * with no currency of its own follows the document's.
 *
 * Rates are read as at the document's own date — the same anchoring the report
 * queries use — so a back-dated invoice converts at the rate that was in force
 * when it was written, not today's.
 */
trait ConvertsToBaseCurrency
{
    private ?ExchangeRates $exchangeRates = null;

    /**
     * The date to read rates at. Requests for dated documents override this;
     * anything undated (a product's price) converts at today's rate.
     */
    protected function currencyDate(): ?string
    {
        return null;
    }

    /**
     * The request field naming the document's currency.
     */
    protected function currencyKey(): string
    {
        return 'currency';
    }

    public function baseCurrency(): string
    {
        return $this->rates()->base;
    }

    /**
     * The currencies an amount on this request may be entered in: the base
     * currency, plus any with a rate in force on the document's date.
     *
     * Validate every currency field against this. A currency nobody has recorded
     * a rate for then cannot be submitted at all, which is why
     * `MissingExchangeRateException` is an internal guard and not an error
     * message users can provoke.
     *
     * @return list<string>
     */
    public function enterableCurrencies(): array
    {
        return $this->rates()->currencies();
    }

    /**
     * The currency the money changed hands in, and the default for every amount
     * on the document.
     */
    public function documentCurrency(): string
    {
        $currency = strtoupper((string) $this->input($this->currencyKey(), ''));

        return $this->rates()->has($currency) ? $currency : $this->baseCurrency();
    }

    /**
     * The scaled rate applied to this document, recorded alongside it so the
     * question "what rate did we use?" stays answerable.
     */
    public function documentRate(): int
    {
        return $this->rates()->rateFor($this->documentCurrency());
    }

    /**
     * A top-level money field as a base-currency decimal string.
     *
     * Returns null for a blank optional amount, matching what the `Money` cast
     * stores for "no figure" as opposed to zero.
     */
    protected function baseMoney(string $key, ?string $default = null): ?string
    {
        if (! $this->filled($key)) {
            return $default;
        }

        return $this->toBase(
            (string) $this->input($key),
            (string) $this->input("{$key}_currency", '')
        );
    }

    /**
     * A money field inside a line or cost row, as a base-currency decimal
     * string. Blanks fall back to `$default` — a missing discount is zero.
     *
     * @param  array<string, mixed>  $row
     */
    protected function baseMoneyIn(array $row, string $key, string $default = '0'): string
    {
        $amount = (string) ($row[$key] ?? '');

        if (trim($amount) === '') {
            $amount = $default;
        }

        return $this->toBase($amount, (string) ($row["{$key}_currency"] ?? ''));
    }

    /**
     * Convert one typed amount. An amount already in the base currency passes
     * through untouched, so the common case never rounds at all.
     */
    private function toBase(string $amount, string $currency): string
    {
        $currency = $currency === '' ? $this->documentCurrency() : strtoupper($currency);

        if (! $this->rates()->has($currency)) {
            $currency = $this->documentCurrency();
        }

        return $this->rates()->toBase(Money::fromDecimal($amount), $currency)->toDecimal();
    }

    /**
     * A date field, only if it reads as one.
     *
     * `rules()` runs before validation, so the date naming the rates to use may
     * still be missing or nonsense at that point. Falling back to today keeps
     * the currency whitelist sane; the date's own rule reports the real problem.
     */
    protected function dateOrNull(string $key): ?string
    {
        try {
            return $this->filled($key)
                ? Carbon::parse((string) $this->input($key))->toDateString()
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function rates(): ExchangeRates
    {
        return $this->exchangeRates ??= app(CurrencyService::class)->ratesOn($this->currencyDate());
    }
}
