<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money spent running the business — rent, wages, fuel, advertising.
     *
     * Deliberately unconnected to stock. An expense is not a purchase: buying
     * goods to resell increases inventory and only becomes a cost when those
     * goods are sold, whereas rent is a cost the moment it is paid. Mixing the
     * two would double-count the first and mistime the second, so nothing here
     * touches the ledger. An arch test enforces that.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            $table->bigInteger('amount');
            $table->date('spent_on');
            $table->string('payment_method');

            // The receipt or invoice number, so a figure can be traced back to
            // the piece of paper it came from.
            $table->string('reference')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['spent_on', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
