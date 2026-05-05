import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-semibold text-text-primary">
                    Profile information
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                    Update your account&apos;s name and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                <FormField label="Name" error={errors.name} required>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            autoComplete="name"
                            error={!!errors.name}
                        />
                    )}
                </FormField>

                <FormField label="Email" error={errors.email} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                            error={!!errors.email}
                        />
                    )}
                </FormField>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="text-sm text-text-secondary">
                        Your email address is unverified.{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="text-accent-neon hover:text-accent-neon/80 transition-colors rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface"
                        >
                            Re-send verification email
                        </Link>
                        {status === 'verification-link-sent' && (
                            <p className="mt-2 text-state-success font-medium">
                                A new verification link has been sent.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4 mt-2">
                    <Button type="submit" loading={processing}>
                        Save
                    </Button>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-state-success">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
