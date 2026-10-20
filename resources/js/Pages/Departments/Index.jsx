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
    Building2,
    Check,
    Edit3,
    FolderTree,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function DepartmentsIndex({ departments, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [departmentToDelete, setDepartmentToDelete] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        name: '',
        code: '',
        description: '',
        is_active: true,
    });

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('master.departments.index'),
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('master.departments.index'), {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingDepartment(null);
        reset();
        clearErrors();
        setData({
            name: '',
            code: '',
            description: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (dept) => {
        setEditingDepartment(dept);
        clearErrors();
        setData({
            name: dept.name,
            code: dept.code,
            description: dept.description || '',
            is_active: Boolean(dept.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingDepartment(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingDepartment) {
            put(route('master.departments.update', editingDepartment.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('master.departments.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleToggleStatus = (dept) => {
        router.patch(
            route('master.departments.toggle-status', dept.id),
            {},
            { preserveScroll: true }
        );
    };

    const confirmDelete = (dept) => {
        setDepartmentToDelete(dept);
        setDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (!departmentToDelete) return;
        router.delete(route('master.departments.destroy', departmentToDelete.id), {
            onSuccess: () => {
                setDeleteModalOpen(false);
                setDepartmentToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Master Departemen"
            header="Master Departemen"
        >
            <Head title="Master Departemen" />

            <div className="space-y-6">
                {/* Header Title & Action Button */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0]">
                                <Building2 className="h-6 w-6" />
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-slate-900 tracking-tight">
                                    Master Departemen
                                </h2>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Kelola struktur departemen utama organisasi dan unit kerja.
                                </p>
                            </div>
                        </div>
                    </div>

                    {can('departments.manage') && (
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0A58CA] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#1A73E8] focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0] transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Departemen</span>
                        </button>
                    )}
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Departemen
                            </p>
                            <p className="text-2xl font-bold text-slate-900 mt-1">
                                {counts?.total || 0}
                            </p>
                        </div>
                        <div className="h-11 w-11 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                            <Building2 className="h-5 w-5" />
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-emerald-600 uppercase tracking-wider">
                                Departemen Aktif
                            </p>
                            <p className="text-2xl font-bold text-emerald-700 mt-1">
                                {counts?.active || 0}
                            </p>
                        </div>
                        <div className="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <Check className="h-5 w-5" />
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Nonaktif
                            </p>
                            <p className="text-2xl font-bold text-slate-700 mt-1">
                                {counts?.inactive || 0}
                            </p>
                        </div>
                        <div className="h-11 w-11 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                            <AlertCircle className="h-5 w-5" />
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3 items-center">
                        <div className="relative flex-1 w-full">
                            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama departemen, kode, atau deskripsi..."
                                className="w-full rounded-xl border border-slate-200 pl-10 pr-4 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] placeholder:text-slate-400"
                            />
                        </div>

                        <div className="w-full sm:w-44">
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="all">Semua Status</option>
                                <option value="active">Hanya Aktif</option>
                                <option value="inactive">Hanya Nonaktif</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <button
                                type="submit"
                                className="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0A58CA] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1A73E8] transition"
                            >
                                <Search className="h-3.5 w-3.5" />
                                <span>Cari</span>
                            </button>
                            {(filters?.search || (filters?.status && filters.status !== 'all')) && (
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Table Content */}
                <div className="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th className="px-5 py-3.5">Kode</th>
                                    <th className="px-5 py-3.5">Nama Departemen</th>
                                    <th className="px-5 py-3.5">Deskripsi</th>
                                    <th className="px-5 py-3.5 text-center">Divisi</th>
                                    <th className="px-5 py-3.5 text-center">Karyawan</th>
                                    <th className="px-5 py-3.5 text-center">Status</th>
                                    <th className="px-5 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {departments?.data?.length > 0 ? (
                                    departments.data.map((dept) => (
                                        <tr key={dept.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-[#0B57D0] font-bold text-xs border border-blue-100">
                                                    {dept.code}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-slate-900">
                                                {dept.name}
                                            </td>
                                            <td className="px-5 py-4 text-slate-500 max-w-xs truncate">
                                                {dept.description || '-'}
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <Link
                                                    href={route('master.divisions.index', { department_id: dept.id })}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-[#0B57D0] text-slate-700 font-semibold text-xs transition"
                                                    title="Lihat Divisi"
                                                >
                                                    <FolderTree className="h-3.5 w-3.5" />
                                                    <span>{dept.divisions_count || 0}</span>
                                                </Link>
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold text-xs">
                                                    <Users className="h-3.5 w-3.5 text-slate-500" />
                                                    <span>{dept.employees_count || 0}</span>
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                                        dept.is_active
                                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                            : 'bg-slate-100 text-slate-600 border border-slate-200'
                                                    }`}
                                                >
                                                    {dept.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center gap-1.5 justify-end">
                                                    {can('departments.manage') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleToggleStatus(dept)}
                                                                className={`p-1.5 rounded-lg transition ${
                                                                    dept.is_active
                                                                        ? 'text-emerald-600 hover:bg-emerald-50'
                                                                        : 'text-slate-400 hover:bg-slate-100'
                                                                }`}
                                                                title={dept.is_active ? 'Nonaktifkan Departemen' : 'Aktifkan Departemen'}
                                                            >
                                                                {dept.is_active ? (
                                                                    <ToggleRight className="h-5 w-5" />
                                                                ) : (
                                                                    <ToggleLeft className="h-5 w-5" />
                                                                )}
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(dept)}
                                                                className="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                                                                title="Edit Departemen"
                                                            >
                                                                <Edit3 className="h-4 w-4" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => confirmDelete(dept)}
                                                                disabled={dept.divisions_count > 0 || dept.employees_count > 0}
                                                                className={`p-1.5 rounded-lg transition ${
                                                                    dept.divisions_count > 0 || dept.employees_count > 0
                                                                        ? 'text-slate-300 cursor-not-allowed'
                                                                        : 'text-rose-600 hover:bg-rose-50'
                                                                }`}
                                                                title={
                                                                    dept.divisions_count > 0 || dept.employees_count > 0
                                                                        ? 'Departemen tidak dapat dihapus karena masih digunakan'
                                                                        : 'Hapus Departemen'
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-5 py-12 text-center text-slate-500">
                                            <Building2 className="h-8 w-8 mx-auto text-slate-300 mb-2" />
                                            <p className="font-semibold text-slate-700">Tidak ada data departemen</p>
                                            <p className="text-xs text-slate-400 mt-1">
                                                Silakan sesuaikan filter pencarian atau tambahkan departemen baru.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {departments?.links && departments.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 bg-slate-50/50">
                            <p className="text-xs text-slate-500">
                                Menampilkan <span className="font-semibold">{departments.from || 0}</span> sampai{' '}
                                <span className="font-semibold">{departments.to || 0}</span> dari{' '}
                                <span className="font-semibold">{departments.total || 0}</span> data
                            </p>
                            <div className="flex gap-1">
                                {departments.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1.5 text-xs rounded-lg transition font-medium ${
                                            link.active
                                                ? 'bg-[#0A58CA] text-white'
                                                : link.url
                                                ? 'text-slate-600 hover:bg-slate-200'
                                                : 'text-slate-300 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Create / Edit Department Modal */}
            <Modal show={modalOpen} onClose={closeModal} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0]">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <h3 className="text-base font-bold text-slate-900">
                                {editingDepartment ? 'Edit Departemen' : 'Tambah Departemen Baru'}
                            </h3>
                        </div>
                        <button
                            type="button"
                            onClick={closeModal}
                            className="rounded-lg p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="dept_code" value="Kode Departemen *" />
                            <TextInput
                                id="dept_code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="Contoh: HRD, FIN, MKT, GA, IT"
                                className="mt-1 block w-full uppercase font-mono"
                                required
                            />
                            <p className="text-[11px] text-slate-400 mt-1">
                                Kode unik pengenal departemen dalam sistem.
                            </p>
                            <InputError message={errors.code} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="dept_name" value="Nama Departemen *" />
                            <TextInput
                                id="dept_name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Contoh: Human Resources, Finance & Accounting"
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="dept_description" value="Deskripsi / Catatan" />
                            <textarea
                                id="dept_description"
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Deskripsi tugas dan tanggung jawab departemen..."
                                className="mt-1 block w-full rounded-xl border border-slate-300 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0]"
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="flex items-center gap-2 pt-1">
                            <input
                                id="dept_is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-slate-300 text-[#0A58CA] focus:ring-[#0B57D0] h-4 w-4"
                            />
                            <label htmlFor="dept_is_active" className="text-xs font-semibold text-slate-700 cursor-pointer">
                                Departemen Aktif
                            </label>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <SecondaryButton type="button" onClick={closeModal}>
                                Batal
                            </SecondaryButton>
                            <PrimaryButton type="submit" disabled={processing}>
                                {editingDepartment ? 'Simpan Perubahan' : 'Tambah Departemen'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            {/* Confirm Delete Modal */}
            <Modal show={deleteModalOpen} onClose={() => setDeleteModalOpen(false)} maxWidth="sm">
                <div className="p-6 text-center">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 mb-4">
                        <Trash2 className="h-6 w-6" />
                    </div>
                    <h3 className="text-base font-bold text-slate-900">
                        Hapus Departemen?
                    </h3>
                    <p className="text-xs text-slate-500 mt-2">
                        Apakah Anda yakin ingin menghapus departemen <span className="font-semibold text-slate-800">{departmentToDelete?.name}</span>? Tindakan ini tidak dapat dibatalkan.
                    </p>

                    <div className="mt-6 flex justify-center gap-3">
                        <SecondaryButton type="button" onClick={() => setDeleteModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 transition"
                        >
                            Hapus Departemen
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
