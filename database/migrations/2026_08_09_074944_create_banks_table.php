<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where non-cash money moves through.
     *
     * A table rather than an enum, on the same reasoning as `expense_categories`
     * and unlike `payment_method`: how you were paid is one of three things
     * everywhere, but which banks a business holds accounts at is that business's
     * own list, and "what came through this account" is only answerable if the
     * user could name the account.
     *
     * Only the name is required, and it is unique — it is what identifies the
     * bank on every dropdown and every figure. The account number is filing:
     * useful for telling two accounts at the same bank apart, never required to
     * record a payment.
     *
     * A bank is NOT a currency. An account held in dollars still records its
     * payments in base-currency minor units like everything else, with the
     * document's own `currency` and `exchange_rate` recording what changed hands.
     */
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('account_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
