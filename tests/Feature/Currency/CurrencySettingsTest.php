<?php

use App\Enums\PaymentMethod;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CurrencyService;

/*
|--------------------------------------------------------------------------
| Which currencies this business deals in
|--------------------------------------------------------------------------
|
| Currencies are rows, not config. Exactly one is the base, and every monetary
| column in the application is minor units of it — which is precisely why the
| base can only move while the books are empty.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Seeded for every feature test — see tests/Pest.php.
    $this->base = Currency::query()->where('code', 'IQD')->firstOrFail();
    $this->dollar = Currency::query()->where('code', 'USD')->firstOrFail();
});

it('lists every currency with what it is worth, base first', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    $this->get('/settings/exchange-rates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/exchange-rates')
            ->where('base', 'IQD')
            ->has('currencies', 2)
            ->where('currencies.0.code', 'IQD')
            ->where('currencies.0.is_base', true)
            ->where('currencies.0.rate', null)
            ->where('currencies.1.code', 'USD')
            ->where('currencies.1.is_base', false)
            ->where('currencies.1.rate', '1320')
            ->where('canChangeBase', true)
        );
});

it('adds a currency', function () {
    $this->post('/settings/currencies', [
        'code' => 'eur',
        'name' => 'Euro',
        'symbol' => '€',
        'fraction_digits' => 2,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $currency = Currency::query()->where('code', 'EUR')->firstOrFail();

    expect($currency->name)->toBe('Euro')
        ->and($currency->symbol)->toBe('€')
        ->and($currency->fraction_digits)->toBe(2)
        // Only the first currency is the base, and there already is one.
        ->and($currency->is_base)->toBeFalse();
});

it('refuses a code that is not three letters', function (string $code) {
    $this->post('/settings/currencies', [
        'code' => $code,
        'name' => 'Something',
        'symbol' => 'S',
        'fraction_digits' => 2,
    ])->assertSessionHasErrors('code');

    expect(Currency::query()->count())->toBe(2);
})->with(['EU', 'EURO', 'E1R', '']);

it('refuses a currency already on the list', function () {
    $this->post('/settings/currencies', [
        'code' => 'USD',
        'name' => 'US dollar again',
        'symbol' => '$',
        'fraction_digits' => 2,
    ])->assertSessionHasErrors('code');

    expect(Currency::query()->where('code', 'USD')->count())->toBe(1);
});

it('makes the first currency the base, because books need one', function () {
    Currency::query()->delete();

    $this->post('/settings/currencies', [
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'fraction_digits' => 2,
    ])->assertSessionHasNoErrors();

    expect(Currency::query()->firstOrFail()->is_base)->toBeTrue();
});

it('offers a new currency for entry only once it has a rate', function () {
    $currencies = app(CurrencyService::class);

    expect($currencies->enterable())->toBe(['IQD']);

    $currencies->record('USD', '1320', today()->toDateString());

    expect(app(CurrencyService::class)->enterable())->toBe(['IQD', 'USD']);
});

/*
|--------------------------------------------------------------------------
| Moving the base
|--------------------------------------------------------------------------
*/

it('moves the base while the books are empty', function () {
    $this->post("/settings/currencies/{$this->dollar->id}/default")
        ->assertRedirect();

    expect($this->dollar->fresh()->is_base)->toBeTrue()
        ->and($this->base->fresh()->is_base)->toBeFalse()
        ->and(app(CurrencyService::class)->base())->toBe('USD');
});

it('throws away rates when the base moves, because they quoted the old one', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    $this->post("/settings/currencies/{$this->dollar->id}/default");

    expect(ExchangeRate::query()->count())->toBe(0);
});

it('refuses to move the base once there is money on record', function () {
    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'payment_method' => PaymentMethod::Cash,
    ]);

    $this->post("/settings/currencies/{$this->dollar->id}/default")
        ->assertRedirect();

    // Every stored amount is minor units of the base, each recorded at a rate
    // current at the time. No single rate could restate that history.
    expect($this->base->fresh()->is_base)->toBeTrue()
        ->and($this->dollar->fresh()->is_base)->toBeFalse();
});

it('says the base is fixed once money is recorded', function () {
    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'payment_method' => PaymentMethod::Cash,
    ]);

    $this->get('/settings/exchange-rates')
        ->assertInertia(fn ($page) => $page->where('canChangeBase', false));
});

/*
|--------------------------------------------------------------------------
| Removing a currency
|--------------------------------------------------------------------------
*/

it('removes a currency and every rate quoted for it', function () {
    ExchangeRate::factory()->at('1320')->on('2026-01-01')->create();

    $this->delete("/settings/currencies/{$this->dollar->id}")->assertRedirect();

    expect(Currency::query()->where('code', 'USD')->exists())->toBeFalse()
        ->and(ExchangeRate::query()->count())->toBe(0);
});

it('refuses to remove the currency the books are kept in', function () {
    $this->delete("/settings/currencies/{$this->base->id}")->assertRedirect();

    expect(Currency::query()->where('code', 'IQD')->exists())->toBeTrue();
});

it('refuses to remove a currency named on a document', function () {
    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'payment_method' => PaymentMethod::Cash,
        'currency' => 'USD',
    ]);

    $this->delete("/settings/currencies/{$this->dollar->id}")->assertRedirect();

    expect(Currency::query()->where('code', 'USD')->exists())->toBeTrue();
});

it('marks a currency in use so the screen can say why it cannot go', function () {
    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'payment_method' => PaymentMethod::Cash,
        'currency' => 'USD',
    ]);

    $this->get('/settings/exchange-rates')
        ->assertInertia(fn ($page) => $page
            ->where('currencies.1.code', 'USD')
            ->where('currencies.1.in_use', true)
        );
});

it('keeps guests out of currency settings', function () {
    auth()->logout();

    $this->post('/settings/currencies')->assertRedirect('/login');
    $this->post("/settings/currencies/{$this->dollar->id}/default")->assertRedirect('/login');
    $this->delete("/settings/currencies/{$this->dollar->id}")->assertRedirect('/login');
});
