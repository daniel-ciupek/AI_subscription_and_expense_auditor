import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import { Modal } from '@/Components/UI/Modal';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const closeModal = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-semibold text-state-danger">
                    Delete account
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                    Once deleted, all data is permanently removed. Download any
                    information you wish to retain first.
                </p>
            </header>

            <div className="mt-6">
                <Button variant="danger" onClick={() => setConfirming(true)}>
                    Delete account
                </Button>
            </div>

            <Modal
                open={confirming}
                onClose={closeModal}
                title="Delete your account?"
                description="This action is irreversible. Enter your password to confirm."
                maxWidth="md"
            >
                <form onSubmit={deleteUser} className="flex flex-col gap-4">
                    <FormField label="Password" error={errors.password}>
                        {(id) => (
                            <Input
                                id={id}
                                ref={passwordInput}
                                type="password"
                                name="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoFocus
                                placeholder="Enter your password"
                                error={!!errors.password}
                            />
                        )}
                    </FormField>

                    <div className="flex justify-end gap-3 mt-2">
                        <Button variant="ghost" type="button" onClick={closeModal}>
                            Cancel
                        </Button>
                        <Button variant="danger" type="submit" loading={processing}>
                            Delete account
                        </Button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
