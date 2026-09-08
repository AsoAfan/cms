<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who you buy from.
     *
     * There is no matching `customers` table: sales here are over the counter
     * and are not tied to a named buyer. If that changes, customers arrive as
     * their own table and a nullable `customer_id` on sales — additive, and no
     * migration of posted documents.
     *
     * Only the name is required. A supplier you have just met is often a name
     * and a phone number, and the form should not stand in the way of writing
     * that down.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
