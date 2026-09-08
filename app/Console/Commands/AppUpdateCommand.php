<?php

namespace App\Console\Commands;

use App\Exceptions\UpdateFailedException;
use App\Services\UpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The same update the Settings screen runs, from a terminal.
 *
 * Kept because the screen is unreachable in exactly the situations where an
 * update is most needed — a release that will not boot, or a client whose
 * browser shows nothing at all. Both paths go through `UpdateService`, so
 * neither can drift from the other.
 */
#[Signature('app:update {--check : Report what is available without installing anything}')]
#[Description('Update this installation to the newest release')]
class AppUpdateCommand extends Command
{
    public function handle(UpdateService $updates): int
    {
        try {
            $result = $this->option('check') ? $updates->check() : $updates->apply();
        } catch (UpdateFailedException $failure) {
            $this->components->error($failure->getMessage());

            if ($failure->detail !== '') {
                $this->line($failure->detail);
            }

            return self::FAILURE;
        }

        if ($result->isUpToDate()) {
            $this->components->info("Already on the newest release ({$result->latest->short()}).");

            return self::SUCCESS;
        }

        $released = Carbon::parse($result->latest->committedAt)->format('j M Y');

        if ($this->option('check')) {
            $this->components->info("Release {$result->latest->short()} is available, dated {$released}.");

            foreach ($result->changes as $change) {
                $this->line("  • {$change}");
            }

            return self::SUCCESS;
        }

        $this->components->info("Updated to {$result->latest->short()}, dated {$released}.");

        return self::SUCCESS;
    }
}
