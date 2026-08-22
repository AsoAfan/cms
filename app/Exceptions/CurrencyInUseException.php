<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a currency cannot be removed or demoted because the books depend
 * on it.
 *
 * These are refusals a user should read, not bugs — the controller turns each
 * into a flash message.
 */
final class CurrencyInUseException extends RuntimeException
{
    public static function isBase(string $code): self
    {
        return new self(
            "{$code} is the currency your books are kept in. Make another currency the default before removing it."
        );
    }

    public static function isOnADocument(string $code): self
    {
        return new self(
            "{$code} is recorded on a purchase, sale or expense. Removing it would leave those documents naming a currency that no longer exists."
        );
    }

    /**
     * Every stored amount is minor units of the base, and each was recorded at
     * the rate current when it happened. No single rate could restate that
     * history correctly, so once there is money on record the base is fixed.
     */
    public static function cannotChangeBase(string $code): self
    {
        return new self(
            "Your books are already kept in {$code} and there is money recorded in them. Changing the default now would restate every past figure at a rate that was never used."
        );
    }
}
