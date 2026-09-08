<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::query()->create($request->validated());

        Flash::success('Category added.');

        return back();
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        Flash::success('Category renamed.');

        return back();
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        // Deleting a category that has expenses would orphan them, and the
        // database refuses it anyway — say so plainly instead.
        if ($category->expenses()->exists()) {
            Flash::error('That category still has expenses against it.');

            return back();
        }

        $category->delete();

        Flash::success('Category deleted.');

        return back();
    }
}
