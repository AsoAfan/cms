<?php

namespace App\Actions\Catalog;

use App\Models\Attribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates an attribute together with the options it offers.
 *
 * An option that a variant already carries cannot be removed: "Red" is part of
 * what an existing SKU means, so dropping it would leave that SKU describing
 * nothing. Renaming the attribute itself is fine.
 */
final class SaveAttributeAction
{
    /**
     * @param  array{name: string, values: array<int, string>}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?Attribute $attribute = null): Attribute
    {
        $values = array_values(array_unique($data['values']));

        if ($attribute !== null) {
            $this->guardAgainstRemovingUsedValues($attribute, $values);
        }

        return DB::transaction(function () use ($data, $values, $attribute): Attribute {
            $attribute ??= new Attribute;

            $attribute->fill(['name' => $data['name']])->save();

            $existing = $attribute->values()->pluck('id', 'value');

            $attribute->values()->whereNotIn('value', $values)->delete();

            $attribute->values()->createMany(
                collect($values)
                    ->reject(fn (string $value): bool => $existing->has($value))
                    ->map(fn (string $value): array => ['value' => $value])
                    ->all()
            );

            return $attribute->load('values');
        });
    }

    /**
     * @param  list<string>  $values
     *
     * @throws ValidationException
     */
    private function guardAgainstRemovingUsedValues(Attribute $attribute, array $values): void
    {
        $inUse = $attribute->values()
            ->whereNotIn('value', $values)
            ->whereHas('variants')
            ->pluck('value')
            ->all();

        if ($inUse === []) {
            return;
        }

        throw ValidationException::withMessages([
            'values' => 'Cannot remove '.implode(', ', $inUse).' — still used by product variants.',
        ]);
    }
}
