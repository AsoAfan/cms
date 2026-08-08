<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A FIFO layer: a quantity of one product received at one cost.
     *
     * `unit_cost` is the landed cost — a recorded fact at the moment of
     * receipt, not a cache. It is the one figure COGS is ultimately built
     * from, so it never changes after the batch is written.
     *
     * The remaining quantity is deliberately NOT stored. It is
     * `quantity_received` minus what stock_batch_consumptions has taken, which
     * cannot fall out of step with the consumption records the way a counter
     * would.
     *
     * The batch points at the movement that created it rather than at a
     * purchase line, so receipts from adjustments and (from Phase 4) purchases
     * both work without a special case.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_movement_id')
                ->unique()
                ->constrained('stock_movements')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity_received');
            $table->bigInteger('unit_cost');
            $table->timestamp('received_at');
            $table->timestamps();

            // FIFO consumption order: oldest receipt first, id breaking ties.
            $table->index(['product_id', 'received_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
