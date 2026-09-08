import { Form, Head, Link } from '@inertiajs/react';

import { store } from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <Alert className="mb-4">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <Form {...store.form()}>
                {({ errors, processing }) => (
                    <FieldGroup>
                        <FormField label="Email" error={errors.email}>
                            {(field) => (
                                <Input
                                    {...field}
                                    type="email"
                                    name="email"
                                    autoComplete="username"
                                    required
                                    autoFocus
                                />
                            )}
                        </FormField>

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner data-icon="inline-start" />}
                            Email reset link
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            <Link
                                href={login()}
                                className="underline underline-offset-4"
                            >
                                Back to log in
                            </Link>
                        </p>
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = [
    AuthLayout,
    {
        title: 'Forgot password',
        description: "We'll email you a reset link.",
    },
];
