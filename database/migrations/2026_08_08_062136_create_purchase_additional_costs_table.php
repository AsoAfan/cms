<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freight, duty, handling — costs that belong to the invoice as a whole
     * rather than to any one line.
     *
     * They are spread across the lines at posting, so they end up inside the
     * cost of the goods rather than sitting apart from it. How they spread is
     * a judgement call the buyer makes per cost: freight usually follows
     * quantity, duty usually follows value.
     */
    public function up(): void
    {
        Schema::create('purchase_additional_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            $table->bigInteger('amount');
            $table->string('allocation_method');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_additional_costs');
    }
};
