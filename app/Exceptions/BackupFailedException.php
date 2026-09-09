<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a copy of the database could not be made.
 *
 * Read by whoever runs the business, so each message says what happened and
 * what to do about it. The `detail` carries the machine's own words for the
 * log — a disk path or a driver error, which is what makes a support call
 * solvable and exactly what nobody wants in a toast.
 */
final class BackupFailedException extends RuntimeException
{
    public string $detail = '';

    public static function notAFileDatabase(): self
    {
        return self::because(
            'This copy keeps its records on a database server, so backups are handled there rather than here.',
            'The default connection is not a file-backed SQLite database.'
        );
    }

    public static function couldNotWrite(string $detail): self
    {
        return self::because(
            'The backup could not be saved. Check there is space left on the disk, then try again.',
            $detail
        );
    }

    public static function missing(string $name): self
    {
        return self::because(
            'That backup is no longer on this computer. It may have been deleted to make room for newer ones.',
            "No backup named {$name}."
        );
    }

    private static function because(string $message, string $detail): self
    {
        $exception = new self($message);
        $exception->detail = $detail;

        return $exception;
    }
}
