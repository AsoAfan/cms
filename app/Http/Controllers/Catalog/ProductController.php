<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
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
        $table = $this->table(Product::query())
            ->searchable(['name', 'code', 'description'])
            ->sortable([
                'name',
                'code',
                'default_cost_price',
                'default_selling_price',
                'created_at',
            ], default: 'name')
            ->filterable([
                'status' => fn (Builder $query, string $value) => $query->where('is_active', $value === 'active'),
            ]);

        return Inertia::render('catalog/products/index', $this->tableProps($table));
    }

    public function create(): Response
    {
        return Inertia::render('catalog/products/create');
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::query()->create($request->payload());

        Flash::success('Product created.');

        return to_route('products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('catalog/products/edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'description' => $product->description,
                'default_cost_price' => $product->default_cost_price?->toDecimal(),
                'default_selling_price' => $product->default_selling_price?->toDecimal(),
                'is_active' => $product->is_active,
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->payload());

        Flash::success('Product updated.');

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Flash::success('Product deleted.');

        return to_route('products.index');
    }
}
