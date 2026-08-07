import type { Attribute, AttributeValue, ItemFormRow } from '@/types/catalog';

/**
 * Every combination of one value per chosen option — the item matrix.
 *
 * With no options chosen this yields a single empty combination, which is
 * exactly right: a plain product is one item carrying no options.
 */
export function combinations(
    valueGroups: AttributeValue[][],
): AttributeValue[][] {
    return valueGroups
        .filter((group) => group.length > 0)
        .reduce<AttributeValue[][]>(
            (accumulated, group) =>
                accumulated.flatMap((row) =>
                    group.map((value) => [...row, value]),
                ),
            [[]],
        );
}

/** "117cm / 137cm", in the option order the user chose. */
export function combinationLabel(values: AttributeValue[]): string {
    return values.map((value) => value.value).join(' / ');
}

/** Stable identity for a combination, order-independent. */
export function combinationKey(valueIds: number[]): string {
    return [...valueIds].sort((a, b) => a - b).join('-');
}

/**
 * Suggest a code for a combination: a product prefix plus a short token per
 * option, e.g. `BLAEYE-117-137`.
 */
export function suggestCode(
    productName: string,
    values: AttributeValue[],
): string {
    const prefix = productName
        .replace(/[^a-zA-Z0-9 ]/g, '')
        .trim()
        .split(/\s+/)
        .map((word) => word.slice(0, 3))
        .join('')
        .toUpperCase()
        .slice(0, 8);

    const suffix = values.map((value) =>
        value.value
            .replace(/[^a-zA-Z0-9]/g, '')
            .slice(0, 4)
            .toUpperCase(),
    );

    return [prefix || 'ITEM', ...suffix].join('-');
}

/**
 * Regenerate the item rows for the chosen options, keeping whatever the user
 * already typed for combinations that survive the change.
 *
 * Rows already saved on the server keep their id so the server updates rather
 * than replaces them — that matters because a saved item's options are fixed.
 */
export function rebuildItemRows(
    productName: string,
    chosenAttributes: Attribute[],
    selectedValues: Record<number, number[]>,
    existing: ItemFormRow[],
): ItemFormRow[] {
    const groups = chosenAttributes.map((attribute) =>
        attribute.values.filter((value) =>
            (selectedValues[attribute.id] ?? []).includes(value.id),
        ),
    );

    const byCombination = new Map(
        existing.map((row) => [combinationKey(row.attribute_value_ids), row]),
    );

    return combinations(groups).map((values) => {
        const ids = values.map((value) => value.id);
        const kept = byCombination.get(combinationKey(ids));

        if (kept) {
            return { ...kept, label: combinationLabel(values) || kept.label };
        }

        return {
            code: suggestCode(productName, values),
            default_cost_price: null,
            default_selling_price: null,
            is_active: true,
            attribute_value_ids: ids,
            label: combinationLabel(values),
        };
    });
}
