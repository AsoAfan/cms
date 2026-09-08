<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
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
            // ISO 4217 is three letters, and it is the identity documents and
            // rates reference — so it has to be unique and cannot be edited
            // afterwards.
            'code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                Rule::unique('currencies', 'code'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:8'],

            // Display only — storage is always two decimal places. Three covers
            // the dinar-style currencies that quote in thousandths.
            'fraction_digits' => ['required', 'integer', 'between:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.size' => 'A currency code is three letters, like USD or EUR.',
            'code.alpha' => 'A currency code is three letters, like USD or EUR.',
            'code.unique' => 'That currency is already on the list.',
            'fraction_digits.between' => 'Between 0 and 3 decimal places.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim($this->string('code')->toString()))]);
        }
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function symbol(): string
    {
        return $this->string('symbol')->toString();
    }

    public function fractionDigits(): int
    {
        return $this->integer('fraction_digits');
    }
}
