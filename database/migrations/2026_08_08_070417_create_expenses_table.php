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

            // What the money went on, in the words of whoever spent it —
            // "February rent", "Van diesel". Required, because a category and an
            // amount alone leave a row nobody can identify a month later.
            $table->string('title');

            $table->bigInteger('amount');
            $table->date('spent_on');
            $table->string('payment_method');
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
