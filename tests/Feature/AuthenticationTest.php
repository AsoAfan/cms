<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the login screen to guests', function () {
    get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login')->where('canResetPassword', true));
});

it('logs a user in with the right credentials', function () {
    $user = User::factory()->create();

    post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    post('/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    assertGuest();
});

it('rejects an unknown email', function () {
    post('/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    assertGuest();
});

it('requires an email and a password', function () {
    post('/login', [])->assertSessionHasErrors(['email', 'password']);
});

it('throttles repeated failed attempts', function () {
    Event::fake([Lockout::class]);

    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // The sixth attempt is refused before the credentials are even checked,
    // so a correct password does not get through either.
    post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    Event::assertDispatched(Lockout::class);
    assertGuest();
});

it('clears the throttle once a user gets in', function () {
    $user = User::factory()->create();

    post('/login', ['email' => $user->email, 'password' => 'wrong']);
    post('/login', ['email' => $user->email, 'password' => 'password']);

    assertAuthenticated();
    expect(RateLimiter::attempts(mb_strtolower($user->email).'|127.0.0.1'))->toBe(0);
});

it('logs a user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    assertGuest();
});

it('keeps signed-in users away from the login screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/dashboard');
});

it('has no registration route', function () {
    get('/register')->assertNotFound();
    post('/register', [])->assertNotFound();
});
