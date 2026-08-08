<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A receipt may now create more than one batch.
     *
     * Landed cost rarely divides evenly: three units costing $10.00 all in
     * works out at $3.33 each, and a cent goes missing. Rather than round it
     * away, the total is allocated across the individual units and equal costs
     * are grouped, so that line becomes two batches — two units at $3.33 and
     * one at $3.34. The books then reconcile to the penny.
     */
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropUnique(['received_movement_id']);
            $table->index('received_movement_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropIndex(['received_movement_id']);
            $table->unique('received_movement_id');
        });
    }
};
