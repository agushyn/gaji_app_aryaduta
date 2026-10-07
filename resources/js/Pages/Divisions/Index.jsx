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

export default function DivisionsIndex({ divisions, offices, departments, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [officeId, setOfficeId] = useState(filters?.office_id || 'all');
    const [departmentId, setDepartmentId] = useState(filters?.department_id || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [editingDivision, setEditingDivision] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [divisionToDelete, setDivisionToDelete] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        office_id: '',
        department_id: '',
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
            route('master.divisions.index'),
            { search, office_id: officeId, department_id: departmentId, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setOfficeId('all');
        setDepartmentId('all');
        setStatus('all');
        router.get(route('master.divisions.index'), {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingDivision(null);
        reset();
        clearErrors();
        setData({
            office_id: offices?.length > 0 ? String(offices[0].id) : '',
            department_id: departments?.length > 0 ? String(departments[0].id) : '',
            name: '',
            code: '',
            description: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (div) => {
        setEditingDivision(div);
        clearErrors();
        setData({
            office_id: div.office_id ? String(div.office_id) : (offices?.length > 0 ? String(offices[0].id) : ''),
            department_id: div.department_id ? String(div.department_id) : '',
            name: div.name,
            code: div.code || '',
            description: div.description || '',
            is_active: Boolean(div.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingDivision(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingDivision) {
            put(route('master.divisions.update', editingDivision.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('master.divisions.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleToggleStatus = (div) => {
        router.patch(
            route('master.divisions.toggle-status', div.id),
            {},
            { preserveScroll: true }
        );
    };

    const confirmDelete = (div) => {
        setDivisionToDelete(div);
        setDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (!divisionToDelete) return;
        router.delete(route('master.divisions.destroy', divisionToDelete.id), {
            onSuccess: () => {
                setDeleteModalOpen(false);
                setDivisionToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Master Divisi"
            header="Master Divisi"
        >
            <Head title="Master Divisi" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {/* Header Title & Action Button */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0]">
                                <FolderTree className="h-6 w-6" />
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-slate-900 tracking-tight">
                                    Master Divisi
                                </h2>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Kelola unit divisi kerja yang bernaung di bawah departemen.
                                </p>
                            </div>
                        </div>
                    </div>

                    {can('divisions.manage') && (
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0A58CA] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#1A73E8] focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0] transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Divisi</span>
                        </button>
                    )}
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Divisi
                            </p>
                            <p className="text-2xl font-bold text-slate-900 mt-1">
                                {counts?.total || 0}
                            </p>
                        </div>
                        <div className="h-11 w-11 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                            <FolderTree className="h-5 w-5" />
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-emerald-600 uppercase tracking-wider">
                                Divisi Aktif
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
                                placeholder="Cari nama divisi, kode, atau deskripsi..."
                                className="w-full rounded-xl border border-slate-200 pl-10 pr-4 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] placeholder:text-slate-400"
                            />
                        </div>

                        {/* Filter Kantor */}
                        <div className="w-full sm:w-56">
                            <select
                                value={officeId}
                                onChange={(e) => setOfficeId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="all">Semua Kantor</option>
                                {offices?.map((off) => (
                                    <option key={off.id} value={off.id}>
                                        {off.name} ({off.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Filter Departemen */}
                        <div className="w-full sm:w-56">
                            <select
                                value={departmentId}
                                onChange={(e) => setDepartmentId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="all">Semua Departemen</option>
                                {departments?.map((dept) => (
                                    <option key={dept.id} value={dept.id}>
                                        {dept.name} ({dept.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Filter Status */}
                        <div className="w-full sm:w-40">
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
                            {(filters?.search || (filters?.office_id && filters.office_id !== 'all') || (filters?.department_id && filters.department_id !== 'all') || (filters?.status && filters.status !== 'all')) && (
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
                                    <th className="px-5 py-3.5">Nama Divisi</th>
                                    <th className="px-5 py-3.5">Kantor</th>
                                    <th className="px-5 py-3.5">Departemen Parent</th>
                                    <th className="px-5 py-3.5">Deskripsi</th>
                                    <th className="px-5 py-3.5 text-center">Karyawan</th>
                                    <th className="px-5 py-3.5 text-center">Status</th>
                                    <th className="px-5 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {divisions?.data?.length > 0 ? (
                                    divisions.data.map((div) => (
                                        <tr key={div.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-100 text-slate-800 font-bold text-xs border border-slate-200">
                                                    {div.code || '-'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-slate-900">
                                                {div.name}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {div.office ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-semibold text-xs border border-indigo-100">
                                                        <Building2 className="h-3 w-3" />
                                                        <span>{div.office.name}</span>
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 italic">-</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {div.department ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-[#0B57D0] font-semibold text-xs border border-blue-100">
                                                        <FolderTree className="h-3 w-3" />
                                                        <span>{div.department.name}</span>
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 italic">Tanpa Departemen</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-slate-500 max-w-xs truncate">
                                                {div.description || '-'}
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold text-xs">
                                                    <Users className="h-3.5 w-3.5 text-slate-500" />
                                                    <span>{div.employees_count || 0}</span>
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                                        div.is_active
                                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                            : 'bg-slate-100 text-slate-600 border border-slate-200'
                                                    }`}
                                                >
                                                    {div.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center gap-1.5 justify-end">
                                                    {can('divisions.manage') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleToggleStatus(div)}
                                                                className={`p-1.5 rounded-lg transition ${
                                                                    div.is_active
                                                                        ? 'text-emerald-600 hover:bg-emerald-50'
                                                                        : 'text-slate-400 hover:bg-slate-100'
                                                                }`}
                                                                title={div.is_active ? 'Nonaktifkan Divisi' : 'Aktifkan Divisi'}
                                                            >
                                                                {div.is_active ? (
                                                                    <ToggleRight className="h-5 w-5" />
                                                                ) : (
                                                                    <ToggleLeft className="h-5 w-5" />
                                                                )}
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(div)}
                                                                className="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                                                                title="Edit Divisi"
                                                            >
                                                                <Edit3 className="h-4 w-4" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => confirmDelete(div)}
                                                                disabled={div.employees_count > 0}
                                                                className={`p-1.5 rounded-lg transition ${
                                                                    div.employees_count > 0
                                                                        ? 'text-slate-300 cursor-not-allowed'
                                                                        : 'text-rose-600 hover:bg-rose-50'
                                                                }`}
                                                                title={
                                                                    div.employees_count > 0
                                                                        ? 'Divisi tidak dapat dihapus karena masih digunakan oleh data karyawan'
                                                                        : 'Hapus Divisi'
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
                                        <td colSpan={8} className="px-5 py-12 text-center text-slate-500">
                                            <FolderTree className="h-8 w-8 mx-auto text-slate-300 mb-2" />
                                            <p className="font-semibold text-slate-700">Tidak ada data divisi</p>
                                            <p className="text-xs text-slate-400 mt-1">
                                                Silakan sesuaikan filter pencarian atau tambahkan divisi baru.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {divisions?.links && divisions.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 bg-slate-50/50">
                            <p className="text-xs text-slate-500">
                                Menampilkan <span className="font-semibold">{divisions.from || 0}</span> sampai{' '}
                                <span className="font-semibold">{divisions.to || 0}</span> dari{' '}
                                <span className="font-semibold">{divisions.total || 0}</span> data
                            </p>
                            <div className="flex gap-1">
                                {divisions.links.map((link, idx) => (
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

            {/* Create / Edit Division Modal */}
            <Modal show={modalOpen} onClose={closeModal} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0]">
                                <FolderTree className="h-5 w-5" />
                            </div>
                            <h3 className="text-base font-bold text-slate-900">
                                {editingDivision ? 'Edit Divisi' : 'Tambah Divisi Baru'}
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
                            <InputLabel htmlFor="div_office_id" value="Kantor *" />
                            <select
                                id="div_office_id"
                                value={data.office_id}
                                onChange={(e) => setData('office_id', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-300 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0]"
                                required
                            >
                                <option value="" disabled>Pilih Kantor</option>
                                {offices?.map((off) => (
                                    <option key={off.id} value={off.id}>
                                        {off.name} ({off.code})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.office_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="div_dept_id" value="Departemen (Opsional)" />
                            <select
                                id="div_dept_id"
                                value={data.department_id}
                                onChange={(e) => setData('department_id', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-300 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0]"
                            >
                                <option value="">Tanpa Departemen</option>
                                {departments?.map((dept) => (
                                    <option key={dept.id} value={dept.id}>
                                        {dept.name} ({dept.code})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.department_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="div_name" value="Nama Divisi *" />
                            <TextInput
                                id="div_name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Contoh: Digital Marketing, Sales Marketing, People Operations"
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="div_code" value="Kode Divisi" />
                            <TextInput
                                id="div_code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="Contoh: MKT-DIG, HRD-OPS"
                                className="mt-1 block w-full uppercase font-mono"
                            />
                            <p className="text-[11px] text-slate-400 mt-1">
                                Kode unik pengenal divisi (opsional).
                            </p>
                            <InputError message={errors.code} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="div_description" value="Deskripsi / Catatan" />
                            <textarea
                                id="div_description"
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Deskripsi peran dan lingkup kerja divisi..."
                                className="mt-1 block w-full rounded-xl border border-slate-300 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0]"
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="flex items-center gap-2 pt-1">
                            <input
                                id="div_is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-slate-300 text-[#0A58CA] focus:ring-[#0B57D0] h-4 w-4"
                            />
                            <label htmlFor="div_is_active" className="text-xs font-semibold text-slate-700 cursor-pointer">
                                Divisi Aktif
                            </label>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <SecondaryButton type="button" onClick={closeModal}>
                                Batal
                            </SecondaryButton>
                            <PrimaryButton type="submit" disabled={processing}>
                                {editingDivision ? 'Simpan Perubahan' : 'Tambah Divisi'}
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
                        Hapus Divisi?
                    </h3>
                    <p className="text-xs text-slate-500 mt-2">
                        Apakah Anda yakin ingin menghapus divisi <span className="font-semibold text-slate-800">{divisionToDelete?.name}</span>? Tindakan ini tidak dapat dibatalkan.
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
                            Hapus Divisi
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
