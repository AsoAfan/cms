<?php

namespace App\Enums;

/**
 * The severity of a one-off message flashed to the next request, rendered by
 * the app shell as a toast.
 */
enum FlashType: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
