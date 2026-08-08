<?php

namespace App\Http\Middleware;

use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'currency' => $this->currency($request),
            // Read back the cookie the sidebar sets so the very first paint
            // matches the state the user left it in.
            'sidebarOpen' => $request->cookie('sidebar_state') !== 'false',
            'appearance' => $this->appearance($request),
        ];
    }

    /**
     * Everything the frontend needs to format an amount, convert one for
     * display, and offer the currency switcher on a money field.
     *
     * `base` is what every amount in every prop is denominated in; `display` is
     * only what the user has asked to look at. Amounts are never converted
     * before they are sent — a figure on the wire is always base currency, and
     * conversion for display happens in `useFormatMoney()`.
     *
     * @return array{base: string, display: string, locale: string, currencies: list<array{code: string, name: string, symbol: string, fraction_digits: int, rate: int, rate_on: string|null}>}
     */
    private function currency(Request $request): array
    {
        $currencies = $this->currencies->displayCurrencies();

        return [
            'base' => $this->currencies->base(),
            'display' => $this->displayCurrency($request, $currencies),
            'locale' => $this->currencies->locale(),
            'currencies' => $currencies,
        ];
    }

    /**
     * The currency the user has asked to read figures in, from the cookie the
     * switcher sets.
     *
     * Whitelisted against what is actually on offer rather than echoed back out
     * of the request — and it falls back to the base currency, so a stale cookie
     * naming a currency whose rate has since been removed shows dinars rather
     * than nothing.
     *
     * @param  list<array{code: string, name: string, symbol: string, fraction_digits: int, rate: int, rate_on: string|null}>  $currencies
     */
    private function displayCurrency(Request $request, array $currencies): string
    {
        $display = $request->cookie('display_currency');

        if (! is_string($display)) {
            return $this->currencies->base();
        }

        $display = strtoupper($display);

        return in_array($display, array_column($currencies, 'code'), true)
            ? $display
            : $this->currencies->base();
    }

    /**
     * The theme the user picked, read back from the cookie the appearance
     * toggle sets. The root template stamps it on <html> so the first paint
     * already matches, which is also why the value is whitelisted here
     * rather than echoed straight out of the request.
     *
     * @return 'light'|'dark'|'system'
     */
    private function appearance(Request $request): string
    {
        $appearance = $request->cookie('appearance');

        return in_array($appearance, ['light', 'dark'], true) ? $appearance : 'system';
    }
}
