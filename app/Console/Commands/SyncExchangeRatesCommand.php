<?php

namespace App\Console\Commands;

use App\Actions\Currency\SyncExchangeRatesAction;
use App\Exceptions\ExchangeRateSyncFailedException;
use Illuminate\Console\Command;

class SyncExchangeRatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'currency:sync
                            {--date= : Record the rates against this date instead of today}';

    /**
     * @var string
     */
    protected $description = 'Fetch the published exchange rates and record them against the base currency';

    /**
     * Scheduled daily in routes/console.php. A failure is reported and the
     * existing rates are left alone — an old rate on record beats none.
     */
    public function handle(SyncExchangeRatesAction $sync): int
    {
        try {
            $rates = $sync->handle($this->option('date'));
        } catch (ExchangeRateSyncFailedException $exception) {
            $this->components->error($exception->getMessage());
            $this->components->warn('Existing rates are unchanged.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=gray>Currency</>', '<fg=gray>Rate</>');

        foreach ($rates as $rate) {
            $this->components->twoColumnDetail(
                "{$rate->currency} on {$rate->effective_on->toDateString()}",
                $rate->decimalRate(),
            );
        }

        $this->newLine();
        $this->components->info(sprintf('%d rate(s) recorded.', $rates->count()));

        return self::SUCCESS;
    }
}
