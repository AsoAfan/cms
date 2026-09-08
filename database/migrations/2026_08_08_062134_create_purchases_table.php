<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase invoice.
     *
     * Carries no total: it is the sum of its lines and additional costs, and a
     * stored copy could only ever disagree with them.
     *
     * No supplier. An invoice is the goods, the money and the date; who it came
     * from was a field that had to be filled in or explicitly skipped on every
     * purchase, and nothing downstream ever read it.
     *
     * A purchase runs ordered → on the way → proceed, and only the last of
     * those is stock. Unlike the draft/posted pair this replaced, the status
     * can move back down as well as up: doing so reverses what the invoice put
     * in the ledger.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            // Our own filing reference, assigned on creation.
            $table->string('number')->unique();

            // The invoice date, which is also the date stock is taken in on —
            // deliveries get entered days late, and the ledger should say when
            // the goods actually arrived.
            $table->date('invoiced_on');

            $table->string('status');
            $table->text('notes')->nullable();

            // When the goods reached the ledger. Null until they do, and null
            // again if the invoice is moved back off `proceed`.
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'invoiced_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
