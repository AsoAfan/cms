<?php

namespace App\Enums;

/**
 * How a sale was paid for.
 *
 * An enum rather than a reference table: these change about once a decade, and
 * a table would mean another screen to manage for no gain. Adding a method is
 * a one-line change here.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Transfer => 'Bank transfer',
        };
    }
}
