<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product is the stock-keeping entity: the thing that is counted,
     * bought and sold. One row, one code, one price.
     *
     * There are deliberately no variants, options, categories, brands or
     * units. Where goods differ — a curtain at 117×137 versus 168×183 — each
     * is its own product with its own code. That keeps every screen and every
     * later ledger entry pointing at a single table.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // The short code printed on the label.
            $table->string('code')->unique();

            $table->text('description')->nullable();

            // Defaults that pre-fill data entry only. Every purchase and sale
            // records its own price as a fact at transaction time, so changing
            // these never rewrites history. Null means "not priced yet", which
            // is a real state and truer than storing zero.
            $table->bigInteger('default_cost_price')->nullable();
            $table->bigInteger('default_selling_price')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
