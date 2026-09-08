<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an invoice was paid, and which account it was paid out of.
     *
     * **This reverses the decision that purchases carry no payment details.**
     * They were left off because a bank without a payment method would mean
     * inventing the method first — which is exactly what this migration does,
     * because money going out of an account is the other half of money coming
     * into one. A bank balance derived from sales, expenses and repayments
     * alone is not a bank balance: stock is usually the largest thing a shop
     * pays for, and an account that never pays for any of it only ever climbs.
     *
     * Both columns match `sales` and `expenses` exactly, so
     * `PaymentMethod::usesBank()` stays the ONE place that decides which
     * methods move through an account and `NamesPayingBank` enforces it here
     * as it does everywhere else.
     *
     * `payment_method` defaults to cash: every invoice recorded before this
     * migration was paid without naming an account, and cash is what that is.
     * New invoices always send their own — the Form Request requires it.
     *
     * `bank_id` is nullable and `restrictOnDelete`, for the same reasons as on
     * the other three tables: cash names no bank, and a bank with invoices
     * behind it is never deleted nor quietly detached from them.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('payment_method')->default('cash')->after('status');

            $table->foreignId('bank_id')
                ->nullable()
                ->after('payment_method')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn('payment_method');
        });
    }
};
