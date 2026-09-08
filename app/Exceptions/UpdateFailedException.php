<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the application cannot update itself.
 *
 * Every message is read by whoever runs the business, not by a developer, so
 * each one says what happened and what to do about it. The controller shows the
 * message and logs the `detail` — git's own words are what makes a support call
 * solvable, and they are exactly what nobody wants in a toast.
 *
 * Anything carrying git output has been through `UpdateService::redact()`: the
 * remote URL holds the repository credential on a private install, and git
 * echoes the URL it was given in most of its failures.
 */
final class UpdateFailedException extends RuntimeException
{
    public string $detail = '';

    private static function because(string $message, string $detail): self
    {
        $exception = new self($message);
        $exception->detail = $detail;

        return $exception;
    }

    public static function notConfigured(): self
    {
        return new self(
            'This copy is not set up to receive updates. Ask whoever installed it to fill in UPDATE_REMOTE.'
        );
    }

    public static function notAGitCheckout(string $path): self
    {
        return self::because(
            'Updates need this copy to have been installed with git, and this one was not. It was probably set up by unzipping a download.',
            "{$path} is not a git checkout."
        );
    }

    public static function gitMissing(): self
    {
        return new self(
            'Git is not installed on this computer, so updates cannot be downloaded.'
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            'An update is already running. Give it a moment, then refresh this page.'
        );
    }

    public static function checkFailed(string $detail): self
    {
        return self::because(
            'Could not reach the update server. Check the internet connection and try again.',
            $detail
        );
    }

    public static function downloadFailed(string $detail): self
    {
        return self::because(
            'The update could not be downloaded, so nothing was changed.',
            $detail
        );
    }

    /**
     * The one message that has to admit something happened, because the files
     * had already been replaced by the time this failed.
     */
    public static function rolledBack(string $detail): self
    {
        return self::because(
            'The update was undone and your data was put back, so the app is exactly as it was before. Please let support know.',
            $detail
        );
    }

    public static function rollbackFailed(string $detail): self
    {
        return self::because(
            'The update failed and could not be undone. Do not enter anything new — please contact support straight away.',
            $detail
        );
    }
}
