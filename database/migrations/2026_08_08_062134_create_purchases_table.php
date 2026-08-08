<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier invoice.
     *
     * Carries no total: it is the sum of its lines and additional costs, and a
     * stored copy could only ever disagree with them.
     *
     * A purchase is a draft until it is posted. Posting is what writes stock,
     * and it happens once — a posted purchase is a historical record and is
     * never edited afterwards.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            // Optional: what was bought, for how much and when is the invoice.
            // Who it came from is filing, and stock that arrives from a cash
            // purchase down the market must not wait on a supplier record
            // being created first. Still restricted on delete — a supplier
            // with history behind them is not quietly detached from it.
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();

            // Our own filing reference, assigned on creation.
            $table->string('number')->unique();

            // The invoice date, which is also the date stock is taken in on —
            // deliveries get entered days late, and the ledger should say when
            // the goods actually arrived.
            $table->date('invoiced_on');

            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'invoiced_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
