<?php

use App\Models\User;

use function Pest\Laravel\withUnencryptedCookie;

it('defaults to the system theme when no cookie has been set', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('appearance', 'system'));
});

it('reads the theme back from the cookie the frontend writes', function (string $appearance) {
    withUnencryptedCookie('appearance', $appearance)
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('appearance', $appearance));
})->with(['light', 'dark', 'system']);

it('falls back to the system theme rather than trusting an unknown cookie', function () {
    withUnencryptedCookie('appearance', '"><script>alert(1)</script>')
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('appearance', 'system'));
});

it('stamps the chosen theme on the root element so the first paint matches', function () {
    withUnencryptedCookie('appearance', 'dark')
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertSee('data-appearance="dark"', escape: false)
        ->assertSee('class="dark"', escape: false);
});

it('leaves the dark class off for light and system, which the script resolves', function (string $appearance) {
    withUnencryptedCookie('appearance', $appearance)
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertSee("data-appearance=\"{$appearance}\"", escape: false)
        ->assertDontSee('class="dark"', escape: false);
})->with(['light', 'system']);

it('shares the theme with guests so the sign-in screen can switch too', function () {
    withUnencryptedCookie('appearance', 'dark')
        ->get('/login')
        ->assertInertia(fn ($page) => $page->where('appearance', 'dark'));
});

it('keeps the sidebar cookie readable, which cookie encryption would otherwise eat', function () {
    withUnencryptedCookie('sidebar_state', 'false')
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('sidebarOpen', false));
});
