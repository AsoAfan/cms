<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a dollar is worth in dinars.
 *
 * The rate is entered here and nowhere else. A published figure was fetched on a
 * schedule for a while and removed: the official rate and the rate this business
 * actually trades at are rarely the same number, and it is the second one that
 * costs an invoice correctly. Nothing in the application reaches for the network.
 *
 * A rate is dated from when it applies, so a document converts at the rate that
 * was in force on its own day and entering old paperwork never rewrites history.
 */
class ExchangeRateController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(): Response
    {
        $table = $this->table(ExchangeRate::query())
            ->sortable(['effective_on', 'currency', 'rate'], default: 'effective_on', direction: 'desc')
            ->filterable(['currency']);

        $paginator = $table->paginate();

        $paginator->through(fn (ExchangeRate $rate): array => [
            'id' => $rate->id,
            'currency' => $rate->currency,
            'rate' => $rate->decimalRate(),
            'effective_on' => $rate->effective_on->toDateString(),
        ]);

        return Inertia::render('settings/exchange-rates', [
            'rows' => $paginator,
            'table' => $table->state(),
            'base' => $this->currencies->base(),
            // Everything this business deals in, with what each is worth today —
            // which is what every money field on every other screen is
            // converting at right now.
            'currencies' => $this->currencyList(),
            // Whether the base can still move. Once there is money on record it
            // cannot, so the screen says so rather than offering a button that
            // only ever refuses.
            'canChangeBase' => ! $this->currencies->hasRecordedMoney(),
        ]);
    }

    public function store(ExchangeRateRequest $request): RedirectResponse
    {
        $rate = $this->currencies->record(
            currency: $request->currency(),
            rate: $request->rate(),
            effectiveOn: $request->effectiveOn(),
        );

        Flash::success(sprintf(
            '1 %s is %s %s from %s.',
            $rate->currency,
            $rate->decimalRate(),
            $this->currencies->base(),
            $rate->effective_on->toDateString(),
        ));

        return back();
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $currency = $exchangeRate->currency;
        $on = $exchangeRate->effective_on->toDateString();

        // Deleting a rate does not touch a document that used it: the rate it
        // was converted at is recorded on the document itself.
        $exchangeRate->delete();

        Flash::success("Removed the {$currency} rate from {$on}.");

        return back();
    }

    /**
     * Every currency on record, base first, with the rate in force today.
     *
     * The base has no rate — it is its own unit — and `in_use` is what decides
     * whether the screen offers to remove one.
     *
     * @return list<array{id: int, code: string, name: string, symbol: string, fraction_digits: int, is_base: bool, in_use: bool, rate: string|null, effective_on: string|null}>
     */
    private function currencyList(): array
    {
        $currencies = [];

        foreach ($this->currencies->all() as $currency) {
            $row = $currency->is_base ? null : $this->currencies->latestRowOn($currency->code);

            $currencies[] = [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'fraction_digits' => $currency->fraction_digits,
                'is_base' => $currency->is_base,
                'in_use' => $this->currencies->isOnADocument($currency->code),
                'rate' => $row?->decimalRate(),
                'effective_on' => $row?->effective_on->toDateString(),
            ];
        }

        return $currencies;
    }
}
