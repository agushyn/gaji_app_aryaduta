import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    Plus,
    Search,
    Shield,
    UserCheck,
    UserPlus,
    UserX,
} from 'lucide-react';
import { useState } from 'react';

export default function UsersIndex({ users, roles, filters }) {
    const { auth } = usePage().props;
    const currentUser = auth.user;

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedRole, setSelectedRole] = useState(filters?.role || '');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || '');

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role_id: roles?.[0]?.id || '',
        is_active: true,
    });

    const can = (permission) => {
        if (!currentUser) return false;
        if (currentUser.permissions?.includes('*')) return true;
        return currentUser.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('users.index'),
            { search, role: selectedRole, status: selectedStatus },
            { preserveState: true, replace: true }
        );
    };

    const handleResetFilters = () => {
        setSearch('');
        setSelectedRole('');
        setSelectedStatus('');
        router.get(route('users.index'), {}, { preserveState: true, replace: true });
    };

    const handleCreateUser = (e) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
            },
        });
    };

    const handleToggleStatus = (user) => {
        if (user.id === currentUser.id) {
            alert('Anda tidak dapat menonaktifkan akun Anda sendiri.');
            return;
        }

        const actionText = user.is_active ? 'menonaktifkan' : 'mengaktifkan';
        if (confirm(`Apakah Anda yakin ingin ${actionText} akun ${user.name}?`)) {
            router.patch(route('users.toggle-status', user.id));
        }
    };

    return (
        <AuthenticatedLayout header="Manajemen Pengguna Internal">
            <Head title="Pengguna Internal" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                            Daftar Pengguna & Staf Internal
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola akun petugas berwenang (Super Admin, Personalia, Approver, Auditor).
                        </p>
                    </div>

                    {can('users.create') && (
                        <button
                            type="button"
                            onClick={() => setIsCreateModalOpen(true)}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                        >
                            <UserPlus className="h-4 w-4" />
                            <span>Tambah Pengguna</span>
                        </button>
                    )}
                </div>

                {/* Important Notice: No employee portal */}
                <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 flex items-start gap-3">
                    <AlertCircle className="h-4 w-4 text-blue-600 mt-0.5 shrink-0" />
                    <div>
                        <span className="font-semibold block">Prinsip Keamanan Akun:</span>
                        Aplikasi ini tidak menyediakan portal login untuk karyawan penerima slip. Seluruh akun di sini adalah akun petugas internal berizin. Karyawan hanya menerima slip gaji via Email dan WhatsApp pada fase berikutnya.
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[220px]">
                            <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama atau email..."
                                className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            />
                        </div>

                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                        >
                            <option value="">Semua Peran</option>
                            {roles?.map((r) => (
                                <option key={r.id} value={r.name}>
                                    {r.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition"
                        >
                            Filter
                        </button>

                        {(search || selectedRole || selectedStatus) && (
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="text-xs font-medium text-slate-500 hover:text-slate-700 underline"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Table Card */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Nama & Email</th>
                                    <th className="px-6 py-3.5">Peran / Role</th>
                                    <th className="px-6 py-3.5">Status Akun</th>
                                    <th className="px-6 py-3.5">Terakhir Masuk</th>
                                    <th className="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                {users?.data?.length > 0 ? (
                                    users.data.map((u) => {
                                        const role = u.roles?.[0];
                                        return (
                                            <tr key={u.id} className="hover:bg-slate-50/60 transition">
                                                <td className="px-6 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {u.name}
                                                        {u.id === currentUser.id && (
                                                            <span className="ms-2 rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200">
                                                                Akun Anda
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-slate-500 font-mono text-[11px] mt-0.5">
                                                        {u.email}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-800 border border-slate-200">
                                                        <Shield className="h-3 w-3 text-slate-500" />
                                                        {role?.label || 'Tanpa Peran'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    {u.is_active ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                                            Aktif
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-rose-500" />
                                                            Nonaktif
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-slate-500">
                                                    {u.last_login_at
                                                        ? new Date(u.last_login_at).toLocaleString('id-ID', {
                                                              day: '2-digit',
                                                              month: 'short',
                                                              year: 'numeric',
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                          })
                                                        : 'Belum pernah'}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    {can('users.toggle-status') && (
                                                        <button
                                                            type="button"
                                                            disabled={u.id === currentUser.id}
                                                            onClick={() => handleToggleStatus(u)}
                                                            className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ${
                                                                u.id === currentUser.id
                                                                    ? 'opacity-40 cursor-not-allowed text-slate-400 bg-slate-100'
                                                                    : u.is_active
                                                                    ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200'
                                                                    : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200'
                                                            }`}
                                                        >
                                                            {u.is_active ? (
                                                                <>
                                                                    <UserX className="h-3.5 w-3.5" />
                                                                    <span>Nonaktifkan</span>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <UserCheck className="h-3.5 w-3.5" />
                                                                    <span>Aktifkan</span>
                                                                </>
                                                            )}
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-8 text-center text-slate-400">
                                            Tidak ada pengguna ditemukan sesuai filter pencarian.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users?.links && users.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Menampilkan {users.from || 0} - {users.to || 0} dari {users.total} pengguna
                            </span>
                            <div className="flex gap-1">
                                {users.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`rounded-lg px-3 py-1 text-xs font-medium transition ${
                                            link.active
                                                ? 'bg-[#0B57D0] text-white'
                                                : link.url
                                                ? 'text-slate-600 hover:bg-slate-100'
                                                : 'text-slate-300 pointer-events-none'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Tambah Pengguna Baru */}
            <Modal show={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} maxWidth="md">
                <form onSubmit={handleCreateUser} className="p-6">
                    <h3 className="text-lg font-bold text-slate-900">
                        Tambah Pengguna Internal
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Buat akun petugas sistem baru. Akun ini akan memiliki akses sesuai peran yang dipilih.
                    </p>

                    <div className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="create_name" value="Nama Lengkap" />
                            <TextInput
                                id="create_name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="mt-1 block w-full text-xs"
                                placeholder="Contoh: Budi Santoso"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="create_email" value="Alamat Email Kantor" />
                            <TextInput
                                id="create_email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1 block w-full text-xs"
                                placeholder="budi@example.com"
                                required
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="create_password" value="Kata Sandi Awal" />
                            <TextInput
                                id="create_password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-1 block w-full text-xs"
                                placeholder="Minimal 8 karakter"
                                required
                            />
                            <InputError message={errors.password} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="create_role" value="Peran Sistem (Role)" />
                            <select
                                id="create_role"
                                value={data.role_id}
                                onChange={(e) => setData('role_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs"
                                required
                            >
                                <option value="">Pilih Peran</option>
                                {roles?.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.role_id} className="mt-1" />
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <input
                                id="create_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label htmlFor="create_active" className="text-xs text-slate-700 cursor-pointer select-none">
                                Akun langsung berstatus aktif
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <SecondaryButton type="button" onClick={() => setIsCreateModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan Pengguna'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
