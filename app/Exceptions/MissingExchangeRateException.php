<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an amount has to be converted but no rate is on record.
 *
 * There is deliberately no fallback. Guessing a rate would quietly write a
 * wrong cost into the ledger, and every figure derived from it afterwards —
 * COGS, margin, inventory value — would be wrong in a way nothing could later
 * detect. Refuse and make someone record the rate.
 *
 * Users should never reach this: Form Requests validate currencies against
 * `CurrencyService::enterable()`, which only lists currencies that already have
 * a usable rate. It is the guard behind that, not the message on a screen.
 */
final class MissingExchangeRateException extends RuntimeException
{
    private function __construct(
        public readonly string $currency,
        public readonly string $base,
        public readonly ?string $on,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(string $currency, string $base, ?string $on = null): self
    {
        return new self(
            $currency,
            $base,
            $on,
            $on === null
                ? "No exchange rate on record for {$currency} against {$base}."
                : "No exchange rate on record for {$currency} against {$base} on or before {$on}.",
        );
    }
}
