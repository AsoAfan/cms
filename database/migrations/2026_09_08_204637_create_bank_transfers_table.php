<?php

use App\Support\ExchangeRates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money moved from one of the business's accounts to another.
     *
     * **One row, not two.** A transfer could have been a pair of
     * `bank_adjustments` — money out of one account, money in to the other —
     * and that is exactly why it is not: two rows can be half-written, half
     * deleted or edited apart, and the moment they disagree the business
     * appears to have gained or lost money it never had. Held as one row, "a
     * transfer never changes what the business holds in total" is a property of
     * the schema rather than a rule somebody has to maintain.
     *
     * It is also not a `bank_adjustment` for a second reason: an adjustment is
     * money trade cannot explain, and a transfer is explained — by the other
     * side of itself.
     *
     * `amount` is unsigned: money always runs `from_bank_id` → `to_bank_id`,
     * and moving it the other way is the same row written the other way round.
     * The two accounts must differ, which `BankTransferRequest` enforces — a
     * transfer to itself is a typo that would read as real money moving.
     *
     * `restrictOnDelete` on both sides, like every other bank reference: an
     * account with money moved through it is never deleted and never quietly
     * detached from that history.
     *
     * There is no update path. A transfer is what happened, so a wrong one is
     * deleted — which unwinds both sides at once, because there is only one row
     * — and recorded again.
     */
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('from_bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignId('to_bank_id')->constrained('banks')->restrictOnDelete();

            // Base-currency minor units, like every other amount.
            $table->unsignedBigInteger('amount');

            $table->date('occurred_on');

            // Optional: the two accounts and the date already say what this was.
            // An adjustment needs a reason because nothing else identifies it;
            // a transfer identifies itself.
            $table->string('reason')->nullable();

            $table->char('currency', 3)->default(config('money.currency'));
            $table->unsignedBigInteger('exchange_rate')->default(ExchangeRates::SCALE);

            $table->timestamps();

            $table->index(['from_bank_id', 'occurred_on']);
            $table->index(['to_bank_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
