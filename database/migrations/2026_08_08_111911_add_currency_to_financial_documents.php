<?php

use App\Support\ExchangeRates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What currency the money changed hands in, and at what rate.
     *
     * Every amount on these documents is stored in the base currency, exactly as
     * before — the ledger, the FIFO costing and every report query stay
     * currency-blind. These two columns record the two facts that base-currency
     * storage would otherwise throw away:
     *
     * - `currency`: what was actually paid or invoiced in. Mostly dinars, and
     *   sometimes dollars, which is the whole reason this exists.
     * - `exchange_rate`: the rate in force on the document's own date, as the
     *   fixed-point integer defined by App\Support\ExchangeRates. Kept so that
     *   "what rate did we use on this invoice?" is answerable years later,
     *   whatever the rate has done since.
     *
     * The rate is stored even when the document is in the base currency, where it
     * is exactly 1.000000. A column that is sometimes null and sometimes
     * meaningful is a column every reader has to think about.
     *
     * KNOWN LIMIT: one rate per document. With two currencies that is complete —
     * an amount is either in the base currency or in the other one, and one rate
     * covers both directions. A third currency would have to move the rate down
     * to the individual amount.
     */
    /**
     * The column each pair sits behind, so the two read next to the status or
     * payment details they belong with rather than at the end of the row.
     *
     * @var array<string, string>
     */
    private const array DOCUMENTS = [
        'purchases' => 'status',
        'sales' => 'payment_method',
        'expenses' => 'payment_method',
    ];

    public function up(): void
    {
        foreach (self::DOCUMENTS as $documents => $after) {
            Schema::table($documents, function (Blueprint $table) use ($after): void {
                $table->char('currency', 3)
                    ->default(config('money.currency'))
                    ->after($after);

                $table->unsignedBigInteger('exchange_rate')
                    ->default(ExchangeRates::SCALE)
                    ->after('currency');
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::DOCUMENTS) as $documents) {
            Schema::table($documents, function (Blueprint $table): void {
                $table->dropColumn(['currency', 'exchange_rate']);
            });
        }
    }
};
