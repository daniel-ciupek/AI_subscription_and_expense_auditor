import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Create your account
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                Track subscriptions, audit expenses with AI.
            </p>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <FormField label="Name" error={errors.name} required>
                    {(id) => (
                        <Input
                            id={id}
                            name="name"
                            value={data.name}
                            autoComplete="name"
                            autoFocus
                            error={!!errors.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    )}
                </FormField>

                <FormField label="Email" error={errors.email} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="username"
                            error={!!errors.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    )}
                </FormField>

                <FormField
                    label="Password"
                    error={errors.password}
                    helperText="At least 8 characters"
                    required
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            name="password"
                            value={data.password}
                            autoComplete="new-password"
                            error={!!errors.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
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
                            required
                        />
                    )}
                </FormField>

                <div className="flex items-center justify-between gap-3 mt-2">
                    <Link
                        href={route('login')}
                        className="text-sm text-text-secondary hover:text-accent-neon transition-colors rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated"
                    >
                        Already registered?
                    </Link>
                    <Button type="submit" loading={processing}>
                        Create account
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
