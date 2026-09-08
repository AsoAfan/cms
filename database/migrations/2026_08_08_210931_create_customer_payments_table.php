<?php

use App\Support\ExchangeRates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a customer paid against what they owe.
     *
     * This is the other half of the loan: `sales.amount_paid` records what was
     * handed over at the time of the sale, and a row here records everything
     * that came in afterwards. Together they are the whole of what a customer
     * has paid, and what is left is derived — never stored.
     *
     * Not a stock movement and never near one. A repayment is money against a
     * debt; the goods left the shelf when the sale was delivered, and letting a
     * payment reach the ledger would take them out twice. An arch test enforces
     * that, exactly as it does for expenses.
     *
     * A payment is recorded once, like an expense, and never edited: it is
     * either what came in or it is not. Correcting one means deleting it and
     * recording what actually happened, which also unwinds its allocations.
     *
     * Carries `currency` and `exchange_rate` on the same terms as every other
     * financial document — what the money changed hands in, and the rate in
     * force on the day it was received. The `amount` itself is base-currency
     * minor units like everything else.
     */
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->bigInteger('amount');

            // When the money came in, which is not always when it was typed up.
            $table->date('received_on');

            $table->string('payment_method');
            $table->char('currency', 3)->default(config('money.currency'));
            $table->unsignedBigInteger('exchange_rate')->default(ExchangeRates::SCALE);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'received_on']);
            $table->index('received_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
