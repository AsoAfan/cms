<?php

namespace App\Enums;

/**
 * How an invoice-wide cost is spread across the lines it belongs to.
 */
enum CostAllocationMethod: string
{
    /** Split in proportion to what each line is worth — duty, insurance. */
    case ByValue = 'by_value';

    /** Split in proportion to how many units each line has — freight, handling. */
    case ByQuantity = 'by_quantity';

    public function label(): string
    {
        return match ($this) {
            self::ByValue => 'By value',
            self::ByQuantity => 'By quantity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ByValue => 'Dearer lines take more of it.',
            self::ByQuantity => 'Lines with more units take more of it.',
        };
    }
}
