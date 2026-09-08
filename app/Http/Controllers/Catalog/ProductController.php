<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Queries\StockOnHandQuery;
use App\Support\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly StockOnHandQuery $onHand) {}

    /**
     * The catalogue, and the whole of the product UI: editing, creating and
     * the one-line buy and sell both happen in drawers over this list, so a
     * day's work never leaves the screen.
     */
    public function index(): Response
    {
        $table = $this->table(Product::query())
            ->searchable(['name', 'description'])
            ->sortable([
                'name',
                'cost_price',
                'selling_price',
                // Quantity is summed from the ledger, so it is ordered by a
                // correlated subquery rather than a column. Products that have
                // never moved sort as zero rather than dropping out.
                'quantity' => fn (Builder $query, string $direction) => $query->orderBy(
                    StockMovement::query()
                        ->selectRaw('COALESCE(SUM(quantity), 0)')
                        ->whereColumn('product_id', 'products.id'),
                    $direction === 'desc' ? 'desc' : 'asc',
                ),
            ], default: 'name');

        $paginator = $table->paginate();
        $quantities = $this->onHand->get();

        $paginator->through(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'cost_price' => $product->cost_price->minorUnits,
            'selling_price' => $product->selling_price->minorUnits,
            'quantity' => $quantities[$product->id] ?? 0,
        ]);

        return Inertia::render('catalog/products/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            // Both quick dialogs live on this page, so their options travel
            // with it rather than costing a round trip on first open.
            'paymentMethods' => PaymentMethod::options(),
            // Which account took the money, on a card or transfer sale.
            'banks' => Bank::options(),
            // Walk-in first, so selling across the counter needs no decision.
            // The quick dialog records a sale paid in full; putting one on a
            // customer's account is what the full sale screen is for.
            'customers' => Customer::query()
                ->orderByRaw('case when name = ? then 0 else 1 end', [Customer::WALK_IN])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ])
                ->all(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::query()->create($request->payload());

        Flash::success('Product created.');

        return back();
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->payload());

        Flash::success('Product updated.');

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        // The ledger must never describe a product that is gone, and the
        // foreign key enforces that. Say so rather than letting it 500.
        if (StockMovement::query()->where('product_id', $product->id)->exists()) {
            Flash::error("{$product->name} has stock history and cannot be deleted.");

            return back();
        }

        $product->delete();

        Flash::success('Product deleted.');

        return to_route('products.index');
    }
}
