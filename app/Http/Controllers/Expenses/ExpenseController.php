<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Expenses are recorded and reported on. Nothing here touches stock — see the
 * expenses migration for why, and `ArchTest` for the rule that enforces it.
 */
class ExpenseController extends Controller
{
    public function index(): Response
    {
        $table = $this->table(Expense::query()->with('category:id,name'))
            ->searchable(['reference', 'notes'])
            ->sortable(['spent_on', 'amount'], default: 'spent_on', direction: 'desc')
            ->filterable([
                'expense_category_id',
                'payment_method',
            ])
            ->dateRange('spent_on');

        $paginator = $table->paginate();

        // The total for the current filter, which is the figure someone came
        // to this screen to see.
        $filtered = Money::fromMinorUnits(
            $this->table(Expense::query())
                ->searchable(['reference', 'notes'])
                ->filterable(['expense_category_id', 'payment_method'])
                ->dateRange('spent_on')
                ->sum('amount')
        );

        $paginator->through(fn (Expense $expense): array => [
            'id' => $expense->id,
            'category' => $expense->category->name,
            'category_id' => $expense->expense_category_id,
            'amount' => $expense->amount->minorUnits,
            'spent_on' => $expense->spent_on->toDateString(),
            'payment_method' => $expense->payment_method->value,
            'payment_method_label' => $expense->payment_method->label(),
            'reference' => $expense->reference,
            'notes' => $expense->notes,
        ]);

        return Inertia::render('expenses/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            'categories' => ExpenseCategory::query()
                ->withCount('expenses')
                ->orderBy('name')
                ->get(['id', 'name']),
            'paymentMethods' => array_map(
                static fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ],
                PaymentMethod::cases(),
            ),
            'filteredTotal' => $filtered->minorUnits,
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        Expense::query()->create($request->payload());

        Flash::success('Expense recorded.');

        return back();
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->payload());

        Flash::success('Expense updated.');

        return back();
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        Flash::success('Expense deleted.');

        return back();
    }
}
