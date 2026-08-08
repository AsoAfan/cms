<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\PostSaleAction;
use App\Actions\Sales\SaveSaleAction;
use App\Enums\PaymentMethod;
use App\Exceptions\SaleNotPostableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Queries\StockOnHandQuery;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly StockOnHandQuery $onHand) {}

    public function index(): Response
    {
        $table = $this->table(Sale::query()->with('lines')->withCount('lines'))
            ->searchable(['number', 'notes'])
            ->sortable(['number', 'sold_on', 'status'], default: 'sold_on', direction: 'desc')
            ->filterable(['status', 'payment_method']);

        $paginator = $table->paginate();

        $paginator->through(fn (Sale $sale): array => [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_on' => $sale->sold_on->toDateString(),
            'status' => $sale->status->value,
            'payment_method' => $sale->payment_method->label(),
            'lines_count' => $sale->lines_count,
            'total' => $sale->total()->minorUnits,
        ]);

        return Inertia::render('sales/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sales/create', $this->formOptions());
    }

    public function store(SaleRequest $request, SaveSaleAction $save): RedirectResponse
    {
        $sale = $save->handle($request->saleHeader(), $request->lines());

        Flash::success("{$sale->number} saved as a draft.");

        return to_route('sales.edit', $sale);
    }

    public function show(Sale $sale): Response
    {
        $sale->load(['lines.product', 'lines.stockMovements.consumptions.batch']);

        return Inertia::render('sales/show', [
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'sold_on' => $sale->sold_on->toDateString(),
                'status' => $sale->status->value,
                'payment_method' => $sale->payment_method->label(),
                'currency' => $sale->currency,
                'exchange_rate' => $sale->exchangeRate(),
                'notes' => $sale->notes,
                'posted_at' => $sale->posted_at?->toDateTimeString(),
                'total' => $sale->total()->minorUnits,
                'cost_of_goods_sold' => $sale->costOfGoodsSold()->minorUnits,
                'gross_profit' => $sale->grossProfit()->minorUnits,
                'lines' => $sale->lines->map(fn (SaleLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->product->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price->minorUnits,
                    'discount' => $line->discount->minorUnits,
                    'net_total' => $line->netTotal()->minorUnits,
                    'cost_of_goods_sold' => $line->costOfGoodsSold()->minorUnits,
                    'gross_profit' => $line->grossProfit()->minorUnits,
                ])->values(),
            ],
        ]);
    }

    public function edit(Sale $sale): Response|RedirectResponse
    {
        if ($sale->isPosted()) {
            return to_route('sales.show', $sale);
        }

        $sale->load('lines.product');

        return Inertia::render('sales/edit', [
            ...$this->formOptions(),
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'sold_on' => $sale->sold_on->toDateString(),
                'payment_method' => $sale->payment_method->value,
                'notes' => $sale->notes,
                // Amounts are stored in the base currency, so the form reopens
                // in it whatever the sale was originally rung up in.
                'currency' => config('money.currency'),
                'lines' => $sale->lines->map(fn (SaleLine $line): array => [
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price->toDecimal(),
                    'unit_price_currency' => config('money.currency'),
                    'discount' => $line->discount->toDecimal(),
                    'discount_currency' => config('money.currency'),
                ])->values(),
            ],
        ]);
    }

    public function update(SaleRequest $request, Sale $sale, SaveSaleAction $save): RedirectResponse
    {
        try {
            $save->handle($request->saleHeader(), $request->lines(), $sale);
        } catch (SaleNotPostableException $exception) {
            Flash::error($exception->getMessage());

            return to_route('sales.show', $sale);
        }

        Flash::success('Draft saved.');

        return back();
    }

    /**
     * Post the sale — this is what takes stock out.
     */
    public function post(Sale $sale, PostSaleAction $post): RedirectResponse
    {
        try {
            $post->handle($sale);
        } catch (SaleNotPostableException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$sale->number} posted. Stock updated.");

        return to_route('sales.show', $sale);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        if ($sale->isPosted()) {
            Flash::error("{$sale->number} is posted and cannot be deleted.");

            return to_route('sales.show', $sale);
        }

        $sale->delete();

        Flash::success('Draft deleted.');

        return to_route('sales.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $quantities = $this->onHand->get();

        return [
            'paymentMethods' => $this->paymentMethods(),
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
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethods(): array
    {
        return array_map(
            static fn (PaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            PaymentMethod::cases(),
        );
    }
}
