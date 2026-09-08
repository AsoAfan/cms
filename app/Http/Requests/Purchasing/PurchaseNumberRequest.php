<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\FilesUnderAReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Refiling an invoice under another reference, from the heading on its own page.
 *
 * The reference is the whole request here, so unlike the drawer's field it
 * cannot be blank: an empty heading is a slip, not an instruction.
 */
class PurchaseNumberRequest extends FormRequest
{
    use FilesUnderAReference;

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
            'number' => $this->referenceRules('purchases', $this->route('purchase'), required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->referenceMessages('invoice');
    }

    protected function prepareForValidation(): void
    {
        $this->trimReference();
    }
}
