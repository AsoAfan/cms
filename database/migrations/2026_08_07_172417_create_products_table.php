<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The catalogue entry a customer recognises.
     *
     * Carries no price and no stock of its own — those belong to the item,
     * which is the real stock-keeping entity. Deliberately has no category,
     * brand or unit: for this business they were management overhead without
     * a matching gain, and any of them can be added later additively.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
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
