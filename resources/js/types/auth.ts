export type User = {
    id: number;
    name: string;
    email: string;
};

export type Auth = {
    user: User | null;
};
