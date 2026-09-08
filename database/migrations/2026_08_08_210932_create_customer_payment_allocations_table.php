<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which invoice a payment settled, and by how much.
     *
     * A payment is applied to named sales rather than dropped on an account
     * balance, so "what is still owed on SAL-00031" has an answer instead of an
     * inference. One payment can clear several invoices and one invoice can take
     * several payments, which is what makes this its own table.
     *
     * Invariants the recording action upholds, none of which a column can:
     *
     * - Allocations of a payment sum to EXACTLY the payment's amount. Money that
     *   came in against nothing in particular would make the account balance and
     *   the invoice balances disagree.
     * - No allocation exceeds what is still owed on its sale, so an invoice can
     *   never be overpaid and no customer's balance can go negative through the
     *   back door.
     * - Only delivered (`proceed`) sales can be allocated to. Money against an
     *   order not yet handed over is a deposit, and a deposit belongs on the
     *   sale as `amount_paid`.
     *
     * Cascades from the payment: deleting a mistyped payment must unwind
     * everything it claimed to settle. Restricts on the sale — an invoice with
     * money against it is not deleted out from under it.
     */
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();

            $table->bigInteger('amount');
            $table->timestamps();

            // One row per invoice per payment: two part-allocations of the same
            // payment to the same invoice are one allocation typed twice.
            $table->unique(['customer_payment_id', 'sale_id']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
