<?php

namespace App\Exceptions;

use App\Models\Sale;
use App\Support\Money;
use RuntimeException;

/**
 * Thrown when money coming in cannot be applied to what it claims to settle.
 *
 * Every case here protects the same invariant: a customer's balance is derived,
 * so a payment that does not add up would not make the figure wrong on one
 * screen — it would make it wrong everywhere at once.
 */
final class CustomerPaymentException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notPositive(): self
    {
        return new self('A payment has to be more than nothing.');
    }

    public static function nothingAllocated(): self
    {
        return new self('Say which invoices this payment settles.');
    }

    /**
     * Money that arrived against nothing in particular would make the account
     * balance and the invoice balances disagree, so the parts must add up to the
     * whole.
     */
    public static function allocationsDoNotAddUp(Money $amount, Money $allocated): self
    {
        return new self(sprintf(
            'The invoices come to %s, but the payment is %s. Apply the payment in full.',
            $allocated->toDecimal(),
            $amount->toDecimal(),
        ));
    }

    public static function saleBelongsToSomebodyElse(Sale $sale): self
    {
        return new self("{$sale->number} is not this customer's invoice.");
    }

    public static function saleIsNotDelivered(Sale $sale): self
    {
        return new self(
            "{$sale->number} has not been delivered yet, so nothing is owed on it. "
            .'Money taken up front belongs on the sale itself.'
        );
    }

    public static function overpaysSale(Sale $sale, Money $outstanding, Money $applied): self
    {
        return new self(sprintf(
            '%s only has %s left on it, and %s was applied to it.',
            $sale->number,
            $outstanding->toDecimal(),
            $applied->toDecimal(),
        ));
    }
}
