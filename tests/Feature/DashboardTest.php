<?php

use App\Models\User;

use function Pest\Laravel\get;

it('sends guests to the login screen', function () {
    get('/dashboard')->assertRedirect('/login');
});

it('shows the dashboard to a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

it('sends the root url to the dashboard', function () {
    get('/')->assertRedirect('/dashboard');
});

it('shares the currency and the signed-in user with every page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('currency.base', config('money.currency'))
            // Figures are read in the base currency until someone says
            // otherwise, which is the whole point of a base currency.
            ->where('currency.display', config('money.currency'))
            ->where('currency.locale', config('money.locale'))
            ->has('currency.currencies')
            ->has('sidebarOpen')
        );
});

it('never leaks the password hash to the frontend', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->missing('auth.user.password'));
});
