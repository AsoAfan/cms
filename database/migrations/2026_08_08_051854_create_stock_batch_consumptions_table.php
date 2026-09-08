<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which batch paid for which issue — the FIFO allocation record.
     *
     * This is what makes COGS exact rather than estimated: issuing 15 units
     * that draw 10 from a batch at $5 and 5 from a batch at $7 writes two rows
     * here, and the cost of that sale is those rows, not an average.
     *
     * The cost is NOT copied onto this row. The batch's `unit_cost` is
     * immutable, so duplicating it here would be a second copy of the same
     * fact with no way to keep them honest.
     */
    public function up(): void
    {
        Schema::create('stock_batch_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // One allocation row per batch per issuing movement.
            $table->unique(['stock_batch_id', 'stock_movement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batch_consumptions');
    }
};
