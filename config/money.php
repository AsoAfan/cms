<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | Every amount in the system is STORED as a whole number of minor units of
    | this currency (see App\Support\Money). Reports, the stock ledger and every
    | derived figure are in it too, and none of them know that other currencies
    | exist.
    |
    | Foreign currency lives only at the two edges: an amount may be TYPED in
    | one (converted to base once, in the Form Request) and the whole UI may be
    | VIEWED in one (converted for display only). Changing this value re-labels
    | existing amounts under a different currency; it does not convert them.
    |
    */

    'currency' => env('APP_CURRENCY', 'IQD'),

    /*
    |--------------------------------------------------------------------------
    | Formatting Locale
    |--------------------------------------------------------------------------
    |
    | Drives `Intl.NumberFormat` through the `formatMoney` TypeScript helper.
    | Deliberately `en-US` rather than `en-IQ`: what is wanted from a locale
    | here is Latin digits and comma thousands separators, which is what every
    | figure in this application has always been read in.
    |
    */

    'locale' => env('APP_CURRENCY_LOCALE', 'en-US'),

    /*
    |--------------------------------------------------------------------------
    | Known Currencies
    |--------------------------------------------------------------------------
    |
    | The currencies an amount may be entered or viewed in. The base currency
    | must appear here. `fraction_digits` is a DISPLAY concern only — storage is
    | always two decimal places, whatever a currency is conventionally quoted
    | in, so that splitting a landed cost stays exact.
    |
    | Dinars are not quoted in fils in practice, hence 0 for IQD. A converted
    | amount may still hold a fraction of a dinar internally; it is simply never
    | shown.
    |
    */

    'currencies' => [

        'IQD' => [
            'name' => 'Iraqi dinar',
            'symbol' => 'IQD',
            'fraction_digits' => 0,
        ],

        'USD' => [
            'name' => 'US dollar',
            'symbol' => '$',
            'fraction_digits' => 2,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Rates are read from the `exchange_rates` table and nowhere else. This
    | endpoint is called only by the scheduled `currency:sync` command and the
    | Sync button on the rates screen — never during ordinary page requests.
    |
    | open.er-api.com is free, needs no key and publishes IQD. The ECB feed
    | (Frankfurter) does not, which rules it out for this application.
    |
    */

    'rates' => [

        'endpoint' => env('EXCHANGE_RATE_ENDPOINT', 'https://open.er-api.com/v6/latest/USD'),

        'timeout' => (int) env('EXCHANGE_RATE_TIMEOUT', 10),

    ],

];
