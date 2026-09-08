<?php

namespace App\Enums;

use App\Support\Money;

/**
 * Which way money moved when somebody adjusted an account by hand.
 *
 * The stored amount is signed, so this exists to turn what a user picked on a
 * form into that sign in one place — a form that posted a negative number
 * would make every screen that shows the field responsible for the minus.
 */
enum BankAdjustmentDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Money in',
            self::Out => 'Money out',
        };
    }

    /**
     * What the one line under the label says on the form.
     */
    public function description(): string
    {
        return match ($this) {
            self::In => 'An opening balance, a deposit, interest.',
            self::Out => 'A withdrawal, a bank charge, a transfer out.',
        };
    }

    /**
     * The amount as it is stored: positive in, negative out.
     */
    public function apply(Money $amount): Money
    {
        return $this === self::Out ? $amount->absolute()->negated() : $amount->absolute();
    }

    /**
     * Which way a stored amount went.
     */
    public static function of(Money $amount): self
    {
        return $amount->isNegative() ? self::Out : self::In;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $direction): array => [
                'value' => $direction->value,
                'label' => $direction->label(),
                'description' => $direction->description(),
            ],
            self::cases(),
        );
    }
}
