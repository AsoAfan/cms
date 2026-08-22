<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\RevertPurchaseAction;
use App\Actions\Purchasing\SavePurchaseAction;
use App\Actions\Purchasing\SetPurchaseStatusAction;
use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseLedgerException;
use App\Exceptions\StockAlreadyConsumedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseRequest;
use App\Http\Requests\Purchasing\PurchaseStatusRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Services\CurrencyService;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchases are two screens: the list, with a drawer over it for writing a new
 * invoice, and the invoice itself. There is no create page and no edit page —
 * editing opens the same drawer the invoice was written in.
 */
class PurchaseController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Purchase::query()->withCount('lines'))
            ->searchable(['number', 'notes'])
            ->sortable(['number', 'invoiced_on', 'status'], default: 'invoiced_on', direction: 'desc')
            ->filterable(['status']);

        $paginator = $table->paginate();

        $paginator->through(fn (Purchase $purchase): array => [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'invoiced_on' => $purchase->invoiced_on->toDateString(),
            'status' => $purchase->status->value,
            'lines_count' => $purchase->lines_count,
            'total' => $purchase->total()->minorUnits,
        ]);

        return Inertia::render('purchases/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            // The next reference, so the drawer can show what it is about to
            // write rather than a blank where the number will be.
            'nextNumber' => Purchase::nextNumber(),
            ...$this->formOptions(),
        ]);
    }

    public function store(PurchaseRequest $request, SavePurchaseAction $save): RedirectResponse
    {
        try {
            $purchase = $save->handle(
                $request->invoiceHeader(),
                $request->lines(),
                $request->additionalCosts(),
            );
        } catch (PurchaseLedgerException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$purchase->number} recorded.");

        return back();
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['lines.product', 'additionalCosts']);

        return Inertia::render('purchases/show', [
            'purchase' => $this->detail($purchase),
            ...$this->formOptions(),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase, SavePurchaseAction $save): RedirectResponse
    {
        try {
            $save->handle(
                $request->invoiceHeader(),
                $request->lines(),
                $request->additionalCosts(),
                $purchase,
            );
        } catch (PurchaseLedgerException|StockAlreadyConsumedException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success("{$purchase->number} updated.");

        return back();
    }

    /**
     * Move the invoice along. Reaching `proceed` is what puts the goods on the
     * shelf, and leaving it takes them back off.
     */
    public function status(
        PurchaseStatusRequest $request,
        Purchase $purchase,
        SetPurchaseStatusAction $setStatus,
    ): RedirectResponse {
        $status = $request->status();

        try {
            $setStatus->handle($purchase, $status);
        } catch (PurchaseLedgerException|StockAlreadyConsumedException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success($status === PurchaseStatus::Proceed
            ? "{$purchase->number} arrived. Stock updated."
            : "{$purchase->number} is now {$status->label()}.");

        return back();
    }

    public function destroy(Purchase $purchase, RevertPurchaseAction $revert): RedirectResponse
    {
        try {
            $revert->handle($purchase);
        } catch (StockAlreadyConsumedException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        $number = $purchase->number;

        $purchase->delete();

        Flash::success("{$number} deleted.");

        return to_route('purchases.index');
    }

    /**
     * The invoice as it reads on screen, and as the edit drawer reopens it.
     *
     * @return array<string, mixed>
     */
    private function detail(Purchase $purchase): array
    {
        $base = app(CurrencyService::class)->base();

        return [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'invoiced_on' => $purchase->invoiced_on->toDateString(),
            'status' => $purchase->status->value,
            'currency' => $purchase->currency,
            'exchange_rate' => $purchase->exchangeRate(),
            'notes' => $purchase->notes,
            'committed_at' => $purchase->committed_at?->toDateTimeString(),
            'goods_total' => $purchase->goodsTotal()->minorUnits,
            'additional_costs_total' => $purchase->additionalCostsTotal()->minorUnits,
            'total' => $purchase->total()->minorUnits,
            'total_quantity' => $purchase->totalQuantity(),
            'lines' => $purchase->lines->map(fn (PurchaseLine $line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => $line->product->name,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost->minorUnits,
                'discount' => $line->discount->minorUnits,
                'net_total' => $line->netTotal()->minorUnits,
                // Amounts are stored in the base currency, so the form reopens
                // in it whatever the invoice was originally typed in.
                'unit_cost_decimal' => $line->unit_cost->toDecimal(),
                'discount_decimal' => $line->discount->toDecimal(),
            ])->values(),
            'additional_costs' => $purchase->additionalCosts->map(
                fn (PurchaseAdditionalCost $cost): array => [
                    'label' => $cost->label,
                    'amount' => $cost->amount->minorUnits,
                    'amount_decimal' => $cost->amount->toDecimal(),
                    'allocation_method' => $cost->allocation_method->value,
                    'allocation_label' => $cost->allocation_method->label(),
                ]
            )->values(),
            'base_currency' => $base,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'cost_price'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'cost_price' => $product->cost_price->toDecimal(),
                ]),
            'allocationMethods' => collect(CostAllocationMethod::cases())->map(
                fn (CostAllocationMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'description' => $method->description(),
                ]
            ),
            'statuses' => PurchaseStatus::options(),
        ];
    }
}
