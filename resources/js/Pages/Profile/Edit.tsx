import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/UI/Card';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-semibold text-text-primary">
                        Profile
                    </h1>
                    <p className="text-sm text-text-secondary mt-1">
                        Manage your account information.
                    </p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="flex flex-col gap-6 max-w-2xl">
                <Card>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </Card>

                <Card>
                    <UpdatePasswordForm />
                </Card>

                <Card>
                    <DeleteUserForm />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
