import { Button } from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Verify your email
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                We sent a verification link to your email. Click it to activate
                your account. Didn&apos;t receive it? Resend below.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-state-success">
                    A new verification link has been sent.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex items-center justify-between gap-3">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm text-text-secondary hover:text-state-danger transition-colors rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-state-danger focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated"
                    >
                        Log out
                    </Link>
                    <Button type="submit" loading={processing}>
                        Resend email
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
