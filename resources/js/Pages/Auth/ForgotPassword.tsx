import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Reset your password
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                Enter your email and we&apos;ll send you a reset link.
            </p>

            {status && (
                <div className="mb-4 text-sm font-medium text-state-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-4">
                <FormField label="Email" error={errors.email} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="email"
                            name="email"
                            value={data.email}
                            autoFocus
                            error={!!errors.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </FormField>

                <div className="flex justify-end">
                    <Button type="submit" loading={processing}>
                        Email reset link
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
