<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stock item — the thing that is actually counted, bought and sold.
     *
     * Every product has at least one, even when it has no options, so that
     * purchases, sales and the stock ledger only ever reference an item and
     * never have to special-case a "simple" product.
     *
     * For sized goods the size lives in the options, so an item is a finished
     * size counted in whole pieces. That is what keeps quantities integral all
     * the way through costing.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // The short code printed on the label. "SKU" in the trade, but the
            // UI calls it a code and so does the schema, so the two agree.
            $table->string('code')->unique();

            // Defaults that pre-fill data entry only (P1.T5). Every purchase
            // and sale records its own price as a fact at transaction time, so
            // changing these never rewrites history. Null means "not priced
            // yet", which is a real state and truer than storing zero.
            $table->bigInteger('default_cost_price')->nullable();
            $table->bigInteger('default_selling_price')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
