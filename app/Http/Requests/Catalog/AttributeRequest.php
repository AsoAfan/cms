<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attributes', 'name')->ignore($this->route('attribute')),
            ],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    /**
     * The validated payload in the shape SaveAttributeAction expects.
     *
     * Named `payload` rather than `data` to avoid colliding with Laravel's
     * own InteractsWithData::data().
     *
     * @return array{name: string, values: list<string>}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'values' => array_values(array_map(
                static fn (mixed $value): string => (string) $value,
                $this->array('values'),
            )),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.distinct' => 'Each option must be listed only once.',
            'values.min' => 'An attribute needs at least one option.',
        ];
    }
}
