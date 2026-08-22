<?php

namespace App\Enums;

/**
 * Where an order stands with the supplier.
 *
 * Ordered, then on its way, then here. Only the last of those is stock: goods
 * somebody has promised are not goods on the shelf, and nothing counts towards
 * what was spent until the invoice is actually settled by delivery.
 *
 * Unlike the draft/posted pair this replaced, none of these is one-way. A
 * purchase can be moved back down the list, which reverses whatever it put in
 * the ledger — see `RevertPurchaseAction`.
 */
enum PurchaseStatus: string
{
    case Ordered = 'ordered';
    case OnTheWay = 'on_the_way';
    case Proceed = 'proceed';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Ordered',
            self::OnTheWay => 'On the way',
            self::Proceed => 'Proceed',
        };
    }

    /**
     * What the status means, for the one line under it on screen.
     */
    public function description(): string
    {
        return match ($this) {
            self::Ordered => 'Placed with the supplier.',
            self::OnTheWay => 'Dispatched, not here yet.',
            self::Proceed => 'Arrived and on the shelf.',
        };
    }

    /**
     * Whether the goods are in the ledger at this status.
     *
     * Stock arrives when the purchase does, and not before — an order on its
     * way cannot be sold from.
     */
    public function holdsStock(): bool
    {
        return $this === self::Proceed;
    }

    /**
     * The statuses whose goods and money are real, for a query to filter on.
     *
     * Derived from `holdsStock()` rather than listed again, so what reporting
     * counts can never drift from what the ledger holds.
     *
     * @return list<string>
     */
    public static function inLedger(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->holdsStock()),
        ));
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
            ],
            self::cases(),
        );
    }
}
