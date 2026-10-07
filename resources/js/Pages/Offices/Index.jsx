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
    Building2,
    Check,
    Edit3,
    FolderTree,
    Mail,
    MapPin,
    Phone,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function OfficesIndex({ offices, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [editingOffice, setEditingOffice] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [officeToDelete, setOfficeToDelete] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        name: '',
        code: '',
        address: '',
        phone: '',
        email: '',
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
            route('master.offices.index'),
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('master.offices.index'), {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingOffice(null);
        reset();
        clearErrors();
        setData({
            name: '',
            code: '',
            address: '',
            phone: '',
            email: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (office) => {
        setEditingOffice(office);
        clearErrors();
        setData({
            name: office.name,
            code: office.code || '',
            address: office.address || '',
            phone: office.phone || '',
            email: office.email || '',
            is_active: Boolean(office.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingOffice(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingOffice) {
            put(route('master.offices.update', editingOffice.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('master.offices.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleToggleStatus = (office) => {
        router.patch(
            route('master.offices.toggle-status', office.id),
            {},
            { preserveScroll: true }
        );
    };

    const confirmDelete = (office) => {
        setOfficeToDelete(office);
        setDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (!officeToDelete) return;
        router.delete(route('master.offices.destroy', officeToDelete.id), {
            onSuccess: () => {
                setDeleteModalOpen(false);
                setOfficeToDelete(null);
            },
            onError: () => {
                setDeleteModalOpen(false);
                setOfficeToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout header="Master Kantor">
            <Head title="Master Kantor" />

            <div className="space-y-6">
                {/* Header & Quick Action */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Master Kantor</h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Kelola data kantor, alamat perusahaan, dan identitas slip gaji.
                        </p>
                    </div>
                    {can('offices.manage') && (
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-[#0B57D0] text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-[#0A48A8] transition-colors"
                        >
                            <Plus className="w-4 h-4" />
                            Tambah Kantor
                        </button>
                    )}
                </div>

                {/* Metric Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Kantor</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{counts?.total || 0}</p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                            <Building2 className="w-6 h-6" />
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Kantor Aktif</p>
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
                                    placeholder="Cari nama kantor, kode, atau alamat..."
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

                {/* Table Data Kantor */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-gray-600">
                            <thead className="bg-gray-50/75 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">Kantor</th>
                                    <th className="px-6 py-4">Alamat</th>
                                    <th className="px-6 py-4">Kontak</th>
                                    <th className="px-6 py-4 text-center">Divisi</th>
                                    <th className="px-6 py-4 text-center">Karyawan</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {offices.data?.length > 0 ? (
                                    offices.data.map((office) => (
                                        <tr key={office.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-9 h-9 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center font-bold text-xs shrink-0">
                                                        {office.code || 'BP'}
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-gray-900">{office.name}</div>
                                                        <div className="text-xs text-gray-500 font-mono mt-0.5">Kode: {office.code}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 max-w-xs">
                                                <div className="text-xs text-gray-700 line-clamp-2" title={office.address}>
                                                    {office.address || '-'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="space-y-1 text-xs">
                                                    {office.phone && (
                                                        <div className="flex items-center gap-1.5 text-gray-600">
                                                            <Phone className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                            <span>{office.phone}</span>
                                                        </div>
                                                    )}
                                                    {office.email && (
                                                        <div className="flex items-center gap-1.5 text-gray-600">
                                                            <Mail className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                            <span>{office.email}</span>
                                                        </div>
                                                    )}
                                                    {!office.phone && !office.email && (
                                                        <span className="text-gray-400">-</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                                    <FolderTree className="w-3 h-3" />
                                                    {office.divisions_count || 0}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-[#0B57D0]">
                                                    <Users className="w-3 h-3" />
                                                    {office.employees_count || 0}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {office.is_active ? (
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
                                                    {can('offices.manage') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(office)}
                                                                className="p-1.5 text-gray-500 hover:text-[#0B57D0] hover:bg-blue-50 rounded-lg transition-colors"
                                                                title="Edit Kantor"
                                                            >
                                                                <Edit3 className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleToggleStatus(office)}
                                                                className="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                                                                title={office.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                            >
                                                                {office.is_active ? (
                                                                    <ToggleRight className="w-4 h-4 text-emerald-600" />
                                                                ) : (
                                                                    <ToggleLeft className="w-4 h-4 text-gray-400" />
                                                                )}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => confirmDelete(office)}
                                                                disabled={office.divisions_count > 0 || office.employees_count > 0}
                                                                className={`p-1.5 rounded-lg transition-colors ${
                                                                    office.divisions_count > 0 || office.employees_count > 0
                                                                        ? 'text-gray-300 cursor-not-allowed'
                                                                        : 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                                                                }`}
                                                                title={
                                                                    office.divisions_count > 0 || office.employees_count > 0
                                                                        ? 'Tidak dapat dihapus karena masih digunakan divisi/karyawan'
                                                                        : 'Hapus Kantor'
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
                                        <td colSpan={7} className="px-6 py-12 text-center text-gray-400">
                                            <Building2 className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                                            <p className="font-medium text-gray-600">Belum ada data kantor</p>
                                            <p className="text-xs text-gray-400 mt-1">
                                                Klik tombol Tambah Kantor untuk menambahkan kantor baru.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {offices.links && offices.data?.length > 0 && (
                        <div className="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <div>
                                Menampilkan {offices.from || 0} - {offices.to || 0} dari {offices.total || 0} kantor
                            </div>
                            <div className="flex gap-1">
                                {offices.links.map((link, idx) => (
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

            {/* Modal Form Tambah/Edit Kantor */}
            <Modal show={modalOpen} onClose={closeModal} maxWidth="lg">
                <form onSubmit={handleSubmit} className="p-6">
                    <div className="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-blue-50 text-[#0B57D0] flex items-center justify-center">
                                <Building2 className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">
                                    {editingOffice ? 'Edit Data Kantor' : 'Tambah Kantor Baru'}
                                </h3>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    Informasi ini dicetak sebagai identitas kop pada Slip Gaji karyawan.
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
                            <InputLabel htmlFor="name" value="Nama Kantor / Perusahaan *" />
                            <TextInput
                                id="name"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Contoh: CV. BINA PUSTAKA"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="code" value="Kode Kantor *" />
                            <TextInput
                                id="code"
                                type="text"
                                className="mt-1 block w-full uppercase"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="Contoh: BP"
                                maxLength={20}
                                required
                            />
                            <p className="text-[11px] text-gray-500 mt-1">
                                Kode kantor unik untuk penomoran dokumen dan pembeda unit.
                            </p>
                            <InputError message={errors.code} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="address" value="Alamat Kantor *" />
                            <textarea
                                id="address"
                                rows={3}
                                className="mt-1 block w-full border-gray-300 focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 rounded-md shadow-sm text-sm"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                placeholder="Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok."
                                required
                            />
                            <p className="text-[11px] text-gray-500 mt-1">
                                Alamat ini akan dicetak langsung di bagian header slip gaji.
                            </p>
                            <InputError message={errors.address} className="mt-1" />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="phone" value="Nomor Telepon (Opsional)" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="021-xxxxxxx"
                                />
                                <InputError message={errors.phone} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="email" value="Alamat Email (Opsional)" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="info@binapustaka.co.id"
                                />
                                <InputError message={errors.email} className="mt-1" />
                            </div>
                        </div>

                        <div className="pt-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300 text-[#0B57D0] shadow-sm focus:ring-[#0B57D0]/20"
                                />
                                <span className="text-sm font-medium text-gray-700">Kantor Aktif</span>
                            </label>
                            <p className="text-[11px] text-gray-500 ml-6 mt-0.5">
                                Kantor aktif dapat dipilih untuk penempatan divisi dan karyawan baru.
                            </p>
                        </div>
                    </div>

                    <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                        <SecondaryButton type="button" onClick={closeModal} disabled={processing}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={processing} className="bg-[#0B57D0] hover:bg-[#0A48A8]">
                            {processing ? 'Menyimpan...' : editingOffice ? 'Simpan Perubahan' : 'Tambah Kantor'}
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
                    <h3 className="text-lg font-bold text-gray-900 text-center">Hapus Kantor?</h3>
                    <p className="text-sm text-gray-500 text-center mt-2">
                        Apakah Anda yakin ingin menghapus kantor <span className="font-semibold text-gray-800">{officeToDelete?.name}</span>?
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
                            Hapus Kantor
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
