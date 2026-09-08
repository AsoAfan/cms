<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Support\Money;

/**
 * Goods sold that the shop does not have — the loan the business is carrying.
 *
 *     owed = what uncommitted sales ask for − what is on the shelf
 *
 * Selling something that is out of stock is allowed: an order can be taken for
 * goods still coming in. What it creates is an obligation the other way round
 * from a customer's loan — they are owed product rather than owing money — and
 * this is where that obligation is read back. It clears itself the moment the
 * stock is purchased, because it is derived from the ledger rather than written
 * down: there is no backorder table, and there must not be one, for the reason
 * there is no stock balance column and no customer balance column. Every sale,
 * every edit, every receipt moves it.
 *
 * Valued at `products.cost_price` — what it will cost to make good on it. The
 * FIFO ledger cannot cost goods that have never been bought, and the selling
 * price would state the debt at what the customer will pay rather than what the
 * shop must find.
 *
 * **Only uncommitted sales owe anything.** `committed_at` is what says the goods
 * have left; a sale already out of the ledger has been covered by real stock and
 * counting it again would owe the same items twice.
 */
final class GoodsOwedQuery
{
    public function __construct(private readonly StockOnHandQuery $onHand) {}

    /**
     * Everything the business owes, one row per product, worst shortfall first.
     *
     * Demand is summed across every uncommitted sale rather than taken a sale at
     * a time: two orders for five of a product with three on the shelf are seven
     * short between them, not two and two. That is the figure that answers "what
     * do I have to buy", which is what the tile is for.
     *
     * Read as at now, not as at a date — what must be bought is a position, the
     * same way a customer's debt is.
     *
     * @return list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}>
     */
    public function get(): array
    {
        $ordered = SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereNull('sales.committed_at')
            ->groupBy('sale_lines.product_id')
            ->selectRaw('sale_lines.product_id as product_id, SUM(sale_lines.quantity) as quantity')
            ->pluck('quantity', 'product_id')
            ->map(static fn (mixed $quantity): int => (int) $quantity);

        if ($ordered->isEmpty()) {
            return [];
        }

        $onHand = $this->onHand->get();
        $products = Product::query()->findMany($ordered->keys())->keyBy('id');

        $rows = [];

        foreach ($ordered as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $rows[] = $this->row($product, $quantity, $onHand[$productId] ?? 0);
        }

        return $this->owing($rows);
    }

    /**
     * What one sale is short, exactly as `IssueSaleAction` will measure it when
     * the sale is sent out.
     *
     * That means the same as-at date the action uses — the end of the day the
     * sale was made — because stock which had not arrived by then cannot have
     * been sold on it. A screen reading against today's shelf would say a sale
     * could go out and then watch the server refuse it.
     *
     * Empty once the sale is committed: its goods are already out of the ledger,
     * so comparing what it asked for against what is left would count the same
     * issue twice.
     *
     * @return list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}>
     */
    public function forSale(Sale $sale): array
    {
        if ($sale->isCommitted()) {
            return [];
        }

        $sale->loadMissing('lines.product');

        $asAt = $sale->sold_on->endOfDay();

        // Summed per product rather than read line by line, which is how
        // `IssueSaleAction` pre-flights the same sale. `SaleRequest` forbids
        // naming a product twice, so the two agree either way — but they have
        // to be measuring the same thing for that to stay true.
        $wanted = $sale->lines
            ->groupBy('product_id')
            ->map(static fn ($lines): int => (int) $lines->sum('quantity'));

        // `array_values` because `unique()` keeps the keys of the lines it kept,
        // so the rows would otherwise come back gapped rather than as a list.
        $rows = array_values(
            $sale->lines
                ->unique('product_id')
                ->map(fn (SaleLine $line): array => $this->row(
                    $line->product,
                    $wanted[$line->product_id],
                    $this->onHand->forProduct($line->product, $asAt),
                ))
                ->all()
        );

        return $this->owing($rows);
    }

    /**
     * The orders held up by each product, oldest first — who is waiting.
     *
     * Kept apart from `get()` because the sale screen has no use for it and
     * should not pay for the query. Same scope as everything else here: only
     * sales still holding their goods in the ledger owe anything.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<array{id: int, number: string}>>
     */
    public function waitingOn(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereNull('sales.committed_at')
            ->whereIn('sale_lines.product_id', $productIds)
            ->distinct()
            ->orderBy('sales.sold_on')
            ->orderBy('sales.id')
            ->get(['sale_lines.product_id', 'sales.id as sale_id', 'sales.number']);

        // Grouped by hand rather than with `groupBy()->map()`: the query is a
        // base one, so every column arrives untyped, and appending in the order
        // the rows came back is what keeps each product's list oldest-first.
        $waiting = [];

        foreach ($rows as $row) {
            $waiting[(int) $row->product_id][] = [
                'id' => (int) $row->sale_id,
                'number' => (string) $row->number,
            ];
        }

        return $waiting;
    }

    /**
     * The rows added up, for a tile or a summary line.
     *
     * Static and pure so the sale screen, the dashboard and the report all state
     * the obligation the same way from whichever rows they happen to hold.
     *
     * @param  list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}>  $rows
     * @return array{products: int, items: int, value: Money}
     */
    public static function summarise(array $rows): array
    {
        return [
            'products' => count($rows),
            'items' => array_sum(array_column($rows, 'short')),
            'value' => Money::sum(...array_column($rows, 'value')),
        ];
    }

    /**
     * What the business owes altogether, at cost — the figure the tiles show.
     */
    public function total(): Money
    {
        return self::summarise($this->get())['value'];
    }

    /**
     * @return array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}
     */
    private function row(Product $product, int $wanted, int $available): array
    {
        $short = max(0, $wanted - $available);

        return [
            'product_id' => $product->id,
            'product' => $product->name,
            'quantity' => $wanted,
            'on_hand' => $available,
            'short' => $short,
            'value' => $product->cost_price->multipliedBy($short),
        ];
    }

    /**
     * Only the products actually short, biggest shortfall first and then by
     * name, so the list reads as an order to place.
     *
     * @param  list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}>  $rows
     * @return list<array{product_id: int, product: string, quantity: int, on_hand: int, short: int, value: Money}>
     */
    private function owing(array $rows): array
    {
        $owing = array_values(array_filter($rows, static fn (array $row): bool => $row['short'] > 0));

        usort($owing, static fn (array $a, array $b): int => $b['short'] <=> $a['short']
            ?: strcmp($a['product'], $b['product']));

        return $owing;
    }
}
