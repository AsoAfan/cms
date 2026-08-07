<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Currency
    |--------------------------------------------------------------------------
    |
    | Every amount in the system is stored as a whole number of minor units in
    | this single currency (see App\Support\Money). There is deliberately no
    | per-row currency column; when multi-currency is genuinely needed it will
    | arrive as a schema change rather than as a config toggle.
    |
    | The code and locale are display concerns only — they are shared with the
    | frontend and drive the `formatMoney` helper. Changing them re-renders
    | existing amounts under a different symbol; it does not convert them.
    |
    */

    'currency' => env('APP_CURRENCY', 'USD'),

    'locale' => env('APP_CURRENCY_LOCALE', 'en-US'),

];
