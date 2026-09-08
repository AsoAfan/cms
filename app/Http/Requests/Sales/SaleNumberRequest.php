<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\FilesUnderAReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Refiling a sale under another reference, from the heading on its own page.
 *
 * The reference is the whole request here, so unlike the drawer's field it
 * cannot be blank: an empty heading is a slip, not an instruction.
 */
class SaleNumberRequest extends FormRequest
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
            'number' => $this->referenceRules('sales', $this->route('sale'), required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->referenceMessages('sale');
    }

    protected function prepareForValidation(): void
    {
        $this->trimReference();
    }
}
