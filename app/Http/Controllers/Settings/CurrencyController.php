<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\CurrencyInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CurrencyRequest;
use App\Models\Currency;
use App\Services\CurrencyService;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * The currencies this business deals in, and which one it keeps its books in.
 *
 * They live beside the exchange rates because they are the same question asked
 * twice: which money do we handle, and what is it worth.
 */
class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function store(CurrencyRequest $request): RedirectResponse
    {
        $currency = $this->currencies->add(
            code: $request->code(),
            name: $request->name(),
            symbol: $request->symbol(),
            fractionDigits: $request->fractionDigits(),
        );

        Flash::success($currency->is_base
            ? "{$currency->code} added, and your books are now kept in it."
            : "{$currency->code} added. Record what it is worth to start using it.");

        return back();
    }

    /**
     * Keep the books in this currency instead.
     *
     * Refused once there is money on record — see
     * `CurrencyService::makeBase()` for why no rate could restate the history.
     */
    public function makeDefault(Currency $currency): RedirectResponse
    {
        try {
            $this->currencies->makeBase($currency);
        } catch (CurrencyInUseException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("Your books are now kept in {$currency->code}. Record what the others are worth against it.");

        return back();
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        try {
            $this->currencies->remove($currency);
        } catch (CurrencyInUseException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$currency->code} removed.");

        return back();
    }
}
