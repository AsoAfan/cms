<?php

namespace App\Actions\Customers;

use App\Exceptions\CustomerPaymentException;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Queries\CustomerBalanceQuery;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Takes money off a customer's loan.
 *
 * A payment is applied to named invoices rather than dropped on an account
 * balance, so what is left on any one sale stays a fact. Everything that makes
 * that trustworthy is checked here, in one transaction, because a half-applied
 * payment is worse than a refused one:
 *
 * - The allocations sum to EXACTLY the payment. Money against nothing in
 *   particular would make the account balance and the invoice balances disagree.
 * - No invoice takes more than it still owes, so no invoice is overpaid and no
 *   balance goes negative by the back door.
 * - Every invoice is this customer's, and delivered. Money against an order not
 *   yet handed over is a deposit, and a deposit belongs on the sale itself as
 *   `amount_paid`.
 *
 * Nothing here touches stock. The goods left when the sale was delivered; this
 * is the money catching up, and an arch test keeps the two apart.
 *
 * There is no matching update action. A payment is either what came in or it is
 * not, so a wrong one is deleted — which unwinds its allocations with it — and
 * recorded again.
 */
final class RecordCustomerPaymentAction
{
    public function __construct(private readonly CustomerBalanceQuery $balances) {}

    /**
     * Amounts arrive as base-currency decimal strings: the Form Request is the
     * one place currency is converted.
     *
     * @param  array{amount: string, received_on: string, payment_method: string, bank_id?: int|null, currency: string, exchange_rate: int, notes: string|null}  $payment
     * @param  array<int, string>  $allocations  sale id => amount applied to it
     *
     * @throws CustomerPaymentException
     */
    public function handle(Customer $customer, array $payment, array $allocations): CustomerPayment
    {
        $amount = Money::fromDecimal($payment['amount']);

        if (! $amount->isPositive()) {
            throw CustomerPaymentException::notPositive();
        }

        return DB::transaction(function () use ($customer, $payment, $allocations, $amount): CustomerPayment {
            $applied = $this->applicableAllocations($customer, $allocations);

            if ($applied === []) {
                throw CustomerPaymentException::nothingAllocated();
            }

            $allocated = Money::sum(...array_values($applied));

            if (! $allocated->equals($amount)) {
                throw CustomerPaymentException::allocationsDoNotAddUp($amount, $allocated);
            }

            $record = CustomerPayment::query()->create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'received_on' => $payment['received_on'],
                'payment_method' => $payment['payment_method'],
                'bank_id' => $payment['bank_id'] ?? null,
                'currency' => $payment['currency'],
                'exchange_rate' => $payment['exchange_rate'],
                'notes' => $payment['notes'],
            ]);

            foreach ($applied as $saleId => $share) {
                $record->allocations()->create([
                    'sale_id' => $saleId,
                    'amount' => $share,
                ]);
            }

            return $record->refresh();
        });
    }

    /**
     * The allocations worth writing, checked against what each invoice still
     * owes.
     *
     * Zero shares are dropped rather than refused: the payment dialog offers
     * every open invoice, and leaving most of them blank is how someone says
     * which one they are paying.
     *
     * @param  array<int, string>  $allocations
     * @return array<int, Money>
     *
     * @throws CustomerPaymentException
     */
    private function applicableAllocations(Customer $customer, array $allocations): array
    {
        $outstanding = [];

        foreach ($this->balances->openSales($customer) as $open) {
            $outstanding[$open['id']] = $open['outstanding'];
        }

        $applied = [];

        foreach ($allocations as $saleId => $share) {
            $share = Money::fromDecimal($share);

            if (! $share->isPositive()) {
                continue;
            }

            $sale = Sale::query()->findOrFail($saleId);

            if ($sale->customer_id !== $customer->id) {
                throw CustomerPaymentException::saleBelongsToSomebodyElse($sale);
            }

            if (! $sale->isDelivered()) {
                throw CustomerPaymentException::saleIsNotDelivered($sale);
            }

            $left = $outstanding[$sale->id] ?? Money::zero();

            if ($share->isGreaterThan($left)) {
                throw CustomerPaymentException::overpaysSale($sale, $left, $share);
            }

            $applied[$sale->id] = $share;
        }

        return $applied;
    }
}
