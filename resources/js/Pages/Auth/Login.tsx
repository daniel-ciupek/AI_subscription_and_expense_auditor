import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Welcome back
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                Sign in to access your dashboard.
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
                            autoComplete="username"
                            autoFocus
                            error={!!errors.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </FormField>

                <FormField label="Password" error={errors.password} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            error={!!errors.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </FormField>

                <label className="flex items-center gap-2 text-sm text-text-secondary cursor-pointer">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="h-4 w-4 rounded border-white/20 bg-bg-surface text-accent-primary focus:ring-accent-neon focus:ring-offset-0"
                    />
                    Remember me
                </label>

                <div className="flex items-center justify-between gap-3 mt-2">
                    {canResetPassword ? (
                        <Link
                            href={route('password.request')}
                            className="text-sm text-text-secondary hover:text-accent-neon transition-colors"
                        >
                            Forgot password?
                        </Link>
                    ) : (
                        <span />
                    )}
                    <Button type="submit" loading={processing}>
                        Log in
                    </Button>
                </div>
            </form>

            <p className="text-sm text-text-secondary mt-6 text-center">
                Don&apos;t have an account?{' '}
                <Link
                    href={route('register')}
                    className="text-accent-neon hover:text-accent-neon/80 transition-colors"
                >
                    Sign up
                </Link>
            </p>
        </GuestLayout>
    );
}
