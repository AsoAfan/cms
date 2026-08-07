export type SupplierListRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    is_active: boolean;
};

export type SupplierFormData = {
    id?: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
};
