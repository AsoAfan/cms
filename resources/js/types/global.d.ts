import type { Appearance, Currency, FlashToast } from '@/types/app';
import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            currency: Currency;
            sidebarOpen: boolean;
            appearance: Appearance;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
        };
    }
}
