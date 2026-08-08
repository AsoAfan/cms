<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One product on a sale.
     *
     * `unit_price` and `discount` are what the customer was actually charged —
     * facts at the moment of sale, not copies of the catalogue price. Changing
     * a product's price tomorrow must never rewrite what yesterday sold for.
     *
     * The cost of these goods is NOT stored here. It is whatever batches the
     * ledger consumed when this line was posted, which is recorded exactly in
     * stock_batch_consumptions and can always be replayed.
     */
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('discount')->default(0);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
