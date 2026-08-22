<?php

namespace App\Http\Controllers\Customers;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Queries\CustomerBalanceQuery;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerBalanceQuery $balances) {}

    /**
     * Who you sell to, and what each of them owes.
     *
     * Balances are derived, so they cannot be sorted or filtered by a column.
     * The book is aggregated in three grouped queries and the ids that owe
     * something are handed to the filter — cheap for a shop's customer list, and
     * the honest alternative to storing a balance that would drift. If this ever
     * gets slow it is the index audit's problem (P8.T4), not a reason to cache a
     * figure that moves on every sale.
     */
    public function index(): Response
    {
        $balances = $this->balances->get();

        $owing = array_keys(array_filter(
            $balances,
            static fn (Money $balance): bool => $balance->isPositive(),
        ));

        $table = $this->table(Customer::query())
            ->searchable(['name', 'phone', 'email', 'address'])
            ->sortable(['name', 'created_at'], default: 'name')
            ->filterable([
                'status' => fn (Builder $query, string $value): Builder => $query
                    ->where('is_active', $value === 'active'),
                'balance' => fn (Builder $query, string $value): Builder => $value === 'owing'
                    ? $query->whereIn('id', $owing)
                    : $query->whereNotIn('id', $owing),
            ]);

        $paginator = $table->paginate();

        $paginator->through(fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'is_active' => $customer->is_active,
            'balance' => ($balances[$customer->id] ?? Money::zero())->minorUnits,
        ]);

        return Inertia::render('customers/index', [
            'rows' => $paginator,
            'table' => $table->state(),
            // Every loan on the books, whatever the list is filtered to — the
            // figure someone opens this screen to see.
            'owedTotal' => Money::sum(...array_values($balances))->minorUnits,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->payload());

        Flash::success('Customer created.');

        return to_route('customers.show', $customer);
    }

    /**
     * A customer's account: what they have bought, what they have paid, and what
     * is left on each invoice.
     */
    public function show(Customer $customer): Response
    {
        return Inertia::render('customers/show', [
            'customer' => $customer->only(['id', 'name', 'phone', 'email', 'address', 'notes', 'is_active']),
            'statement' => $this->balances->statement($customer),
            // The invoices a payment can be applied to, and how it can be paid.
            'openSales' => $this->balances->openSales($customer),
            'paymentMethods' => PaymentMethod::options(),
            // Which account the repayment came into, on a card or transfer.
            'banks' => Bank::options(),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/edit', [
            'customer' => $customer->only([
                'id', 'name', 'phone', 'email', 'address', 'notes', 'is_active',
            ]),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->payload());

        Flash::success('Customer updated.');

        return back();
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        // Every sale names a buyer, and the foreign key enforces that a buyer
        // with history behind them stays. Say so rather than letting it 500.
        if ($customer->sales()->exists()) {
            Flash::error("{$customer->name} has sales on record and cannot be deleted.");

            return back();
        }

        if ($customer->payments()->exists()) {
            Flash::error("{$customer->name} has payments on record and cannot be deleted.");

            return back();
        }

        $customer->delete();

        Flash::success('Customer deleted.');

        return to_route('customers.index');
    }
}
