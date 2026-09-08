<?php

namespace App\Enums;

/**
 * Why stock moved. It does not say which way — the sign of the movement's
 * quantity does that, and an adjustment goes either way.
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::Adjustment => 'Adjustment',
        };
    }
}
