<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the published rates could not be fetched or made sense of.
 *
 * A failed sync must leave the existing rates exactly as they were. Yesterday's
 * recorded rate is a known quantity; a half-written table is not.
 */
final class ExchangeRateSyncFailedException extends RuntimeException
{
    public static function unreachable(string $endpoint, string $reason): self
    {
        return new self("Could not reach the exchange rate service at {$endpoint}: {$reason}");
    }

    public static function unusableResponse(string $endpoint): self
    {
        return new self("The exchange rate service at {$endpoint} did not return usable rates.");
    }

    public static function missingCurrencies(string $endpoint, string ...$currencies): self
    {
        return new self(sprintf(
            'The exchange rate service at %s does not publish %s.',
            $endpoint,
            implode(', ', $currencies),
        ));
    }
}
