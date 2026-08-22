<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The currencies this business deals in, and which one it keeps its books in.
     *
     * A table rather than a config array: which currencies matter is a fact
     * about a business, not about a deployment, and it changes when a new
     * supplier starts invoicing in something new — not when somebody edits a
     * file and redeploys.
     *
     * **Exactly one row is the base**, and every monetary column in the whole
     * application is minor units of it. `is_base` is not a preference: changing
     * it re-denominates the books, which is why it can only move while there is
     * no money on record (see CurrencyService::makeBase()).
     *
     * `fraction_digits` is display only — storage is always two decimal places,
     * whatever a currency is conventionally quoted in, so that splitting a
     * landed cost stays exact. Dinars are shown to none.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // ISO 4217, and the identity everything else references: documents
            // record `currency` as a code, and so do exchange rates.
            $table->char('code', 3)->unique();

            $table->string('name');
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('fraction_digits')->default(2);
            $table->boolean('is_base')->default(false);

            $table->timestamps();

            $table->index('is_base');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
