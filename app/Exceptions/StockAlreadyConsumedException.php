<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * Thrown when a receipt cannot be undone because the goods it brought in have
 * already been sold on.
 *
 * The alternative would be to delete a batch some sale's cost of goods was
 * read from, which would quietly change what that sale is recorded as having
 * made. Refusing is the only honest answer: sell-through is not reversible by
 * editing the paperwork behind it.
 */
final class StockAlreadyConsumedException extends RuntimeException
{
    public static function for(Product $product): self
    {
        return new self(
            "Some of the {$product->name} from this invoice has already been sold. "
            .'Undo the sale first, or leave the invoice as it is.'
        );
    }
}
