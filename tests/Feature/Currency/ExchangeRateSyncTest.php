<?php

use App\Actions\Currency\SyncExchangeRatesAction;
use App\Exceptions\ExchangeRateSyncFailedException;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use Illuminate\Support\Facades\Http;

/**
 * The published feed quotes everything against its own base — USD — so an IQD
 * figure of 1310 means 1,310 dinars to the dollar, which is exactly the rate
 * wanted once re-based.
 *
 * @param  array<string, float|int|string>  $rates
 */
function publishedRates(array $rates = ['USD' => 1, 'IQD' => 1310, 'EUR' => 0.92]): void
{
    Http::fake([
        '*' => Http::response([
            'result' => 'success',
            'base_code' => 'USD',
            'rates' => $rates,
        ]),
    ]);
}

it('records the published rate against the base currency', function () {
    publishedRates();

    $written = app(SyncExchangeRatesAction::class)->handle('2026-08-08');

    expect($written)->toHaveCount(1);

    $rate = ExchangeRate::query()->firstOrFail();

    expect($rate->currency)->toBe('USD')
        ->and($rate->decimalRate())->toBe('1310')
        ->and($rate->effective_on->toDateString())->toBe('2026-08-08');
});

it('re-bases a rate that does not divide evenly, to the rate\'s own precision', function () {
    publishedRates(['USD' => 1, 'IQD' => 1310.375]);

    app(SyncExchangeRatesAction::class)->handle('2026-08-08');

    expect(ExchangeRate::query()->firstOrFail()->decimalRate())->toBe('1310.375');
});

it('ignores currencies it is not configured for', function () {
    publishedRates(['USD' => 1, 'IQD' => 1310, 'GBP' => 0.78, 'JPY' => 155]);

    app(SyncExchangeRatesAction::class)->handle();

    expect(ExchangeRate::query()->pluck('currency')->all())->toBe(['USD']);
});

it('leaves existing rates alone when the service is unreachable', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => app(SyncExchangeRatesAction::class)->handle())
        ->toThrow(ExchangeRateSyncFailedException::class);

    expect(ExchangeRate::query()->count())->toBe(1)
        ->and(ExchangeRate::query()->firstOrFail()->decimalRate())->toBe('1320');
});

it('refuses a response with no usable rates in it', function () {
    Http::fake(['*' => Http::response(['result' => 'error'])]);

    expect(fn () => app(SyncExchangeRatesAction::class)->handle())
        ->toThrow(ExchangeRateSyncFailedException::class, 'did not return usable rates');

    expect(ExchangeRate::query()->count())->toBe(0);
});

it('refuses a feed that does not publish a currency it needs', function () {
    publishedRates(['USD' => 1, 'EUR' => 0.92]);

    expect(fn () => app(SyncExchangeRatesAction::class)->handle())
        ->toThrow(ExchangeRateSyncFailedException::class, 'does not publish IQD');

    expect(ExchangeRate::query()->count())->toBe(0);
});

it('replaces its own earlier figure for a day rather than piling them up', function () {
    // A sequence rather than two `Http::fake()` calls: stubs are additive, so a
    // second `*` stub never gets a look in behind the first.
    Http::fakeSequence()
        ->push(['result' => 'success', 'base_code' => 'USD', 'rates' => ['USD' => 1, 'IQD' => 1310]])
        ->push(['result' => 'success', 'base_code' => 'USD', 'rates' => ['USD' => 1, 'IQD' => 1315]]);

    app(SyncExchangeRatesAction::class)->handle('2026-08-08');
    app(SyncExchangeRatesAction::class)->handle('2026-08-08');

    expect(ExchangeRate::query()->count())->toBe(1)
        ->and(ExchangeRate::query()->firstOrFail()->decimalRate())->toBe('1315');
});

it('exposes the rate it recorded through the rates in force', function () {
    publishedRates(['USD' => 1, 'IQD' => 1310]);
    app(SyncExchangeRatesAction::class)->handle('2026-08-08');

    expect(app(CurrencyService::class)->ratesOn('2026-08-08')->rateFor('USD'))
        ->toBe(ExchangeRates::rateFromDecimal('1310'));
});

it('takes the newest rate on or before the date, not the newest overall', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();
    ExchangeRate::factory()->at('1450')->on('2026-06-01')->create();

    $currencies = app(CurrencyService::class);

    expect($currencies->latestRowOn('USD', '2026-03-01')?->decimalRate())->toBe('1320')
        ->and($currencies->latestRowOn('USD', '2026-06-01')?->decimalRate())->toBe('1450')
        ->and($currencies->latestRowOn('USD', '2025-12-31'))->toBeNull();
});

it('runs from the command line and says what it recorded', function () {
    publishedRates();

    $this->artisan('currency:sync')
        ->expectsOutputToContain('1310')
        ->assertSuccessful();

    expect(ExchangeRate::query()->count())->toBe(1);
});

it('reports a failed sync without changing anything', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('currency:sync')
        ->expectsOutputToContain('Existing rates are unchanged')
        ->assertFailed();

    expect(ExchangeRate::query()->count())->toBe(1);
});

it('never calls the rate service while serving a page', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    Http::fake();

    // Rates come from the table, never the network. A page that reached out
    // would go down whenever the feed did, and slow down for everyone when it
    // was merely sluggish.
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk();

    $endpoint = (string) config('money.rates.endpoint');

    Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), $endpoint));
});
