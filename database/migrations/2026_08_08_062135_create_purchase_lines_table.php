<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One product on a supplier invoice.
     *
     * `unit_cost` and `discount` are what the supplier charged — facts at
     * transaction time, not caches. The landed cost, once freight and duty are
     * spread across the invoice, is not stored here: it is derived at posting
     * and recorded where it matters, on the stock batches.
     */
    public function up(): void
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost');

            // An absolute amount off this line, not a percentage: a percentage
            // would need rounding to become money, and the amount is what the
            // invoice actually says.
            $table->bigInteger('discount')->default(0);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};
