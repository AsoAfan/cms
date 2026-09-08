<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\RevertSaleAction;
use App\Actions\Sales\SaveSaleAction;
use App\Actions\Sales\SetSaleStatusAction;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\SaleLedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaleNumberRequest;
use App\Http\Requests\Sales\SaleRequest;
use App\Http\Requests\Sales\SaleStatusRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Queries\GoodsOwedQuery;
use App\Queries\StockOnHandQuery;
use App\Services\CurrencyService;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales are two screens: the list, with a drawer over it for ringing one up,
 * and the invoice itself. There is no create page and no edit page — editing
 * opens the same drawer the sale was rung up in.
 */
class SaleController extends Controller
{
    public function __construct(
        private readonly StockOnHandQuery $onHand,
        private readonly GoodsOwedQuery $goodsOwed,
        private readonly CurrencyService $currencies,
    ) {}

    public function index(): Response
    {
        $table = $this->table(
            Sale::query()
                ->with(['lines', 'customer:id,name', 'bank:id,name'])
                ->withCount('lines')
                // Summed in SQL so a page of invoices stays three queries rather
                // than one per row.
                ->withSum('paymentAllocations as allocated_minor_units', 'amount')
        )
            ->searchable(['number', 'notes'])
            ->sortable(['number', 'sold_on', 'status'], default: 'sold_on', direction: 'desc')
            ->filterable(['status', 'payment_method', 'customer_id', 'bank_id']);

        $paginator = $table->paginate();

        $paginator->through(fn (Sale $sale): array => [
            'id' => $sale->id,
            'number' => $sale->number,
            'customer' => $sale->customer->name,
            'customer_id' => $sale->customer_id,
            'sold_on' => $sale->sold_on->toDateString(),
            'status' => $sale->status->value,
            'payment_method' => $sale->payment_method->label(),
            'bank' => $sale->bank?->name,
            'lines_count' => $sale->lines_count,
            'total' => $sale->total()->minorUnits,
            // What is still on the customer's loan. Undelivered sales owe
            // nothing, which `Sale::outstanding()` is what decides.
            'outstanding' => $sale->isDelivered()
                ? $sale->total()
                    ->minus($sale->amount_paid)
                    ->minus(Money::fromMinorUnits((int) $sale->allocated_minor_units))
                    ->minorUnits
                : 0,
        ]);

        return Inertia::render('sales/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            // The next reference, so the drawer can show what it is about to
            // write rather than a blank where the number will be.
            'nextNumber' => Sale::nextNumber(),
            ...$this->formOptions(),
        ]);
    }

    public function store(SaleRequest $request, SaveSaleAction $save): RedirectResponse
    {
        try {
            $sale = $save->handle($request->saleHeader(), $request->lines());
        } catch (SaleLedgerException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$sale->number} recorded.");

        return back();
    }

    public function show(Sale $sale): Response
    {
        $sale->load(['customer', 'bank', 'lines.product', 'lines.stockMovements.consumptions.batch', 'paymentAllocations']);

        return Inertia::render('sales/show', [
            'sale' => $this->detail($sale),
            ...$this->formOptions(),
        ]);
    }

    public function update(SaleRequest $request, Sale $sale, SaveSaleAction $save): RedirectResponse
    {
        try {
            $save->handle($request->saleHeader(), $request->lines(), $sale);
        } catch (SaleLedgerException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$sale->number} updated.");

        return back();
    }

    /**
     * Refile the sale under another reference, edited in place on the invoice.
     *
     * Deliberately not `update()`: the reference is filing, not ledger data.
     * Sending it back through `SaveSaleAction` would put the stock back and
     * take it out again to rename a sale, which can fail outright — and a
     * reference must always be correctable.
     */
    public function rename(SaleNumberRequest $request, Sale $sale): RedirectResponse
    {
        $sale->update(['number' => $request->reference()]);

        Flash::success("Filed under {$sale->number}.");

        return back();
    }

    /**
     * Move the sale along. Marking it on its way is what takes the stock out;
     * putting it back to ordered returns it to the shelf.
     */
    public function status(
        SaleStatusRequest $request,
        Sale $sale,
        SetSaleStatusAction $setStatus,
    ): RedirectResponse {
        $status = $request->status();

        try {
            $setStatus->handle($sale, $status);
        } catch (SaleLedgerException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success($status === SaleStatus::OnTheWay
            ? "{$sale->number} sent out. Stock updated."
            : "{$sale->number} is now {$status->label()}.");

        return back();
    }

    public function destroy(Sale $sale, RevertSaleAction $revert): RedirectResponse
    {
        $revert->handle($sale);

        $number = $sale->number;

        $sale->delete();

        Flash::success("{$number} deleted.");

        return to_route('sales.index');
    }

    /**
     * The invoice as it reads on screen, and as the edit drawer reopens it.
     *
     * Amounts come twice over: minor units for the figures on the page, and
     * decimal strings for the fields in the drawer, which round-trip what was
     * typed rather than a number the client divided.
     *
     * @return array<string, mixed>
     */
    private function detail(Sale $sale): array
    {
        $base = $this->currencies->base();

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'customer' => $sale->customer->name,
            'customer_id' => $sale->customer_id,
            // For the printed invoice's "issued to" block. Filing, all of it
            // optional — a walk-in has a name and nothing else.
            'customer_phone' => $sale->customer->phone,
            'customer_address' => $sale->customer->address,
            'sold_on' => $sale->sold_on->toDateString(),
            'status' => $sale->status->value,
            'payment_method' => $sale->payment_method->value,
            'payment_method_label' => $sale->payment_method->label(),
            'bank' => $sale->bank?->name,
            // A string because the drawer's select holds one, and a select
            // cannot hold null — empty is how it says "no bank".
            'bank_id' => $sale->bank_id === null ? '' : (string) $sale->bank_id,
            // The money side of the invoice: what was handed over, what has
            // been paid since, and what is still on the customer's loan.
            'amount_paid' => $sale->amount_paid->minorUnits,
            'amount_paid_decimal' => $sale->amount_paid->toDecimal(),
            'paid_to_date' => $sale->paidToDate()->minorUnits,
            'outstanding' => $sale->outstanding()->minorUnits,
            'delivered' => $sale->isDelivered(),
            'currency' => $sale->currency,
            'exchange_rate' => $sale->exchangeRate(),
            'notes' => $sale->notes,
            'committed_at' => $sale->committed_at?->toDateTimeString(),
            // What this sale sold that is not on the shelf: goods the shop owes
            // and has to buy before the sale can be sent out. Derived, so it
            // empties itself the moment the stock is purchased.
            ...$this->owedOnSale($sale),
            'total' => $sale->total()->minorUnits,
            'total_quantity' => $sale->totalQuantity(),
            'cost_of_goods_sold' => $sale->costOfGoodsSold()->minorUnits,
            'gross_profit' => $sale->grossProfit()->minorUnits,
            'lines' => $sale->lines->map(fn (SaleLine $line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => $line->product->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price->minorUnits,
                'discount' => $line->discount->minorUnits,
                'net_total' => $line->netTotal()->minorUnits,
                // Amounts are stored in the base currency, so the drawer reopens
                // in it whatever the sale was originally rung up in.
                'unit_price_decimal' => $line->unit_price->toDecimal(),
                'discount_decimal' => $line->discount->toDecimal(),
            ])->values(),
            'base_currency' => $base,
        ];
    }

    /**
     * What this sale sells that the shop has not got.
     *
     * Measured exactly as `IssueSaleAction` will measure it, so the screen and
     * the server never disagree about whether the sale can go out — the page
     * disables the two statuses that release stock on the strength of this.
     *
     * @return array{owed: list<array{product: string, quantity: int, on_hand: int, short: int, value: int}>, owed_value: int, owed_items: int}
     */
    private function owedOnSale(Sale $sale): array
    {
        $rows = $this->goodsOwed->forSale($sale);
        $summary = GoodsOwedQuery::summarise($rows);

        return [
            'owed' => array_map(static fn (array $row): array => [
                'product' => $row['product'],
                'quantity' => $row['quantity'],
                'on_hand' => $row['on_hand'],
                'short' => $row['short'],
                'value' => $row['value']->minorUnits,
            ], $rows),
            // At cost: what it will take to make good on the sale, not what the
            // customer is paying for it.
            'owed_value' => $summary['value']->minorUnits,
            'owed_items' => $summary['items'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $quantities = $this->onHand->get();

        return [
            'paymentMethods' => PaymentMethod::options(),
            'statuses' => SaleStatus::options(),
            'customers' => $this->customers(),
            'banks' => Bank::options(),
            // On-hand travels with each product so the till can warn before
            // someone rings up something that is not there.
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'selling_price'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'selling_price' => $product->selling_price->toDecimal(),
                    'on_hand' => $quantities[$product->id] ?? 0,
                ]),
        ];
    }

    /**
     * Who a sale can be rung up against, walk-in first so the common case is
     * already selected when the screen opens.
     *
     * @return list<array{id: int, name: string}>
     */
    private function customers(): array
    {
        return array_values(
            Customer::query()
                ->orderByRaw('case when name = ? then 0 else 1 end', [Customer::WALK_IN])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ])
                ->all()
        );
    }
}
