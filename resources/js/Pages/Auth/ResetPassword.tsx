import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Set a new password
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                Choose a strong password you haven&apos;t used before.
            </p>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <FormField label="Email" error={errors.email}>
                    {(id) => (
                        <Input
                            id={id}
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="username"
                            error={!!errors.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </FormField>

                <FormField label="New password" error={errors.password} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            name="password"
                            value={data.password}
                            autoComplete="new-password"
                            autoFocus
                            error={!!errors.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </FormField>

                <FormField
                    label="Confirm password"
                    error={errors.password_confirmation}
                    required
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            error={!!errors.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                    )}
                </FormField>

                <div className="flex justify-end mt-2">
                    <Button type="submit" loading={processing}>
                        Reset password
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
