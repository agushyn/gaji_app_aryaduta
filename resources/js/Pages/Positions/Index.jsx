import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Briefcase,
    Check,
    Edit3,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function PositionsIndex({ positions, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [editingPosition, setEditingPosition] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [positionToDelete, setPositionToDelete] = useState(null);

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
            route('master.positions.index'),
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('master.positions.index'), {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingPosition(null);
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

    const openEditModal = (position) => {
        setEditingPosition(position);
        clearErrors();
        setData({
            name: position.name,
            code: position.code || '',
            description: position.description || '',
            is_active: Boolean(position.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingPosition(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingPosition) {
            put(route('master.positions.update', editingPosition.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('master.positions.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleToggleStatus = (position) => {
        router.patch(
            route('master.positions.toggle-status', position.id),
            {},
            { preserveScroll: true }
        );
    };

    const confirmDelete = (position) => {
        setPositionToDelete(position);
        setDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (!positionToDelete) return;
        router.delete(route('master.positions.destroy', positionToDelete.id), {
            onSuccess: () => {
                setDeleteModalOpen(false);
                setPositionToDelete(null);
            },
            onError: () => {
                setDeleteModalOpen(false);
                setPositionToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout header="Master Jabatan">
            <Head title="Master Jabatan" />

            <div className="space-y-6">
                {/* Header & Quick Action */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Master Jabatan</h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Kelola posisi dan jabatan pekerjaan untuk karyawan dan tunjangan jabatan.
                        </p>
                    </div>
                    {can('positions.manage') && (
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-[#0B57D0] text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-[#0A48A8] transition-colors"
                        >
                            <Plus className="w-4 h-4" />
                            Tambah Jabatan
                        </button>
                    )}
                </div>

                {/* Metric Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Jabatan</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{counts?.total || 0}</p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                            <Briefcase className="w-6 h-6" />
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan Aktif</p>
                            <p className="text-2xl font-bold text-emerald-600 mt-1">{counts?.active || 0}</p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <Check className="w-6 h-6" />
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Nonaktif</p>
                            <p className="text-2xl font-bold text-gray-400 mt-1">{counts?.inactive || 0}</p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center">
                            <X className="w-6 h-6" />
                        </div>
                    </div>
                </div>

                {/* Search & Filter Bar */}
                <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3 items-center justify-between">
                        <div className="flex-1 w-full flex flex-col sm:flex-row gap-3">
                            <div className="relative flex-1">
                                <Search className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama jabatan atau kode..."
                                    className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0B57D0]/20 focus:border-[#0B57D0]"
                                />
                            </div>
                            <div className="w-full sm:w-44">
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0B57D0]/20 focus:border-[#0B57D0]"
                                >
                                    <option value="all">Semua Status</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <button
                                type="submit"
                                className="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-xl hover:bg-gray-900 transition-colors"
                            >
                                Filter
                            </button>
                            {(search || status !== 'all') && (
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="w-full sm:w-auto px-4 py-2 border border-gray-200 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Table Data Jabatan */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-gray-600">
                            <thead className="bg-gray-50/75 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">Jabatan</th>
                                    <th className="px-6 py-4">Deskripsi</th>
                                    <th className="px-6 py-4 text-center">Jumlah Karyawan</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {positions.data?.length > 0 ? (
                                    positions.data.map((pos) => (
                                        <tr key={pos.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                        <Briefcase className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-gray-900">{pos.name}</div>
                                                        {pos.code && (
                                                            <div className="text-xs text-gray-500 font-mono mt-0.5">Kode: {pos.code}</div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 max-w-sm">
                                                <div className="text-xs text-gray-600 line-clamp-2">
                                                    {pos.description || '-'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-[#0B57D0]">
                                                    <Users className="w-3 h-3" />
                                                    {pos.employees_count || 0}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {pos.is_active ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {can('positions.manage') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(pos)}
                                                                className="p-1.5 text-gray-500 hover:text-[#0B57D0] hover:bg-blue-50 rounded-lg transition-colors"
                                                                title="Edit Jabatan"
                                                            >
                                                                <Edit3 className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleToggleStatus(pos)}
                                                                className="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                                                                title={pos.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                            >
                                                                {pos.is_active ? (
                                                                    <ToggleRight className="w-4 h-4 text-emerald-600" />
                                                                ) : (
                                                                    <ToggleLeft className="w-4 h-4 text-gray-400" />
                                                                )}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => confirmDelete(pos)}
                                                                disabled={pos.employees_count > 0}
                                                                className={`p-1.5 rounded-lg transition-colors ${
                                                                    pos.employees_count > 0
                                                                        ? 'text-gray-300 cursor-not-allowed'
                                                                        : 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                                                                }`}
                                                                title={
                                                                    pos.employees_count > 0
                                                                        ? 'Tidak dapat dihapus karena masih digunakan oleh karyawan'
                                                                        : 'Hapus Jabatan'
                                                                }
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-gray-400">
                                            <Briefcase className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                                            <p className="font-medium text-gray-600">Belum ada data jabatan</p>
                                            <p className="text-xs text-gray-400 mt-1">
                                                Klik tombol Tambah Jabatan untuk menambahkan jabatan baru.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {positions.links && positions.data?.length > 0 && (
                        <div className="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <div>
                                Menampilkan {positions.from || 0} - {positions.to || 0} dari {positions.total || 0} jabatan
                            </div>
                            <div className="flex gap-1">
                                {positions.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        disabled={!link.url}
                                        className={`px-2.5 py-1 rounded-md ${
                                            link.active
                                                ? 'bg-[#0B57D0] text-white font-semibold'
                                                : link.url
                                                ? 'hover:bg-gray-100 text-gray-700'
                                                : 'text-gray-300 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Form Tambah/Edit Jabatan */}
            <Modal show={modalOpen} onClose={closeModal} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <div className="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                                <Briefcase className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">
                                    {editingPosition ? 'Edit Data Jabatan' : 'Tambah Jabatan Baru'}
                                </h3>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    Jabatan digunakan pada identitas karyawan dan slip gaji.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={closeModal}
                            className="text-gray-400 hover:text-gray-600 rounded-lg p-1"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="space-y-4 py-4">
                        <div>
                            <InputLabel htmlFor="name" value="Nama Jabatan *" />
                            <TextInput
                                id="name"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Contoh: Manager, Supervisor, Staff, Teknisi"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="code" value="Kode Jabatan (Opsional)" />
                            <TextInput
                                id="code"
                                type="text"
                                className="mt-1 block w-full uppercase"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="Contoh: MGR, SPV, STF"
                                maxLength={20}
                            />
                            <InputError message={errors.code} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="description" value="Deskripsi / Catatan" />
                            <textarea
                                id="description"
                                rows={3}
                                className="mt-1 block w-full border-gray-300 focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 rounded-md shadow-sm text-sm"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Deskripsi tugas atau ruang lingkup jabatan..."
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="pt-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300 text-[#0B57D0] shadow-sm focus:ring-[#0B57D0]/20"
                                />
                                <span className="text-sm font-medium text-gray-700">Jabatan Aktif</span>
                            </label>
                            <p className="text-[11px] text-gray-500 ml-6 mt-0.5">
                                Jabatan aktif dapat dipilih pada form data karyawan.
                            </p>
                        </div>
                    </div>

                    <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                        <SecondaryButton type="button" onClick={closeModal} disabled={processing}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={processing} className="bg-[#0B57D0] hover:bg-[#0A48A8]">
                            {processing ? 'Menyimpan...' : editingPosition ? 'Simpan Perubahan' : 'Tambah Jabatan'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Modal Konfirmasi Hapus */}
            <Modal show={deleteModalOpen} onClose={() => setDeleteModalOpen(false)} maxWidth="sm">
                <div className="p-6">
                    <div className="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-4">
                        <AlertCircle className="w-6 h-6" />
                    </div>
                    <h3 className="text-lg font-bold text-gray-900 text-center">Hapus Jabatan?</h3>
                    <p className="text-sm text-gray-500 text-center mt-2">
                        Apakah Anda yakin ingin menghapus jabatan <span className="font-semibold text-gray-800">{positionToDelete?.name}</span>?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div className="mt-6 flex items-center justify-center gap-3">
                        <SecondaryButton type="button" onClick={() => setDeleteModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors shadow-sm"
                        >
                            Hapus Jabatan
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
