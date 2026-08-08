<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a foreign currency was worth in the base currency, by date.
     *
     * `rate` is base major units per one foreign major unit, as a fixed-point
     * integer scaled by 1,000,000 — see App\Support\ExchangeRates, which owns
     * that definition and every conversion. An integer because a rate that
     * drifts silently rewrites the cost of everything ever bought with it.
     *
     * The base currency itself is never stored here: it is its own unit, always
     * exactly 1,000,000.
     *
     * Every row is written by `currency:sync` from the published feed. There is
     * deliberately no rate entered by hand and no `source` column to tell them
     * apart: the rate is a fact about the market, not a setting, and a screen
     * for typing one in is a screen for getting one wrong.
     *
     * One rate per currency per day, hence the unique key. A second sync on the
     * same day corrects that day's figure rather than adding to it.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->char('currency', 3);
            $table->unsignedBigInteger('rate');
            $table->date('effective_on');

            $table->timestamps();

            // Every lookup is "the newest rate for this currency on or before
            // this date", which is exactly this index read backwards.
            $table->unique(['currency', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
