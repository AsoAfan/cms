<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\Product;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private readonly StockOnHandQuery $onHand,
        private readonly InventoryValuationQuery $valuation,
    ) {}

    public function index(): Response
    {
        $table = $this->table(Product::query())
            ->searchable(['name', 'code'])
            ->sortable(['name', 'code'], default: 'name');

        $paginator = $table->paginate();
        $quantities = $this->onHand->get();
        $values = $this->valuation->get();

        // On hand and value are derived from the ledger rather than stored, so
        // they are attached to the page rows instead of being sortable columns.
        $paginator->through(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'is_active' => $product->is_active,
            'on_hand' => $quantities[$product->id] ?? 0,
            'value' => ($values[$product->id] ?? Money::zero())->minorUnits,
            'default_cost_price' => $product->default_cost_price?->toDecimal(),
        ]);

        return Inertia::render('stock/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            'totalValue' => $this->valuation->total()->minorUnits,
        ]);
    }

    public function store(StockAdjustmentRequest $request, InventoryService $inventory): RedirectResponse
    {
        $delta = $request->delta();

        $inventory->adjust(
            product: $request->product(),
            delta: $delta,
            reason: $request->string('reason')->toString(),
            unitCost: $request->filled('unit_cost')
                ? Money::fromDecimal($request->string('unit_cost')->toString())
                : null,
        );

        Flash::success(sprintf(
            'Stock adjusted by %s%d.',
            $delta > 0 ? '+' : '',
            $delta,
        ));

        return back();
    }
}
