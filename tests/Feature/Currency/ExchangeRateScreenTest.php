<?php

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the rate in force for each currency', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();
    ExchangeRate::factory()->at('1450')->on('2026-06-01')->create();

    $this->get('/settings/exchange-rates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/exchange-rates')
            ->where('base', 'IQD')
            ->where('currencies.1.code', 'USD')
            // The newest, not the first or the last written.
            ->where('currencies.1.rate', '1450')
            ->where('currencies.1.effective_on', '2026-06-01')
            ->has('rows.data', 2)
        );
});

it('says plainly when there is no rate on record at all', function () {
    $this->get('/settings/exchange-rates')
        ->assertInertia(fn ($page) => $page
            ->where('currencies.1.code', 'USD')
            ->where('currencies.1.rate', null)
            ->has('rows.data', 0)
        );
});

it('records a rate', function () {
    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => '1450.5',
        'effective_on' => '2026-08-08',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $rate = ExchangeRate::query()->firstOrFail();

    expect($rate->currency)->toBe('USD')
        ->and($rate->decimalRate())->toBe('1450.5')
        ->and($rate->effective_on->toDateString())->toBe('2026-08-08');
});

it('corrects a rate rather than recording a second one for the same day', function () {
    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => '1450',
        'effective_on' => '2026-08-08',
    ]);

    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => '1460',
        'effective_on' => '2026-08-08',
    ])->assertSessionHasNoErrors();

    expect(ExchangeRate::query()->count())->toBe(1)
        ->and(ExchangeRate::query()->firstOrFail()->decimalRate())->toBe('1460');
});

it('makes a recorded rate the one prices convert at', function () {
    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => '1450',
        'effective_on' => '2026-08-08',
    ]);

    expect(app(CurrencyService::class)->ratesOn('2026-08-08')->rateFor('USD'))
        ->toBe(ExchangeRates::rateFromDecimal('1450'));
});

it('takes the newest rate on or before the date, not the newest overall', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();
    ExchangeRate::factory()->at('1450')->on('2026-06-01')->create();

    $currencies = app(CurrencyService::class);

    expect($currencies->latestRowOn('USD', '2026-03-01')?->decimalRate())->toBe('1320')
        ->and($currencies->latestRowOn('USD', '2026-06-01')?->decimalRate())->toBe('1450')
        ->and($currencies->latestRowOn('USD', '2025-12-31'))->toBeNull();
});

it('refuses a rate for the base currency, which is its own unit', function () {
    $this->post('/settings/exchange-rates', [
        'currency' => 'IQD',
        'rate' => '1',
        'effective_on' => '2026-08-08',
    ])->assertSessionHasErrors('currency');

    expect(ExchangeRate::query()->count())->toBe(0);
});

it('refuses a rate of nothing', function (string $rate) {
    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => $rate,
        'effective_on' => '2026-08-08',
    ])->assertSessionHasErrors('rate');

    expect(ExchangeRate::query()->count())->toBe(0);
})->with(['0', '-1450', '']);

it('refuses a rate with more precision than it can hold', function () {
    $this->post('/settings/exchange-rates', [
        'currency' => 'USD',
        'rate' => '1450.1234567',
        'effective_on' => '2026-08-08',
    ])->assertSessionHasErrors('rate');
});

it('removes a rate without disturbing what it already converted', function () {
    $rate = ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    $this->delete("/settings/exchange-rates/{$rate->id}")->assertRedirect();

    expect(ExchangeRate::query()->count())->toBe(0);
});

it('keeps guests out of the rates screen', function () {
    auth()->logout();

    $this->get('/settings/exchange-rates')->assertRedirect('/login');
    $this->post('/settings/exchange-rates')->assertRedirect('/login');
});
