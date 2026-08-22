<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\PurchaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving an invoice along, from the buttons on it.
 *
 * Any of the three is reachable from any other: an order marked arrived by
 * mistake has to be able to go back, and a transition table that only allowed
 * one direction would make correcting that impossible.
 */
class PurchaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PurchaseStatus::class)],
        ];
    }

    public function status(): PurchaseStatus
    {
        return $this->enum('status', PurchaseStatus::class) ?? PurchaseStatus::Ordered;
    }
}
