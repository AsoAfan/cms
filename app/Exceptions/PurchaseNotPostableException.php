<?php

namespace App\Exceptions;

use App\Models\Purchase;
use RuntimeException;

/**
 * Thrown when a purchase cannot be posted, or when something tries to change
 * one that already has been.
 */
final class PurchaseNotPostableException extends RuntimeException
{
    public static function alreadyPosted(Purchase $purchase): self
    {
        return new self("{$purchase->number} has already been posted.");
    }

    public static function hasNoLines(Purchase $purchase): self
    {
        return new self("{$purchase->number} has no lines to post.");
    }

    public static function isPosted(Purchase $purchase): self
    {
        return new self(
            "{$purchase->number} is posted and cannot be changed. Reverse it instead."
        );
    }
}
