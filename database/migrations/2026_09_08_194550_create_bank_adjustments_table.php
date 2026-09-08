<?php

use App\Support\ExchangeRates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money moved into or out of an account by hand.
     *
     * A bank balance is derived from the documents that touched the account —
     * sales taken into it, purchases and expenses paid out of it, repayments
     * received into it. That covers trade and nothing else, so the balance it
     * produces starts at zero on the day the business began using this system.
     * This table is where everything trade does not explain gets recorded: the
     * opening balance, cash walked to the bank, interest, charges, an owner's
     * transfer between accounts.
     *
     * **The amount is signed**, like a stock movement and for the same reason:
     * money in is positive, money out is negative, the rows are the account's
     * history, and correcting one means deleting it and recording what really
     * happened rather than editing a fact. `BankAdjustmentDirection` is what
     * turns the direction a user picks into the sign.
     *
     * `reason` is required and free text — "Opening balance", "Cash deposited".
     * An amount and a date with nothing to identify them is a row nobody can
     * account for a month later, the same reasoning that makes an expense's
     * title required.
     *
     * `currency` and `exchange_rate` record what was actually handed over and
     * the rate it was converted at, exactly as on a purchase, a sale or an
     * expense. The `amount` itself is base-currency minor units like every
     * other amount in the application.
     */
    public function up(): void
    {
        Schema::create('bank_adjustments', function (Blueprint $table): void {
            $table->id();

            // Never cascades: the row IS part of the account's balance, so
            // losing one silently would change a figure nobody asked to change.
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();

            $table->string('reason');

            // Signed minor units of the base currency. Negative is money out.
            $table->bigInteger('amount');

            $table->date('occurred_on');

            $table->char('currency', 3)->default(config('money.currency'));
            $table->unsignedBigInteger('exchange_rate')->default(ExchangeRates::SCALE);

            $table->timestamps();

            $table->index(['bank_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_adjustments');
    }
};
