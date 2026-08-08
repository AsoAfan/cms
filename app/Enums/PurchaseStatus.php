<?php

namespace App\Enums;

/**
 * A purchase is a draft while it is being typed, and posted once it has hit
 * the stock ledger. Posting is one-way: undoing one means writing a reversal,
 * never editing the original.
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
