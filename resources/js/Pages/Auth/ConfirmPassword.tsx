import { Button } from '@/Components/UI/Button';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm Password" />

            <h1 className="text-2xl font-semibold text-text-primary mb-1">
                Confirm your password
            </h1>
            <p className="text-sm text-text-secondary mb-6">
                This is a secure area. Please re-enter your password to continue.
            </p>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <FormField label="Password" error={errors.password} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="password"
                            name="password"
                            value={data.password}
                            autoFocus
                            error={!!errors.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </FormField>

                <div className="flex justify-end mt-2">
                    <Button type="submit" loading={processing}>
                        Confirm
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
