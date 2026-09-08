<?php

namespace App\Support;

/**
 * One commit on the release branch — a version of the application.
 *
 * There is no version file to bump and no tag to remember: CI publishes one
 * commit per release, so the commit itself already carries an identity (its
 * sha), a date and a description (its subject). Anything else would be a second
 * number that can disagree with the code actually installed.
 *
 * The date stays an ISO-8601 string. It is a value object, not a formatter —
 * the screen renders it in the user's locale, like every other date on the wire.
 */
final readonly class Release
{
    public function __construct(
        public string $sha,
        public string $committedAt,
        public string $subject,
    ) {}

    /**
     * Reads one `git log --format=%H%x1f%cI%x1f%s` line.
     *
     * Split on the ASCII unit separator rather than a space or a pipe, because
     * a commit subject is free text and will eventually contain whichever
     * printable character was chosen as a delimiter.
     */
    public static function fromGitLine(string $line): ?self
    {
        $parts = explode("\x1f", trim($line), 3);

        if (count($parts) !== 3 || $parts[0] === '') {
            return null;
        }

        return new self($parts[0], $parts[1], $parts[2]);
    }

    /** The seven characters a person can actually read out over the phone. */
    public function short(): string
    {
        return substr($this->sha, 0, 7);
    }

    public function is(?self $other): bool
    {
        return $other !== null && $this->sha === $other->sha;
    }

    /**
     * @return array{sha: string, short: string, committed_at: string, subject: string}
     */
    public function toArray(): array
    {
        return [
            'sha' => $this->sha,
            'short' => $this->short(),
            'committed_at' => $this->committedAt,
            'subject' => $this->subject,
        ];
    }
}
