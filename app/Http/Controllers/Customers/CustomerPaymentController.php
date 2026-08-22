<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\RecordCustomerPaymentAction;
use App\Exceptions\CustomerPaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Money coming in against a customer's loan.
 *
 * Recorded from the customer's own statement screen, which is where someone can
 * see what is owed on what, so there is no screen of its own.
 */
class CustomerPaymentController extends Controller
{
    public function store(
        CustomerPaymentRequest $request,
        Customer $customer,
        RecordCustomerPaymentAction $record,
    ): RedirectResponse {
        try {
            $record->handle($customer, $request->payment(), $request->allocations());
        } catch (CustomerPaymentException $exception) {
            // The problem is with what was applied to which invoice, so it
            // belongs on the form where it can be corrected rather than in a
            // toast that closes the dialog and loses the entry.
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        // No figure in the message: the whole app can be read in another
        // currency, and a toast is the one place that would not follow.
        Flash::success("Payment recorded against {$customer->name}.");

        return back();
    }

    /**
     * A payment is never edited — it is either what came in or it is not. Deleting
     * one unwinds every invoice it settled, which the cascade on
     * `customer_payment_allocations` does.
     */
    public function destroy(Customer $customer, CustomerPayment $payment): RedirectResponse
    {
        if ($payment->customer_id !== $customer->id) {
            Flash::error('That payment belongs to another customer.');

            return back();
        }

        $payment->delete();

        Flash::success('Payment deleted. What it settled is owed again.');

        return back();
    }
}
