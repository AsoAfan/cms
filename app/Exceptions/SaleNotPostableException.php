<?php

namespace App\Exceptions;

use App\Models\Sale;
use RuntimeException;

/**
 * Thrown when a sale cannot be posted, or when something tries to change one
 * that already has been.
 */
final class SaleNotPostableException extends RuntimeException
{
    /**
     * @param  list<string>  $shortages  one line per product that is short
     */
    private function __construct(string $message, public readonly array $shortages = [])
    {
        parent::__construct($message);
    }

    public static function alreadyPosted(Sale $sale): self
    {
        return new self("{$sale->number} has already been posted.");
    }

    public static function hasNoLines(Sale $sale): self
    {
        return new self("{$sale->number} has no lines to post.");
    }

    public static function isPosted(Sale $sale): self
    {
        return new self("{$sale->number} is posted and cannot be changed.");
    }

    /**
     * Every product that cannot be covered, reported together — sending
     * someone back to the shelf once beats sending them three times.
     *
     * @param  list<string>  $shortages
     */
    public static function notEnoughStock(array $shortages): self
    {
        return new self('Not enough stock: '.implode('; ', $shortages).'.', $shortages);
    }
}
