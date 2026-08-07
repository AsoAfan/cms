<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveAttributeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\AttributeRequest;
use App\Models\Attribute;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Attribute::query()->with('values')->withCount('values'))
            ->searchable(['name'])
            ->sortable(['name', 'values_count'], default: 'name');

        return Inertia::render('catalog/attributes/index', $this->tableProps($table));
    }

    public function store(AttributeRequest $request, SaveAttributeAction $save): RedirectResponse
    {
        $save->handle($request->payload());

        Flash::success('Attribute created.');

        return back();
    }

    public function update(AttributeRequest $request, Attribute $attribute, SaveAttributeAction $save): RedirectResponse
    {
        $save->handle($request->payload(), $attribute);

        Flash::success('Attribute updated.');

        return back();
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $inUse = $attribute->values()->whereHas('variants')->exists();

        if ($inUse) {
            Flash::error('That attribute is still used by product variants.');

            return back();
        }

        $attribute->delete();

        Flash::success('Attribute deleted.');

        return back();
    }
}
