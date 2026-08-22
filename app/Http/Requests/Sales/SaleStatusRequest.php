<?php

namespace App\Http\Requests\Sales;

use App\Enums\SaleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving a sale along, from the buttons on it.
 *
 * Any of the three is reachable from any other, so a sale marked sent out by
 * mistake can be put back on the shelf.
 */
class SaleStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(SaleStatus::class)],
        ];
    }

    public function status(): SaleStatus
    {
        return $this->enum('status', SaleStatus::class) ?? SaleStatus::Ordered;
    }
}
