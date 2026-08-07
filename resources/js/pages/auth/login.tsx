import { Form, Head, Link } from '@inertiajs/react';

import { store } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { request } from '@/routes/password';

type LoginProps = {
    canResetPassword: boolean;
    status?: string;
};

export default function Login({ canResetPassword, status }: LoginProps) {
    return (
        <>
            <Head title="Log in" />

            {status && (
                <Alert className="mb-4">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <Form {...store.form()} resetOnError={['password']}>
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

                        <FormField label="Password" error={errors.password}>
                            {(field) => (
                                <Input
                                    {...field}
                                    type="password"
                                    name="password"
                                    autoComplete="current-password"
                                    required
                                />
                            )}
                        </FormField>

                        <Field orientation="horizontal">
                            <Checkbox id="remember" name="remember" value="1" />
                            <FieldLabel
                                htmlFor="remember"
                                className="font-normal"
                            >
                                Remember me
                            </FieldLabel>
                        </Field>

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner data-icon="inline-start" />}
                            Log in
                        </Button>

                        {canResetPassword && (
                            <p className="text-center text-sm text-muted-foreground">
                                <Link
                                    href={request()}
                                    className="underline underline-offset-4"
                                >
                                    Forgot your password?
                                </Link>
                            </p>
                        )}
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

Login.layout = [
    AuthLayout,
    { title: 'Log in', description: 'Enter your credentials to continue.' },
];
