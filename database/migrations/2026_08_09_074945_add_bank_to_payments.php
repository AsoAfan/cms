<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which bank the money moved through, on every document that records how it
     * was paid.
     *
     * The three tables that carry `payment_method` carry this beside it: a sale
     * (money in at the counter), an expense (money out), and a customer payment
     * (money in against a loan). Purchases are deliberately not here — they have
     * no payment method at all, and giving one a bank would mean inventing the
     * other first.
     *
     * **Nullable in the database, required by the Form Requests when the method
     * is card or transfer.** Cash has no bank, so the column must be able to hold
     * nothing; but a card payment nobody can trace to an account is exactly the
     * row that makes bank totals fail to reconcile with card totals, and
     * `PaymentMethod::usesBank()` is the one place that line is drawn. Existing
     * rows predate banks entirely and stay null, which is why this cannot be a
     * required column however much the rule wants it to be.
     *
     * `restrictOnDelete` throughout: a bank with payments behind it is never
     * deleted, and never quietly detached from its history — the same treatment
     * a supplier gets, and for the same reason.
     */
    public function up(): void
    {
        foreach (['sales', 'expenses', 'customer_payments'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('bank_id')
                    ->nullable()
                    ->after('payment_method')
                    ->constrained()
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['sales', 'expenses', 'customer_payments'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('bank_id');
            });
        }
    }
};
