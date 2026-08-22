<?php

namespace App\Exceptions;

use App\Models\Purchase;
use RuntimeException;

/**
 * Thrown when a purchase cannot take its goods into stock.
 */
final class PurchaseLedgerException extends RuntimeException
{
    public static function hasNoLines(Purchase $purchase): self
    {
        return new self("{$purchase->number} has nothing on it to take in.");
    }
}
