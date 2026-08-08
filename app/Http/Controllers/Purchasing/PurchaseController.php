<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\PostPurchaseAction;
use App\Actions\Purchasing\SavePurchaseAction;
use App\Enums\CostAllocationMethod;
use App\Exceptions\PurchaseNotPostableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Purchase::query()->with('supplier:id,name')->withCount('lines'))
            ->searchable(['number', 'notes'])
            ->sortable(['number', 'invoiced_on', 'status'], default: 'invoiced_on', direction: 'desc')
            ->filterable([
                'status',
                'supplier_id',
            ]);

        $paginator = $table->paginate();

        $paginator->through(fn (Purchase $purchase): array => [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'supplier' => $purchase->supplier->name,
            'invoiced_on' => $purchase->invoiced_on->toDateString(),
            'status' => $purchase->status->value,
            'lines_count' => $purchase->lines_count,
            'total' => $purchase->total()->minorUnits,
        ]);

        return Inertia::render('purchases/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchases/create', $this->formOptions());
    }

    public function store(PurchaseRequest $request, SavePurchaseAction $save): RedirectResponse
    {
        $purchase = $save->handle($request->invoiceHeader(), $request->lines(), $request->additionalCosts());

        Flash::success("{$purchase->number} saved as a draft.");

        return to_route('purchases.edit', $purchase);
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier', 'lines.product', 'lines.stockMovements', 'additionalCosts']);

        return Inertia::render('purchases/show', [
            'purchase' => $this->detail($purchase),
        ]);
    }

    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        if ($purchase->isPosted()) {
            return to_route('purchases.show', $purchase);
        }

        $purchase->load(['lines.product', 'additionalCosts']);

        return Inertia::render('purchases/edit', [
            ...$this->formOptions(),
            'purchase' => [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier_id' => $purchase->supplier_id,
                'invoiced_on' => $purchase->invoiced_on->toDateString(),
                'notes' => $purchase->notes,
                'lines' => $purchase->lines->map(fn (PurchaseLine $line): array => [
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost->toDecimal(),
                    'discount' => $line->discount->toDecimal(),
                ])->values(),
                'additional_costs' => $purchase->additionalCosts->map(
                    fn (PurchaseAdditionalCost $cost): array => [
                        'label' => $cost->label,
                        'amount' => $cost->amount->toDecimal(),
                        'allocation_method' => $cost->allocation_method->value,
                    ]
                )->values(),
            ],
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase, SavePurchaseAction $save): RedirectResponse
    {
        try {
            $save->handle($request->invoiceHeader(), $request->lines(), $request->additionalCosts(), $purchase);
        } catch (PurchaseNotPostableException $exception) {
            Flash::error($exception->getMessage());

            return to_route('purchases.show', $purchase);
        }

        Flash::success('Draft saved.');

        return back();
    }

    /**
     * Post the draft — this is what writes stock, and it happens once.
     */
    public function post(Purchase $purchase, PostPurchaseAction $post): RedirectResponse
    {
        try {
            $post->handle($purchase);
        } catch (PurchaseNotPostableException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$purchase->number} posted. Stock updated.");

        return to_route('purchases.show', $purchase);
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        if ($purchase->isPosted()) {
            Flash::error("{$purchase->number} is posted and cannot be deleted.");

            return to_route('purchases.show', $purchase);
        }

        $purchase->delete();

        Flash::success('Draft deleted.');

        return to_route('purchases.index');
    }

    /**
     * A posted invoice with the trace of what it did to stock.
     *
     * @return array<string, mixed>
     */
    private function detail(Purchase $purchase): array
    {
        $post = app(PostPurchaseAction::class);
        $landed = $purchase->isPosted() || $purchase->lines->isNotEmpty()
            ? $post->landedTotals($purchase)
            : [];

        return [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'supplier' => $purchase->supplier->name,
            'invoiced_on' => $purchase->invoiced_on->toDateString(),
            'status' => $purchase->status->value,
            'notes' => $purchase->notes,
            'posted_at' => $purchase->posted_at?->toDateTimeString(),
            'goods_total' => $purchase->goodsTotal()->minorUnits,
            'additional_costs_total' => $purchase->additionalCostsTotal()->minorUnits,
            'total' => $purchase->total()->minorUnits,
            'lines' => $purchase->lines->map(fn (PurchaseLine $line): array => [
                'id' => $line->id,
                'product' => $line->product->name,
                'code' => $line->product->code,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost->minorUnits,
                'discount' => $line->discount->minorUnits,
                'net_total' => $line->netTotal()->minorUnits,
                'landed_total' => ($landed[$line->id] ?? $line->netTotal())->minorUnits,
                // What the line put on the shelf, and at what cost per unit.
                'batches' => StockBatch::query()
                    ->whereIn('received_movement_id', $line->stockMovements->pluck('id'))
                    ->get()
                    ->map(fn (StockBatch $batch): array => [
                        'quantity' => $batch->quantity_received,
                        'unit_cost' => $batch->unit_cost->minorUnits,
                    ])->values(),
            ])->values(),
            'additional_costs' => $purchase->additionalCosts->map(
                fn (PurchaseAdditionalCost $cost): array => [
                    'label' => $cost->label,
                    'amount' => $cost->amount->minorUnits,
                    'allocation_method' => $cost->allocation_method->value,
                    'allocation_label' => $cost->allocation_method->label(),
                ]
            )->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'default_cost_price'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'default_cost_price' => $product->default_cost_price?->toDecimal(),
                ]),
            'allocationMethods' => collect(CostAllocationMethod::cases())->map(
                fn (CostAllocationMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'description' => $method->description(),
                ]
            ),
        ];
    }
}
