<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The append-only ledger. Every change in stock is a row here, and stock
     * on hand is always derived by summing them — there is no balance column
     * anywhere, because a stored balance drifts.
     *
     * Rows are never updated or deleted. Correcting a mistake means writing a
     * further movement in the opposite direction, so the history of what was
     * believed and when stays intact.
     *
     * `quantity` is signed: positive receives, negative issues. The type says
     * why it moved, not which way — an adjustment goes either way.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Restricted on delete: a product with stock history can never be
            // removed, or the ledger would describe something that is gone.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->integer('quantity');
            $table->string('type');

            // What caused it — a purchase line, a sale line, an adjustment.
            // Nullable because Phase 3 writes adjustments that stand alone.
            $table->nullableMorphs('source');

            // When the stock actually moved, which is not when the row was
            // written: back-dating a delivery note is legitimate.
            $table->timestamp('occurred_at');

            $table->string('reason')->nullable();
            $table->timestamps();

            // Every stock question is "this product, up to this date".
            $table->index(['product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
