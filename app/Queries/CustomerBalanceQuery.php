<?php

namespace App\Queries;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What customers owe, derived from three recorded facts and nothing else:
 *
 *     what they were invoiced   the lines on their delivered sales
 *     less what they handed over `sales.amount_paid`
 *     less what they paid since  the allocations against those sales
 *
 * There is no balance column anywhere, and there must never be one — it is the
 * single figure in this application most certain to drift, because every sale,
 * every part payment and every deletion moves it. This class is the only place
 * the arithmetic happens, so a customer's list row, their statement, the payment
 * dialog and the report tile can never disagree about what they owe.
 *
 * **Only delivered (`proceed`) sales count.** An `ordered` sale is a quote and an
 * `on_the_way` one has left the shelf without reaching the customer; neither is
 * a debt, however much has been paid against it. Money on an undelivered order
 * is a deposit, and it sits on the sale as `amount_paid` until delivery makes it
 * a payment towards something.
 *
 * Aggregates are summed in SQL over three separate grouped queries rather than
 * one join across lines and allocations together — joining both at once
 * multiplies the rows and silently doubles the figures.
 */
final class CustomerBalanceQuery
{
    /**
     * What a sale line was charged at, matching `CashFlowQuery` exactly so a
     * customer's debt and the reported income can never be measured differently.
     */
    private const string SALE_LINE_NET = 'sale_lines.quantity * sale_lines.unit_price - sale_lines.discount';

    /**
     * What each customer owes, keyed by customer id.
     *
     * Customers who owe nothing are absent rather than zero, so a caller reads
     * `$balances[$id] ?? Money::zero()`. Pass the ids on the current page to keep
     * a paginated list from aggregating the whole book.
     *
     * @param  list<int>|null  $customerIds
     * @return array<int, Money>
     */
    public function get(?array $customerIds = null): array
    {
        $invoiced = $this->invoicedPerCustomer($customerIds);
        $handedOver = $this->handedOverPerCustomer($customerIds);
        $allocated = $this->allocatedPerCustomer($customerIds);

        $balances = [];

        foreach (array_keys($invoiced + $handedOver + $allocated) as $customerId) {
            $owed = ($invoiced[$customerId] ?? 0)
                - ($handedOver[$customerId] ?? 0)
                - ($allocated[$customerId] ?? 0);

            if ($owed !== 0) {
                $balances[$customerId] = Money::fromMinorUnits($owed);
            }
        }

        return $balances;
    }

    /**
     * One customer's balance.
     */
    public function forCustomer(Customer $customer): Money
    {
        return $this->get([$customer->id])[$customer->id] ?? Money::zero();
    }

    /**
     * Everything owed to the business, across every customer — the receivable.
     *
     * Read as at now rather than as at a date: a debt is what is still unpaid
     * today, and a historical one would need the payment dates rewound, which is
     * a different question from the one any screen asks.
     */
    public function total(): Money
    {
        return Money::sum(...array_values($this->get()));
    }

    /**
     * A customer's delivered invoices that still owe something, oldest first —
     * the list a payment is allocated across.
     *
     * @return list<array{id: int, number: string, sold_on: string, total: Money, paid: Money, outstanding: Money}>
     */
    public function openSales(Customer $customer): array
    {
        return array_values(array_filter(
            $this->invoices($customer, deliveredOnly: true),
            static fn (array $sale): bool => $sale['outstanding']->isPositive(),
        ));
    }

    /**
     * A customer's account: every sale, every payment, and what is left.
     *
     * Undelivered sales are listed — a shop wants to see what is on order — but
     * carry an outstanding of zero, because nothing is owed until the goods are
     * the customer's.
     *
     * @return array{
     *     balance: Money,
     *     invoiced: Money,
     *     paid: Money,
     *     sales: list<array{id: int, number: string, sold_on: string, status: string, status_label: string, delivered: bool, total: Money, paid: Money, outstanding: Money}>,
     *     payments: list<array{id: int, received_on: string, amount: Money, payment_method: string, bank: string|null, currency: string, exchange_rate: string, notes: string|null, allocations: list<array{sale_id: int, number: string, amount: Money}>}>,
     * }
     */
    public function statement(Customer $customer): array
    {
        $sales = $this->invoices($customer);
        $delivered = array_filter($sales, static fn (array $sale): bool => $sale['delivered']);

        return [
            'balance' => $this->forCustomer($customer),
            'invoiced' => Money::sum(...array_column($delivered, 'total')),
            'paid' => Money::sum(...array_column($delivered, 'paid')),
            'sales' => $sales,
            'payments' => $this->payments($customer),
        ];
    }

