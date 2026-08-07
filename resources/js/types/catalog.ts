export type AttributeValue = {
    id: number;
    attribute_id: number;
    value: string;
};

/** An axis a product varies along — Width, Drop, Colour. */
export type Attribute = {
    id: number;
    name: string;
    values: AttributeValue[];
    values_count?: number;
};

export type ProductListRow = {
    id: number;
    name: string;
    is_active: boolean;
    variants_count: number;
};

export type ProductFormData = {
    id?: number;
    name: string;
    description: string | null;
    is_active: boolean;
    attribute_ids: number[];
    variants: ItemFormRow[];
};

/**
 * One stock item on the product form. Called a "variant" in the schema for
 * Eloquent's benefit; everywhere a person reads it, it is an item with a code.
 */
export type ItemFormRow = {
    id?: number;
    code: string;
    /** Decimal strings so the input round-trips exactly. */
    default_cost_price: string | null;
    default_selling_price: string | null;
    is_active: boolean;
    attribute_value_ids: number[];
    label?: string;
};
