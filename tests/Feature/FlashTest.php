<?php

use App\Enums\FlashType;
use App\Models\User;
use App\Support\Flash;
use Inertia\Support\SessionKey;

use function Pest\Laravel\post;

it('queues a message at each severity', function (string $method, FlashType $type) {
    Flash::{$method}('Something happened.');

    expect(session(SessionKey::FLASH_DATA))->toBe([
        Flash::KEY => ['type' => $type->value, 'message' => 'Something happened.'],
    ]);
})->with([
    ['success', FlashType::Success],
    ['error', FlashType::Error],
    ['warning', FlashType::Warning],
    ['info', FlashType::Info],
]);

it('keeps only the most recent message for a key', function () {
    Flash::info('First.');
    Flash::error('Second.');

    expect(session(SessionKey::FLASH_DATA)[Flash::KEY])
        ->toBe(['type' => 'error', 'message' => 'Second.']);
});

it('flashes a message on logout', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertInertiaFlash(Flash::KEY, [
            'type' => FlashType::Success->value,
            'message' => 'You have been logged out.',
        ]);
});

it('does not flash anything on a plain login', function () {
    $user = User::factory()->create();

    post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertInertiaFlashMissing(Flash::KEY);
});
