<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the forgot-password screen', function () {
    get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/forgot-password'));
});

it('emails a reset link', function () {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an address has an account', function () {
    Notification::fake();

    post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('shows the reset screen for a token', function () {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        get("/reset-password/{$notification->token}?email=".urlencode($user->email))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/reset-password')
                ->where('token', $notification->token)
                ->where('email', $user->email)
            );

        return true;
    });
});

it('resets the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasNoErrors()->assertRedirect('/login');

        return true;
    });

    post('/login', ['email' => $user->email, 'password' => 'a-brand-new-password'])
        ->assertRedirect('/dashboard');
});

it('rejects an invalid token', function () {
    $user = User::factory()->create();

    post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');
});

it('requires the password to be confirmed', function () {
    $user = User::factory()->create();

    post('/reset-password', [
        'token' => 'whatever',
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');
});
