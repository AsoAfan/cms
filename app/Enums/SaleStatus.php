<?php

namespace App\Enums;

/**
 * Where an order stands with the customer.
 *
 * Ordered when they ask for it, on the way once it has been sent out, and
 * proceed once they have it.
 *
 * **Stock leaves at `OnTheWay`, not at `Proceed`.** Goods handed to a driver
 * are off the shelf whatever happens next, and a shop that still counts them
 * as stock will sell them twice. `Proceed` records that the customer received
 * them and moves nothing.
 *
 * None of this is one-way: moving a sale back to `Ordered` puts the goods back
 * on the shelf — see `RevertSaleAction`.
 */
enum SaleStatus: string
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
            self::Ordered => 'Asked for, still on the shelf.',
            self::OnTheWay => 'Sent out to the customer.',
            self::Proceed => 'Customer has it.',
        };
    }

    /**
     * Whether the goods have left the ledger at this status.
     */
    public function releasesStock(): bool
    {
        return $this === self::OnTheWay || $this === self::Proceed;
    }

    /**
     * The statuses whose goods and money are real, for a query to filter on.
     *
     * Derived from `releasesStock()` rather than listed again, so what
     * reporting counts can never drift from what the ledger holds.
     *
     * @return list<string>
     */
    public static function inLedger(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->releasesStock()),
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
