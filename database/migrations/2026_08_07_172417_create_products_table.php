<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product is the stock-keeping entity: the thing that is counted,
     * bought and sold. One row, one name, one price.
     *
     * There are deliberately no variants, options, categories, brands, units
     * or codes. Where goods differ — a curtain at 117×137 versus 168×183 —
     * each is its own product with its own name. That keeps every screen and
     * every later ledger entry pointing at a single table.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // The name is the identity: it is what someone searches for, picks
            // from a list and reads on a report, so two products may not share
            // one. A separate code was tried and removed — it was a second
            // identifier to invent, type and keep unique for no gain.
            $table->string('name')->unique();

            $table->text('description')->nullable();

            // What it costs and what it sells for, both required. Every purchase
            // and sale still records its own price as a fact at transaction
            // time, so changing these never rewrites history — but a product
            // nobody has priced cannot be sold, and carrying "not priced yet" as
            // a storable state meant a null to handle on every screen, every
            // prefill and every report for a case that helped nobody.
            //
            // Stored in the base currency like every other amount; a price
            // quoted in dollars is converted on the way in.
            $table->bigInteger('cost_price');
            $table->bigInteger('selling_price');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
