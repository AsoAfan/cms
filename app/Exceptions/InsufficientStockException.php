<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * Thrown when an issue would take more stock than the ledger says exists.
 *
 * Stock is never allowed to go negative: a negative balance means the real
 * world and the books have diverged, and every cost derived afterwards is
 * guesswork. Better to refuse and make someone look.
 */
final class InsufficientStockException extends RuntimeException
{
    private function __construct(
        public readonly Product $product,
        public readonly int $requested,
        public readonly int $available,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Product $product, int $requested, int $available): self
    {
        return new self(
            $product,
            $requested,
            $available,
            sprintf(
                '%s (%s): tried to issue %d but only %d in stock.',
                $product->name,
                $product->code,
                $requested,
                $available,
            ),
        );
    }
}