    /**
     * Every invoice of a customer's with what is left on each, newest last so a
     * statement reads down the page in the order things happened.
     *
     * @return list<array{id: int, number: string, sold_on: string, status: string, status_label: string, delivered: bool, total: Money, paid: Money, outstanding: Money}>
     */
    private function invoices(Customer $customer, bool $deliveredOnly = false): array
    {
        return array_values(Sale::query()
            ->where('customer_id', $customer->id)
            ->when($deliveredOnly, fn ($query) => $query->where('status', SaleStatus::Proceed->value))
            ->withSum('lines as net_minor_units', DB::raw(self::SALE_LINE_NET))
            ->withSum('paymentAllocations as allocated_minor_units', 'amount')
            ->orderBy('sold_on')
            ->orderBy('id')
            ->get()
            ->map(function (Sale $sale): array {
                // `withSum` applies no cast, and returns null when nothing
                // matched — an invoice nobody has paid against.
                $total = Money::fromMinorUnits((int) $sale->net_minor_units);
                $paid = $sale->amount_paid->plus(
                    Money::fromMinorUnits((int) $sale->allocated_minor_units)
                );

                return [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'sold_on' => $sale->sold_on->toDateString(),
                    'status' => $sale->status->value,
                    'status_label' => $sale->status->label(),
                    'delivered' => $sale->isDelivered(),
                    'total' => $total,
                    'paid' => $paid,
                    'outstanding' => $sale->isDelivered() ? $total->minus($paid) : Money::zero(),
                ];
            })
            ->all());
    }

    /**
     * A customer's payments, newest first, each with the invoices it settled.
     *
     * @return list<array{id: int, received_on: string, amount: Money, payment_method: string, bank: string|null, currency: string, exchange_rate: string, notes: string|null, allocations: list<array{sale_id: int, number: string, amount: Money}>}>
     */
    private function payments(Customer $customer): array
    {
        /** @var Collection<int, CustomerPayment> $payments */
        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->with(['allocations.sale:id,number', 'bank:id,name'])
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();

        return array_values($payments->map(fn (CustomerPayment $payment): array => [
            'id' => $payment->id,
            'received_on' => $payment->received_on->toDateString(),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method->label(),
            'bank' => $payment->bank?->name,
            'currency' => $payment->currency,
            'exchange_rate' => $payment->exchangeRate(),
            'notes' => $payment->notes,
            'allocations' => array_values(
                $payment->allocations
                    ->map(fn (CustomerPaymentAllocation $allocation): array => [
                        'sale_id' => $allocation->sale_id,
                        'number' => $allocation->sale->number,
                        'amount' => $allocation->amount,
                    ])
                    ->all()
            ),
        ])->all());
    }

    /**
     * What each customer has been invoiced on delivered sales.
     *
     * @param  list<int>|null  $customerIds
     * @return array<int, int>
     */
    private function invoicedPerCustomer(?array $customerIds): array
    {
        $query = SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id');

        return $this->perCustomer($query, $customerIds, self::SALE_LINE_NET);
    }

    /**
     * What each customer handed over at the time of sale.
     *
     * @param  list<int>|null  $customerIds
     * @return array<int, int>
     */
    private function handedOverPerCustomer(?array $customerIds): array
    {
        return $this->perCustomer(Sale::query()->toBase(), $customerIds, 'sales.amount_paid');
    }

    /**
     * What each customer has paid since, applied to those sales.
     *
     * @param  list<int>|null  $customerIds
     * @return array<int, int>
     */
    private function allocatedPerCustomer(?array $customerIds): array
    {
        $query = CustomerPaymentAllocation::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'customer_payment_allocations.sale_id');

        return $this->perCustomer($query, $customerIds, 'customer_payment_allocations.amount');
    }

    /**
     * Sum one expression per customer over delivered sales.
     *
     * `$expression` is literal by contract — every caller passes a class constant
     * or a column name written out here, never anything from a request.
     *
     * @param  list<int>|null  $customerIds
     * @param  literal-string  $expression
     * @return array<int, int>
     */
    private function perCustomer(Builder $query, ?array $customerIds, string $expression): array
    {
        return $query
            ->where('sales.status', SaleStatus::Proceed->value)
            ->when(
                $customerIds !== null,
                fn (Builder $query): Builder => $query->whereIn('sales.customer_id', $customerIds ?? []),
            )
            ->groupBy('sales.customer_id')
            ->selectRaw("sales.customer_id as customer_id, COALESCE(SUM({$expression}), 0) as total")
            ->pluck('total', 'customer_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }
}
