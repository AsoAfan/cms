<?php

namespace App\Http\Requests\Catalog;

use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],

            // Every product needs at least one item: the item is what
            // purchases, sales and the stock ledger actually reference.
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'variants.*.code' => ['required', 'string', 'max:64', 'distinct:ignore_case'],
            'variants.*.default_cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'variants.*.default_selling_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.attribute_value_ids' => ['array'],
            'variants.*.attribute_value_ids.*' => ['integer', Rule::exists('attribute_values', 'id')],
        ];
    }

    /**
     * Cross-row checks the per-field rules cannot express.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $variants = (array) $this->input('variants', []);

                if ($variants === []) {
                    return;
                }

                $this->validateCodesAreFree($validator, $variants);

                $attributeOfValue = $this->attributeOfValue($variants);
                $seenCombinations = [];
                $attributeSets = [];

                foreach ($variants as $index => $variant) {
                    $valueIds = collect((array) ($variant['attribute_value_ids'] ?? []))
                        ->map(fn ($id): int => (int) $id);

                    $attributeIds = $valueIds
                        ->map(fn (int $id): ?int => $attributeOfValue[$id] ?? null)
                        ->filter()
                        ->values();

                    if ($attributeIds->count() !== $attributeIds->unique()->count()) {
                        $validator->errors()->add(
                            "variants.{$index}.attribute_value_ids",
                            'An item cannot take two values of the same option.'
                        );
                    }

                    $attributeSets[$index] = $attributeIds->unique()->sort()->values()->all();

                    $combination = $valueIds->sort()->implode('-');

                    if (array_key_exists($combination, $seenCombinations)) {
                        $validator->errors()->add(
                            "variants.{$index}.attribute_value_ids",
                            'Another item already uses this combination of options.'
                        );
                    }

                    $seenCombinations[$combination] = $index;
                }

                // Items may cover a subset of the possible combinations — not
                // every width has to come in every drop — but they must all
                // vary along the same options, or the product has no coherent
                // shape.
                $expected = reset($attributeSets);

                foreach ($attributeSets as $index => $set) {
                    if ($set !== $expected) {
                        $validator->errors()->add(
                            "variants.{$index}.attribute_value_ids",
                            'Every item must use the same options as the others.'
                        );
                    }
                }
            },
        ];
    }

    /**
     * The validated payload in the shape SaveProductAction expects.
     *
     * Named `payload` rather than `data` to avoid colliding with Laravel's
     * own InteractsWithData::data().
     *
     * @return array{
     *     name: string,
     *     description: string|null,
     *     is_active: bool,
     *     variants: list<array{
     *         id: int|null,
     *         code: string,
     *         default_cost_price: string|null,
     *         default_selling_price: string|null,
     *         is_active: bool,
     *         attribute_value_ids: list<int>,
     *     }>,
     * }
     */
    public function payload(): array
    {
        $variants = [];

        foreach ($this->array('variants') as $variant) {
            $variant = (array) $variant;

            $variants[] = [
                'id' => isset($variant['id']) ? (int) $variant['id'] : null,
                'code' => (string) ($variant['code'] ?? ''),
                'default_cost_price' => $this->decimal($variant['default_cost_price'] ?? null),
                'default_selling_price' => $this->decimal($variant['default_selling_price'] ?? null),
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'attribute_value_ids' => array_values(array_map(
                    static fn (mixed $id): int => (int) $id,
                    (array) ($variant['attribute_value_ids'] ?? []),
                )),
            ];
        }

        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->has('description') && $this->input('description') !== null
                ? $this->string('description')->toString()
                : null,
            'is_active' => $this->boolean('is_active', true),
            'variants' => $variants,
        ];
    }

    private function decimal(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.required' => 'A product needs at least one item.',
            'variants.min' => 'A product needs at least one item.',
            'variants.*.code.required' => 'Every item needs a code.',
            'variants.*.code.distinct' => 'Each code must be unique.',
        ];
    }

    /**
     * Codes are unique across the whole catalogue.
     *
     * Checked here rather than with a per-index `Rule::unique` so the indexed
     * rules cannot overwrite the wildcard ones on `variants.*.code` — doing so
     * silently drops `required` and `distinct` from those rows.
     *
     * @param  array<int|string, mixed>  $variants
     */
    private function validateCodesAreFree(Validator $validator, array $variants): void
    {
        $codes = collect($variants)
            ->map(fn (mixed $variant): mixed => is_array($variant) ? ($variant['code'] ?? null) : null)
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== '');

        if ($codes->isEmpty()) {
            return;
        }

        $owners = ProductVariant::query()
            ->whereIn('code', $codes->values())
            ->pluck('id', 'code');

        foreach ($variants as $index => $variant) {
            if (! is_array($variant) || ! isset($variant['code']) || ! is_string($variant['code'])) {
                continue;
            }

            $ownerId = $owners->get($variant['code']);

            if ($ownerId !== null && (int) $ownerId !== (int) ($variant['id'] ?? 0)) {
                $validator->errors()->add(
                    "variants.{$index}.code",
                    'That code is already used by another product.'
                );
            }
        }
    }

    /**
     * Map every submitted option value id to the option it belongs to.
     *
     * @param  array<int|string, mixed>  $variants
     * @return array<int, int>
     */
    private function attributeOfValue(array $variants): array
    {
        $valueIds = collect($variants)
            ->flatMap(fn (mixed $variant): array => is_array($variant)
                ? (array) ($variant['attribute_value_ids'] ?? [])
                : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($valueIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, int> $map */
        $map = AttributeValue::query()
            ->whereIn('id', $valueIds)
            ->pluck('attribute_id', 'id');

        return $map->all();
    }
}
