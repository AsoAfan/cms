import { Form, Head } from '@inertiajs/react';

import { store } from '@/actions/App/Http/Controllers/Auth/NewPasswordController';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

type ResetPasswordProps = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    return (
        <>
            <Head title="Reset password" />

            <Form {...store.form()}>
                {({ errors, processing }) => (
                    <FieldGroup>
                        <input type="hidden" name="token" value={token} />

                        <FormField label="Email" error={errors.email}>
                            {(field) => (
                                <Input
                                    {...field}
                                    type="email"
                                    name="email"
                                    autoComplete="username"
                                    defaultValue={email}
                                    readOnly
                                />
                            )}
                        </FormField>

                        <FormField label="New password" error={errors.password}>
                            {(field) => (
                                <Input
                                    {...field}
                                    type="password"
                                    name="password"
                                    autoComplete="new-password"
                                    required
                                    autoFocus
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Confirm password"
                            error={errors.password_confirmation}
                        >
                            {(field) => (
                                <Input
                                    {...field}
                                    type="password"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    required
                                />
                            )}
                        </FormField>

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner data-icon="inline-start" />}
                            Reset password
                        </Button>
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = [
    AuthLayout,
    {
        title: 'Reset password',
        description: 'Choose a new password for your account.',
    },
];
