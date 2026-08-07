<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What makes one variant different from another: the set of attribute
     * values it carries. "Red / Large" is two rows here.
     *
     * `attribute_id` is derivable from `attribute_value_id`, and carrying it
     * anyway is the one deliberate redundancy in this schema. It buys a
     * guarantee the database can actually enforce — that a variant is never
     * both Red and Blue — via `unique(product_variant_id, attribute_id)`.
     * The composite foreign key below pins the pair back to a real row in
     * `attribute_values`, so the two columns cannot drift apart.
     */
    public function up(): void
    {
        Schema::create('product_variant_attribute_value', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->restrictOnDelete();

            $table->primary(['product_variant_id', 'attribute_value_id']);
            $table->unique(['product_variant_id', 'attribute_id']);

            $table->foreign(['attribute_id', 'attribute_value_id'])
                ->references(['attribute_id', 'id'])
                ->on('attribute_values')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_value');
    }
};
