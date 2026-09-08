<?php

namespace App\Support;

/**
 * The answer to "is there anything new?" — what is installed, what is on the
 * release branch, and every release message in between.
 *
 * Transient: nothing is stored between visits. A check is a network round trip
 * whose answer can be wrong by the time it is read, and a remembered "update
 * available" that has since been installed is worse than no answer at all.
 */
final readonly class UpdateCheck
{
    /**
     * @param  list<string>  $changes  Release subjects, newest first.
     */
    public function __construct(
        public ?Release $installed,
        public Release $latest,
        public array $changes,
    ) {}

    public function isUpToDate(): bool
    {
        return $this->latest->is($this->installed);
    }

    /**
     * @return array{installed: ?array<string, string>, latest: array<string, string>, changes: list<string>, up_to_date: bool}
     */
    public function toArray(): array
    {
        return [
            'installed' => $this->installed?->toArray(),
            'latest' => $this->latest->toArray(),
            'changes' => $this->changes,
            'up_to_date' => $this->isUpToDate(),
        ];
    }
}
