<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who bought it, and how much of it they have actually paid for.
     *
     * `customer_id` is REQUIRED. Every sale names a buyer, and counter trade is
     * recorded against the seeded "Walk-in" customer, which is what the sale
     * form opens on — so fast entry costs no keystrokes and no sale is left
     * without an owner. The FK restricts on delete: a customer with sales
     * behind them cannot be removed and is never quietly detached from their
     * history.
     *
     * `amount_paid` is what the customer handed over on the sale itself, in base
     * currency minor units. It is a recorded fact of the transaction — the same
     * kind of fact as a line's `unit_price` — not a cache of anything derivable.
     * What it makes possible is the whole point of this migration:
     *
     *     paid in full  → amount_paid = the total, nothing owed
     *     part paid     → the shortfall is on the customer's account
     *     on account    → amount_paid = 0, the whole invoice is a loan
     *
     * What remains owed is DERIVED, never stored: total − amount_paid − whatever
     * later payments were allocated to the sale. See `CustomerBalanceQuery` for
     * the one place that arithmetic lives.
     *
     * A debt only exists once the goods are the customer's — a sale counts from
     * `proceed`, matching the status at which it reaches the reports. An
     * `ordered` sale with money against it is a deposit on something not yet
     * delivered, and owes nobody anything.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('number')
                ->constrained()
                ->restrictOnDelete();

            $table->bigInteger('amount_paid')
                ->default(0)
                ->after('payment_method');
        });

        $this->attributeExistingSalesToWalkIn();

        // Nullable only long enough to land on rows written before customers
        // existed. Every sale from here has a buyer.
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'amount_paid']);
        });
    }

    /**
     * Sales recorded before there were customers were over-the-counter trade,
     * so they become the walk-in customer's — and they were paid in full at the
     * till, which is what `amount_paid` has to say about them or they would all
     * read as unpaid debt.
     */
    private function attributeExistingSalesToWalkIn(): void
    {
        if (DB::table('sales')->count() === 0) {
            return;
        }

        $walkIn = DB::table('customers')->where('name', 'Walk-in')->value('id')
            ?? DB::table('customers')->insertGetId([
                'name' => 'Walk-in',
                'notes' => 'Counter trade with nobody named.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('sales')->whereNull('customer_id')->update(['customer_id' => $walkIn]);

        DB::table('sales')->update([
            'amount_paid' => DB::raw(
                '(select coalesce(sum(sale_lines.quantity * sale_lines.unit_price - sale_lines.discount), 0)'
                .' from sale_lines where sale_lines.sale_id = sales.id)'
            ),
        ]);
    }
};
