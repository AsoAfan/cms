<?php

namespace App\Enums;

/**
 * A sale is a draft while it is being rung up, and posted once it has taken
 * stock out. Posting is one-way.
 */
enum SaleStatus: string
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
}
