import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowUpDown,
    Check,
    Coins,
    Edit3,
    Layers,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';

export default function PayComponentsIndex({ components, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [type, setType] = useState(filters?.type || '');
    const [status, setStatus] = useState(filters?.status || '');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [editingComponent, setEditingComponent] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        code: '',
        name: '',
        type: 'earning',
        sort_order: 10,
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
            route('pay-components.index'),
            { search, type, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setType('');
        setStatus('');
        router.get(route('pay-components.index'), {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingComponent(null);
        reset();
        clearErrors();
        setData({
            code: '',
            name: '',
            type: 'earning',
            sort_order: 10,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (comp) => {
        setEditingComponent(comp);
        clearErrors();
        setData({
            code: comp.code,
            name: comp.name,
            type: comp.type,
            sort_order: comp.sort_order,
            is_active: Boolean(comp.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingComponent(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingComponent) {
            put(route('pay-components.update', editingComponent.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('pay-components.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleToggleStatus = (comp) => {
        const actionText = comp.is_active ? 'menonaktifkan' : 'mengaktifkan';
        if (confirm(`Apakah Anda yakin ingin ${actionText} komponen ${comp.name}?`)) {
            router.patch(route('pay-components.toggle-status', comp.id));
        }
    };

    const handleDelete = (comp) => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus komponen ${comp.name}? Komponen yang telah dipakai pada slip gaji tidak dapat dihapus.`
            )
        ) {
            router.delete(route('pay-components.destroy', comp.id));
        }
    };

    return (
        <AuthenticatedLayout header="Master Komponen Gaji">
            <Head title="Komponen Gaji" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                            Master Komponen Gaji Dinamis
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola pos pendapatan (gaji pokok, tunjangan, bonus) dan pos potongan (pajak, BPJS, pinjaman).
                        </p>
                    </div>

                    {can('pay-components.manage') && (
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Komponen</span>
                        </button>
                    )}
                </div>

                {/* Counts Overview Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Total Komponen
                                </p>
                                <p className="mt-1 text-2xl font-bold text-slate-900">
                                    {counts?.total || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-indigo-50 p-2.5 text-indigo-600">
                                <Layers className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Pendapatan (Earnings)
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-700">
                                    {counts?.earnings || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                                <TrendingUp className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Potongan (Deductions)
                                </p>
                                <p className="mt-1 text-2xl font-bold text-rose-700">
                                    {counts?.deductions || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-rose-50 p-2.5 text-rose-600">
                                <TrendingDown className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Komponen Aktif
                                </p>
                                <p className="mt-1 text-2xl font-bold text-slate-800">
                                    {counts?.active || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-slate-100 p-2.5 text-slate-700">
                                <Coins className="h-5 w-5" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[240px]">
                            <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama komponen atau kode pos..."
                                className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            />
                        </div>

                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[160px]"
                        >
                            <option value="">Semua Tipe</option>
                            <option value="earning">Pendapatan (Earning)</option>
                            <option value="deduction">Potongan (Deduction)</option>
                        </select>

                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[140px]"
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

                        {(search || type || status) && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="text-xs font-medium text-slate-500 hover:text-slate-700 underline"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Table Data */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Urutan</th>
                                    <th className="px-6 py-3.5">Kode & Nama Komponen</th>
                                    <th className="px-6 py-3.5">Tipe Pos</th>
                                    <th className="px-6 py-3.5">Penggunaan di Slip</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                {components?.data?.length > 0 ? (
                                    components.data.map((comp) => (
                                        <tr key={comp.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-6 py-4 font-mono text-slate-500">
                                                #{comp.sort_order}
                                            </td>

                                            <td className="px-6 py-4">
                                                <div className="font-bold text-slate-900 text-sm">
                                                    {comp.name}
                                                </div>
                                                <div className="font-mono text-xs text-indigo-600 mt-0.5">
                                                    {comp.code}
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                {comp.type === 'earning' ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                                        <TrendingUp className="h-3 w-3" />
                                                        Pendapatan
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200">
                                                        <TrendingDown className="h-3 w-3" />
                                                        Potongan
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 text-slate-600">
                                                <span className="font-semibold text-slate-800">
                                                    {comp.payslip_items_count ?? 0}
                                                </span>{' '}
                                                kali digunakan
                                            </td>

                                            <td className="px-6 py-4">
                                                {comp.is_active ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 border border-slate-200">
                                                        <span className="h-1.5 w-1.5 rounded-full bg-slate-400" />
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center gap-1.5">
                                                    {can('pay-components.manage') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(comp)}
                                                                className="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 transition"
                                                                title="Ubah Komponen"
                                                            >
                                                                <Edit3 className="h-4 w-4" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => handleToggleStatus(comp)}
                                                                className={`rounded-lg p-1.5 transition ${
                                                                    comp.is_active
                                                                        ? 'text-amber-600 hover:bg-amber-50'
                                                                        : 'text-emerald-600 hover:bg-emerald-50'
                                                                }`}
                                                                title={
                                                                    comp.is_active
                                                                        ? 'Nonaktifkan Komponen'
                                                                        : 'Aktifkan Komponen'
                                                                }
                                                            >
                                                                {comp.is_active ? (
                                                                    <ToggleRight className="h-5 w-5" />
                                                                ) : (
                                                                    <ToggleLeft className="h-5 w-5" />
                                                                )}
                                                            </button>

                                                            {comp.payslip_items_count === 0 && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDelete(comp)}
                                                                    className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                                    title="Hapus Komponen"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </button>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-10 text-center text-slate-400">
                                            Tidak ada komponen gaji yang cocok.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {components?.links && components.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Menampilkan {components.from || 0} - {components.to || 0} dari {components.total} komponen
                            </span>
                            <div className="flex gap-1">
                                {components.links.map((link, idx) => (
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

                {/* Modal Create / Edit */}
                <Modal show={modalOpen} onClose={closeModal} maxWidth="md">
                    <form onSubmit={handleSubmit} className="p-6">
                        <h2 className="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">
                            {editingComponent ? 'Ubah Komponen Gaji' : 'Tambah Komponen Gaji Baru'}
                        </h2>

                        <div className="mt-4 space-y-4">
                            <div>
                                <InputLabel htmlFor="code" value="Kode Komponen (Unik) *" />
                                <TextInput
                                    id="code"
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    className="mt-1 block w-full text-xs font-mono"
                                    placeholder="Contoh: BASIC_SALARY, MEAL_ALLOWANCE, BPJS_TK"
                                    required
                                    disabled={Boolean(editingComponent && editingComponent.payslip_items_count > 0)}
                                />
                                <p className="mt-1 text-[11px] text-slate-400">
                                    Hanya huruf besar, angka, dan underscore (_).
                                </p>
                                <InputError message={errors.code} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="name" value="Nama Komponen *" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full text-xs"
                                    placeholder="Contoh: Gaji Pokok, Tunjangan Makan, Potongan BPJS Ketenagakerjaan"
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="type" value="Tipe Pos *" />
                                    <select
                                        id="type"
                                        value={data.type}
                                        onChange={(e) => setData('type', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs"
                                        required
                                        disabled={Boolean(editingComponent && editingComponent.payslip_items_count > 0)}
                                    >
                                        <option value="earning">Pendapatan (Earning)</option>
                                        <option value="deduction">Potongan (Deduction)</option>
                                    </select>
                                    <InputError message={errors.type} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="sort_order" value="Urutan Pos *" />
                                    <TextInput
                                        id="sort_order"
                                        type="number"
                                        value={data.sort_order}
                                        onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                        className="mt-1 block w-full text-xs"
                                        min="0"
                                        required
                                    />
                                    <InputError message={errors.sort_order} className="mt-1" />
                                </div>
                            </div>

                            <div>
                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.is_active}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                                    />
                                    <span>Aktifkan komponen ini untuk penginputan slip gaji baru</span>
                                </label>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <SecondaryButton type="button" onClick={closeModal} className="rounded-xl">
                                Batal
                            </SecondaryButton>
                            <PrimaryButton disabled={processing} className="rounded-xl">
                                {processing ? 'Menyimpan...' : editingComponent ? 'Simpan Perubahan' : 'Buat Komponen'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Modal>
            </div>
        </AuthenticatedLayout>
    );
}
