<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Opening Base Currency
    |--------------------------------------------------------------------------
    |
    | Which currencies exist, and which one the books are kept in, live in the
    | `currencies` table — they are facts about a business, not about a
    | deployment. Manage them on Settings → Exchange rates.
    |
    | This value is only the code the seeder opens the books in, and the fallback
    | `CurrencyService::base()` uses before the first currency row exists.
    | Changing it does not move the base of an application already in use; that
    | is `CurrencyService::makeBase()`, and it is refused once money is recorded.
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
    | Opening Currencies
    |--------------------------------------------------------------------------
    |
    | Seeded on a fresh install so there is something to trade in on day one.
    | Add, remove and re-base from the settings screen after that — this array
    | is never read again.
    |
    | `fraction_digits` is a DISPLAY concern only. Storage is always two decimal
    | places, whatever a currency is conventionally quoted in, so that splitting
    | a landed cost stays exact. Dinars are not quoted in fils, hence 0.
    |
    */

    'seed_currencies' => [

        ['code' => 'IQD', 'name' => 'Iraqi dinar', 'symbol' => 'IQD', 'fraction_digits' => 0],
        ['code' => 'USD', 'name' => 'US dollar', 'symbol' => '$', 'fraction_digits' => 2],

    ],

];
