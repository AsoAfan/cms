<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who you sell to.
     *
     * The mirror of `suppliers`, and deliberately the same shape: only the name
     * is required, because a customer is often a name and a phone number when
     * you first write them down, and the form must not stand in the way of
     * recording that.
     *
     * The name is unique. A customer has to be pickable from a list on the sale
     * screen and recognisable at the top of a statement, and two rows reading
     * "Ahmed" are two rows nobody can tell apart — the phone number is what
     * separates them, so it goes in the name where a second Ahmed appears.
     *
     * Nothing here stores a balance. What a customer owes is derived from their
     * delivered sales and the payments recorded against them — see
     * `App\Queries\CustomerBalanceQuery`. A stored balance is the one figure in
     * this application that would be guaranteed to drift.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
