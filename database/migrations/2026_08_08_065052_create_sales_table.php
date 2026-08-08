<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sale over the counter.
     *
     * No customer: sales here are not tied to a named buyer (see Phase 2).
     *
     * Carries no total and no cost of sale. The total is the sum of its lines,
     * and the cost is whatever the stock ledger recorded when it was posted —
     * both derived, so neither can drift away from what actually happened.
     *
     * Draft until posted. Posting is what takes stock out, and it happens once.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();

            // When it was sold, which is not always when it was typed in.
            $table->date('sold_on');

            $table->string('status');

            // How it was paid for. A single method rather than a payments
            // table: split and part payment are not needed yet, and adding
            // them later is a new table plus a nullable column here.
            $table->string('payment_method');

            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sold_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
