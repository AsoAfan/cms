<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Attribute;
use App\Models\Product;
use App\Support\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Product::query()->withCount('variants'))
            // Searching the product name and the item code together is the
            // one lookup this screen exists for.
            ->searchable(['name', 'description'])
            ->sortable(['name', 'created_at', 'variants_count'], default: 'name')
            ->filterable([
                'status' => fn (Builder $query, string $value) => $query->where('is_active', $value === 'active'),
                'code' => fn (Builder $query, string $value) => $query->whereHas(
                    'variants',
                    fn (Builder $variants) => $variants->whereLike('code', "%{$value}%", caseSensitive: false)
                ),
            ]);

        return Inertia::render('catalog/products/index', $this->tableProps($table));
    }

    public function create(): Response
    {
        return Inertia::render('catalog/products/create', $this->formOptions());
    }

    public function store(ProductRequest $request, SaveProductAction $save): RedirectResponse
    {
        $product = $save->handle($request->payload());

        Flash::success('Product created.');

        return to_route('products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        $product->load(['variants.attributeValues.attribute']);

        return Inertia::render('catalog/products/edit', [
            ...$this->formOptions(),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'attribute_ids' => $product->attributesInUse()->pluck('id'),
                'variants' => $product->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'code' => $variant->code,
                    'default_cost_price' => $variant->default_cost_price?->toDecimal(),
                    'default_selling_price' => $variant->default_selling_price?->toDecimal(),
                    'is_active' => $variant->is_active,
                    'attribute_value_ids' => $variant->attributeValues->pluck('id'),
                    'label' => $variant->optionLabel(),
                ]),
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product, SaveProductAction $save): RedirectResponse
    {
        $save->handle($request->payload(), $product);

        Flash::success('Product updated.');

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Flash::success('Product deleted.');

        return to_route('products.index');
    }

    /**
     * Reference data the product form needs to build its option matrix.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'attributes' => Attribute::query()
                ->with(['values' => fn ($query) => $query->orderBy('value')])
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
