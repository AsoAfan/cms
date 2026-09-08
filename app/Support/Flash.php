<?php

namespace App\Support;

use App\Enums\FlashType;
use Inertia\Inertia;

/**
 * Queues a one-off message that the app shell shows as a toast.
 *
 *     Flash::success('Purchase posted.');
 *
 *     return to_route('purchases.index');
 *
 * Uses Inertia flash data rather than a shared session prop, so a message is
 * shown once and never resurfaces when the user navigates back to the page.
 */
final class Flash
{
    /**
     * The flash key the frontend `FlashToaster` listens on.
     */
    public const string KEY = 'toast';

    public static function success(string $message): void
    {
        self::put(FlashType::Success, $message);
    }

    public static function error(string $message): void
    {
        self::put(FlashType::Error, $message);
    }

    public static function warning(string $message): void
    {
        self::put(FlashType::Warning, $message);
    }

    public static function info(string $message): void
    {
        self::put(FlashType::Info, $message);
    }

    private static function put(FlashType $type, string $message): void
    {
        Inertia::flash(self::KEY, [
            'type' => $type->value,
            'message' => $message,
        ]);
    }
}
