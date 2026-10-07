import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout header="Pengaturan Profil Pengguna">
            <Head title="Profil Akun" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                        Profil Akun Saya
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Perbarui informasi nama akun dan kata sandi Anda.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="rounded-2xl border border-rose-200 bg-white p-6 shadow-xs">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
