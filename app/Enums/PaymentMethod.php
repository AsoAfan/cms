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

    /**
     * Whether money paid this way moved through a bank account.
     *
     * This is the ONE place that line is drawn. Every Form Request that accepts a
     * payment reads it to decide whether `bank_id` is required or forbidden, and
     * it is sent to the frontend on each payment-method option so a form shows
     * the bank field for exactly the methods that need it. Adding a method means
     * answering this question here and nowhere else.
     *
     * Cash is the only method that does not: it is money over the counter, and a
     * bank against one is a typo rather than a detail.
     */
    public function usesBank(): bool
    {
        return match ($this) {
            self::Cash => false,
            self::Card, self::Transfer => true,
        };
    }

    /**
     * The payment methods as the frontend receives them, on every screen that
     * takes a payment.
     *
     * @return list<array{value: string, label: string, uses_bank: bool}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
                'uses_bank' => $method->usesBank(),
            ],
            self::cases(),
        );
    }
}
