<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

/**
 * Requires a bank on the payment methods that move through one.
 *
 * The rule is derived from `PaymentMethod::usesBank()` rather than restated per
 * request, so every screen that takes a payment — a sale, a quick sale, an
 * expense, a customer repayment — asks for a bank on exactly the same methods.
 *
 * Both directions are enforced, and both matter:
 *
 * - **Card and transfer require one.** A card payment nobody can trace to an
 *   account is the row that makes the bank totals disagree with the card totals,
 *   and it can never be identified afterwards.
 * - **Cash forbids one.** Cash over the counter did not touch an account, and a
 *   bank against one is a stale value left behind by switching the method on the
 *   form, not a detail somebody meant to record.
 *
 * The column itself is nullable — see the `add_bank_to_payments` migration. Rows
 * recorded before banks existed carry no bank, and no rule can go back and ask
 * them.
 */
trait NamesPayingBank
{
    /**
     * @return list<mixed>
     */
    protected function bankRules(): array
    {
        $exists = ['integer', Rule::exists('banks', 'id')];

        return match (PaymentMethod::tryFrom($this->string($this->paymentMethodKey())->toString())?->usesBank()) {
            true => ['required', ...$exists],
            false => ['prohibited'],
            // The method itself is missing or nonsense; its own rule reports
            // that, and a second error about the bank would only be noise.
            null => ['nullable', ...$exists],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function bankMessages(): array
    {
        return [
            'bank_id.required' => 'Say which bank the money went through.',
            'bank_id.prohibited' => 'Cash does not go through a bank.',
            'bank_id.exists' => 'That bank is not on the list.',
        ];
    }

    /**
     * The bank the money moved through, or null when it did not move through one.
     */
    public function bankId(): ?int
    {
        return $this->filled('bank_id') ? $this->integer('bank_id') : null;
    }

    /**
     * The request field naming the payment method. Constant everywhere so far;
     * a request that names it differently overrides this.
     */
    protected function paymentMethodKey(): string
    {
        return 'payment_method';
    }
}
