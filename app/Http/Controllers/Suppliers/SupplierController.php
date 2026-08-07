<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Supplier;
use App\Support\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Supplier::query())
            ->searchable(['name', 'phone', 'email', 'address'])
            ->sortable(['name', 'created_at'], default: 'name')
            ->filterable([
                'status' => fn (Builder $query, string $value) => $query->where('is_active', $value === 'active'),
            ]);

        return Inertia::render('suppliers/index', $this->tableProps($table));
    }

    public function create(): Response
    {
        return Inertia::render('suppliers/create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->payload());

        Flash::success('Supplier created.');

        return to_route('suppliers.edit', $supplier);
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/edit', [
            'supplier' => $supplier->only([
                'id', 'name', 'phone', 'email', 'address', 'notes', 'is_active',
            ]),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->payload());

        Flash::success('Supplier updated.');

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        Flash::success('Supplier deleted.');

        return to_route('suppliers.index');
    }
}
