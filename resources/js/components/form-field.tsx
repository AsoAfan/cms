import { useId } from 'react';
import type { ReactNode } from 'react';

import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

/**
 * Props to spread onto the control so its label, description and error are
 * wired up for screen readers without the caller having to think about it.
 */
export type FieldControlProps = {
    id: string;
    'aria-invalid': boolean | undefined;
    'aria-describedby': string | undefined;
};

export type FormFieldProps = {
    label: string;
    /** The message from Inertia's `errors` bag for this field, if any. */
    error?: string;
    description?: string;
    orientation?: 'vertical' | 'horizontal' | 'responsive';
    children: ReactNode | ((field: FieldControlProps) => ReactNode);
};

/**
 * A labelled form control with its description and validation message.
 *
 * Pass a function as the child to receive the wiring for the control:
 *
 *     <FormField label="Email" error={errors.email}>
 *         {(field) => (
 *             <Input
 *                 {...field}
 *                 type="email"
 *                 value={data.email}
 *                 onChange={(event) => setData('email', event.target.value)}
 *             />
 *         )}
 *     </FormField>
 */
export function FormField({
    label,
    error,
    description,
    orientation = 'vertical',
    children,
}: FormFieldProps) {
    const id = useId();
    const descriptionId = `${id}-description`;
    const errorId = `${id}-error`;

    const field: FieldControlProps = {
        id,
        'aria-invalid': error ? true : undefined,
        'aria-describedby':
            [description ? descriptionId : null, error ? errorId : null]
                .filter(Boolean)
                .join(' ') || undefined,
    };

    return (
        <Field
            orientation={orientation}
            data-invalid={error ? true : undefined}
        >
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {typeof children === 'function' ? children(field) : children}
            {description && (
                <FieldDescription id={descriptionId}>
                    {description}
                </FieldDescription>
            )}
            {error && <FieldError id={errorId}>{error}</FieldError>}
        </Field>
    );
}
