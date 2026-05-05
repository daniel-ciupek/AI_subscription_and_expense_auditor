import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-semibold text-text-primary">
                    Update password
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                    Use a long, random password to stay secure.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-6 flex flex-col gap-4">
                <FormField label="Current password" error={errors.current_password}>
                    {(id) => (
                        <Input
                            id={id}
                            ref={currentPasswordInput}
                            type="password"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            autoComplete="current-password"
                            error={!!errors.current_password}
                        />
                    )}
                </FormField>

                <FormField label="New password" error={errors.password}>
                    {(id) => (
                        <Input
                            id={id}
                            ref={passwordInput}
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            error={!!errors.password}
                        />
                    )}
                </FormField>

                <FormField label="Confirm password" error={errors.password_confirmation}>
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            autoComplete="new-password"
                            error={!!errors.password_confirmation}
                        />
                    )}
                </FormField>

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
