<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithPurchaseForm;
use App\Models\Customer;
use App\Queries\CustomerBalanceQuery;
use App\Queries\GoodsOwedQuery;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Both loans on one screen: what the business owes, and what it is owed.
 *
 * The two are answered by different queries because they are made of different
 * things — goods sold and never bought on one side, money invoiced and never
 * collected on the other — but they are the same question to whoever is running
 * the shop, and splitting them across two screens is why neither was ever read.
 *
 * Everything here is a **position**, not a flow: it is what stands today, not
 * what moved in a window. That is why this screen takes no date range, unlike
 * the report beside it in the sidebar.
 */
class LoanController extends Controller
{
    use InteractsWithPurchaseForm;

    public function __construct(
        private readonly GoodsOwedQuery $goodsOwed,
        private readonly CustomerBalanceQuery $balances,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('loans/index', [
            'goods' => $this->goodsOwedByYou(),
            'customers' => $this->owedToYou(),
            // The screen writes the order it is telling you to place, in the
            // same drawer the purchases list uses — see the trait for why the
            // props come from one place.
            ...$this->newPurchaseOptions(),
        ]);
    }

    /**
     * What the business owes in goods: the shopping list, and the orders each
     * line is holding up.
     *
     * Ordered demand is summed across every waiting sale, so this is what has
     * to be bought — not the sum of what each invoice is short, which double
     * counts nothing and under-buys everything.
     *
     * @return array{rows: list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: int, sales: list<array{id: int, number: string}>}>, value: int, items: int}
     */
    private function goodsOwedByYou(): array
    {
        $owed = $this->goodsOwed->get();
        $waiting = $this->goodsOwed->waitingOn(array_column($owed, 'product_id'));
        $summary = GoodsOwedQuery::summarise($owed);

        return [
            'rows' => array_map(static fn (array $row): array => [
                'product_id' => $row['product_id'],
                'product' => $row['product'],
                'quantity' => $row['quantity'],
                'on_hand' => $row['on_hand'],
                'short' => $row['short'],
                'value' => $row['value']->minorUnits,
                'sales' => $waiting[$row['product_id']] ?? [],
            ], $owed),
            'value' => $summary['value']->minorUnits,
            'items' => $summary['items'],
        ];
    }

    /**
     * What customers owe, biggest first — the same figure their own screen
     * shows, from the same query, so the two can never disagree.
     *
     * A customer in credit (they overpaid, or an invoice was moved back after
     * they had settled it) is listed too. It is a negative loan and hiding it
     * would leave the total unexplainable from the rows above it.
     *
     * @return array{rows: list<array{id: int, name: string, balance: int}>, total: int}
     */
    private function owedToYou(): array
    {
        $balances = $this->balances->get();

        $customers = Customer::query()
            ->findMany(array_keys($balances))
            ->keyBy('id');

        $rows = [];

        foreach ($balances as $customerId => $balance) {
            $customer = $customers->get($customerId);

            if ($customer === null) {
                continue;
            }

            $rows[] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'balance' => $balance->minorUnits,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['balance'] <=> $a['balance']
            ?: strcmp($a['name'], $b['name']));

        return [
            'rows' => $rows,
            'total' => Money::sum(...array_values($balances))->minorUnits,
        ];
    }
}
