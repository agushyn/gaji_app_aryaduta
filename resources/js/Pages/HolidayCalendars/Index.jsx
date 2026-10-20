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
    Calendar,
    CalendarCheck,
    Check,
    Edit3,
    Plus,
    RefreshCw,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

export default function HolidayCalendarsIndex({
    holidays,
    selectedYear,
    availableYears,
    counts,
    lastSyncedAt,
    filters,
}) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [type, setType] = useState(filters?.type || '');
    const [payrollHolidayFilter, setPayrollHolidayFilter] = useState(filters?.payroll_holiday || '');
    const [year, setYear] = useState(selectedYear);

    // Sync modal state
    const [syncModalOpen, setSyncModalOpen] = useState(false);
    const [syncYear, setSyncYear] = useState(selectedYear);
    const [isSyncing, setIsSyncing] = useState(false);

    // Manual add/edit modal state
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        date: '',
        name: '',
        type: 'national_holiday',
        is_joint_leave: false,
        is_payroll_holiday: true,
        is_active: true,
        description: '',
    });

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*') || user.roles?.includes('super_admin')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('holiday-calendars.index'),
            { search, type, payroll_holiday: payrollHolidayFilter, year },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setType('');
        setPayrollHolidayFilter('');
        router.get(
            route('holiday-calendars.index'),
            { year: selectedYear },
            { preserveState: true, replace: true }
        );
    };

    const handleYearChange = (newYear) => {
        setYear(newYear);
        router.get(
            route('holiday-calendars.index'),
            { search, type, payroll_holiday: payrollHolidayFilter, year: newYear },
            { preserveState: true, replace: true }
        );
    };

    const handleTogglePayrollHoliday = (item) => {
        router.patch(
            route('holiday-calendars.toggle-payroll-holiday', item.id),
            {},
            { preserveScroll: true }
        );
    };

    const handleToggleStatus = (item) => {
        router.patch(
            route('holiday-calendars.toggle-status', item.id),
            {},
            { preserveScroll: true }
        );
    };

    const handleSync = (e) => {
        e.preventDefault();
        setIsSyncing(true);
        router.post(
            route('holiday-calendars.sync'),
            { year: syncYear },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSyncing(false);
                    setSyncModalOpen(false);
                },
            }
        );
    };

    const openCreateModal = () => {
        setEditingItem(null);
        reset();
        clearErrors();
        setData({
            date: `${selectedYear}-01-01`,
            name: '',
            type: 'national_holiday',
            is_joint_leave: false,
            is_payroll_holiday: true,
            is_active: true,
            description: '',
        });
        setEditModalOpen(true);
    };

    const openEditModal = (item) => {
        setEditingItem(item);
        clearErrors();
        setData({
            date: item.date,
            name: item.name,
            type: item.type,
            is_joint_leave: item.is_joint_leave,
            is_payroll_holiday: item.is_payroll_holiday,
            is_active: item.is_active,
            description: item.description || '',
        });
        setEditModalOpen(true);
    };

    const handleSaveHoliday = (e) => {
        e.preventDefault();
        if (editingItem) {
            put(route('holiday-calendars.update', editingItem.id), {
                preserveScroll: true,
                onSuccess: () => setEditModalOpen(false),
            });
        } else {
            post(route('holiday-calendars.store'), {
                preserveScroll: true,
                onSuccess: () => setEditModalOpen(false),
            });
        }
    };

    const handleDelete = (item) => {
        if (confirm(`Hapus '${item.name}' dari kalender libur?`)) {
            router.delete(route('holiday-calendars.destroy', item.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            title="Kalender Libur Indonesia"
            header="Kalender Libur Indonesia"
        >
            <Head title="Kalender Libur Indonesia" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0] shrink-0">
                            <CalendarCheck className="h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                Kalender Libur Nasional & Cuti Bersama
                            </h1>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Disimpan secara lokal di database (bebas downtime API saat hitung payroll) dengan acuan SKB 3 Menteri.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can('payroll.holiday.sync') && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSyncYear(year);
                                    setSyncModalOpen(true);
                                }}
                                className="inline-flex items-center gap-2 px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-xs transition"
                            >
                                <RefreshCw className="h-4 w-4" />
                                Sync Kalender
                            </button>
                        )}
                        {can('payroll.holiday.update') && (
                            <button
                                type="button"
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-[#0B57D0] hover:bg-[#0A58CA] text-white text-xs font-semibold rounded-xl shadow-xs transition"
                            >
                                <Plus className="h-4 w-4" />
                                Tambah Libur
                            </button>
                        )}
                    </div>
                </div>

                {/* Stats & Last Synced Info */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-slate-500 font-medium">Tahun Terpilih</div>
                        <div className="text-2xl font-bold text-slate-900 mt-1">{selectedYear}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-blue-600 font-medium">Libur Nasional</div>
                        <div className="text-2xl font-bold text-blue-700 mt-1">{counts.national}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-amber-600 font-medium">Cuti Bersama</div>
                        <div className="text-2xl font-bold text-amber-700 mt-1">{counts.joint_leave}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-emerald-600 font-medium">Libur Payroll</div>
                        <div className="text-2xl font-bold text-emerald-700 mt-1">{counts.payroll_holiday}</div>
                    </div>
                    <div className="col-span-2 md:col-span-1 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-slate-400 font-medium">Terakhir Sync</div>
                        <div className="text-xs font-bold text-slate-700 mt-2 truncate">
                            {lastSyncedAt || 'Belum pernah sync'}
                        </div>
                    </div>
                </div>

                {/* Filters & Year Selector */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Cari nama hari libur..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="flex items-center space-x-2">
                            <label className="text-xs text-slate-500 font-medium">Tahun:</label>
                            <select
                                value={year}
                                onChange={(e) => handleYearChange(e.target.value)}
                                className="text-xs font-bold rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {availableYears.map((yr) => (
                                    <option key={yr} value={yr}>
                                        {yr}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Kategori</option>
                            <option value="national_holiday">Libur Nasional</option>
                            <option value="joint_leave">Cuti Bersama</option>
                            <option value="custom">Libur Khusus</option>
                        </select>
                        <select
                            value={payrollHolidayFilter}
                            onChange={(e) => setPayrollHolidayFilter(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Status Libur Payroll</option>
                            <option value="yes">Diperlakukan Sebagai Libur Payroll</option>
                            <option value="no">Bukan Libur Payroll</option>
                        </select>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition"
                            >
                                Cari
                            </button>
                            <button
                                type="button"
                                onClick={handleReset}
                                className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                {/* Table of Holidays */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-3.5">Tanggal & Hari</th>
                                    <th className="px-4 py-3.5">Nama Hari Libur</th>
                                    <th className="px-4 py-3.5">Jenis</th>
                                    <th className="px-4 py-3.5 text-center">Hitung Libur Payroll?</th>
                                    <th className="px-4 py-3.5 text-center">Status</th>
                                    <th className="px-4 py-3.5">Sumber Data</th>
                                    <th className="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {holidays.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-10 text-center text-slate-400">
                                            Tidak ada data hari libur untuk filter tahun {selectedYear}. Silakan klik tombol <strong>Sync Kalender</strong>.
                                        </td>
                                    </tr>
                                ) : (
                                    holidays.data.map((h) => (
                                        <tr key={h.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-3 font-medium text-slate-800 whitespace-nowrap">
                                                <div className="font-semibold text-slate-900">{h.formatted_date}</div>
                                                <div className="text-[11px] text-slate-500">{h.day_name}</div>
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-800">
                                                {h.name}
                                                {h.description && (
                                                    <div className="text-[11px] text-slate-400 font-normal">{h.description}</div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                                        h.type === 'national_holiday'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : h.type === 'joint_leave'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {h.type_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center whitespace-nowrap">
                                                {can('payroll.holiday.update') ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleTogglePayrollHoliday(h)}
                                                        className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold transition ${
                                                            h.is_payroll_holiday
                                                                ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                                                        }`}
                                                        title="Klik untuk mengubah perlakuan hari libur payroll"
                                                    >
                                                        {h.is_payroll_holiday ? 'Ya (Libur Payroll)' : 'Tidak (Hari Kerja)'}
                                                    </button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-600">
                                                        {h.is_payroll_holiday ? 'Ya' : 'Tidak'}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center whitespace-nowrap">
                                                {can('payroll.holiday.update') ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleToggleStatus(h)}
                                                        title={h.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                        className="text-slate-400 hover:text-indigo-600"
                                                    >
                                                        {h.is_active ? (
                                                            <ToggleRight className="w-5 h-5 text-emerald-600" />
                                                        ) : (
                                                            <ToggleLeft className="w-5 h-5 text-slate-400" />
                                                        )}
                                                    </button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-500">
                                                        {h.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-400 text-[11px] font-mono whitespace-nowrap">
                                                {h.source}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center space-x-1">
                                                    {can('payroll.holiday.update') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openEditModal(h)}
                                                            className="p-1 text-slate-400 hover:text-blue-600 rounded"
                                                            title="Edit"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                    {can('payroll.holiday.update') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDelete(h)}
                                                            className="p-1 text-slate-400 hover:text-rose-600 rounded"
                                                            title="Hapus"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {holidays.links && holidays.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>Menampilkan {holidays.data.length} dari {holidays.total} data</div>
                            <div className="flex space-x-1">
                                {holidays.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`px-2.5 py-1 rounded text-xs ${
                                            link.active
                                                ? 'bg-indigo-600 text-white font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100'
                                        } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Sync Kalender Modal */}
            <Modal show={syncModalOpen} onClose={() => setSyncModalOpen(false)}>
                <form onSubmit={handleSync} className="p-6 space-y-4">
                    <div className="flex items-center space-x-2 border-b border-slate-200 pb-3">
                        <RefreshCw className="w-5 h-5 text-emerald-600" />
                        <h3 className="font-bold text-slate-800 text-base">
                            Sinkronisasi Kalender Libur Indonesia
                        </h3>
                    </div>

                    <p className="text-xs text-slate-600 leading-relaxed">
                        Sistem akan mengambil data hari libur nasional dan cuti bersama berdasarkan <strong>SKB 3 Menteri</strong> untuk tahun yang dipilih. Data di database lokal akan diperbarui secara aman tanpa membuat data duplikat.
                    </p>

                    <div>
                        <InputLabel htmlFor="sync_year" value="Tahun Kalender yang Akan Disinkronkan *" />
                        <input
                            id="sync_year"
                            type="number"
                            min="2020"
                            max="2030"
                            value={syncYear}
                            onChange={(e) => setSyncYear(e.target.value)}
                            className="mt-1 w-full text-xs font-bold rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                    </div>

                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-500 space-y-1">
                        <div>• Sumber data: External API / Katalog Resmi SKB 3 Menteri</div>
                        <div>• Penyimpanan: Database lokal (payroll tidak call API saat hitung)</div>
                        <div>• Idempotent: Record di-upsert berdasarkan tanggal + jenis</div>
                    </div>

                    <div className="flex justify-end space-x-2 pt-2">
                        <SecondaryButton onClick={() => setSyncModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton disabled={isSyncing} className="text-xs bg-emerald-600 hover:bg-emerald-700">
                            {isSyncing ? 'Menyinkronkan...' : `Sinkronkan Tahun ${syncYear}`}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Create / Edit Holiday Modal */}
            <Modal show={editModalOpen} onClose={() => setEditModalOpen(false)}>
                <form onSubmit={handleSaveHoliday} className="p-6 space-y-4">
                    <div className="border-b border-slate-200 pb-3">
                        <h3 className="font-bold text-slate-800 text-base">
                            {editingItem ? 'Edit Hari Libur' : 'Tambah Hari Libur Manual'}
                        </h3>
                    </div>

                    <div className="space-y-3">
                        <div>
                            <InputLabel htmlFor="h_date" value="Tanggal *" />
                            <TextInput
                                id="h_date"
                                type="date"
                                className="mt-1 block w-full text-xs"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                required
                            />
                            <InputError message={errors.date} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="h_name" value="Nama Hari Libur *" />
                            <TextInput
                                id="h_name"
                                type="text"
                                className="mt-1 block w-full text-xs"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Contoh: Hari Kemerdekaan RI"
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="h_type" value="Jenis Libur *" />
                            <select
                                id="h_type"
                                value={data.type}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setData((prev) => ({
                                        ...prev,
                                        type: val,
                                        is_joint_leave: val === 'joint_leave',
                                        is_payroll_holiday: val === 'national_holiday' ? true : prev.is_payroll_holiday,
                                    }));
                                }}
                                className="mt-1 block w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="national_holiday">Libur Nasional</option>
                                <option value="joint_leave">Cuti Bersama</option>
                                <option value="custom">Libur Khusus Perusahaan</option>
                            </select>
                            <InputError message={errors.type} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="h_desc" value="Keterangan Tambahan" />
                            <TextInput
                                id="h_desc"
                                type="text"
                                className="mt-1 block w-full text-xs"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Opsional"
                            />
                        </div>

                        <div className="pt-2 space-y-2">
                            <div className="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    id="h_payroll"
                                    checked={data.is_payroll_holiday}
                                    onChange={(e) => setData('is_payroll_holiday', e.target.checked)}
                                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label htmlFor="h_payroll" className="text-xs font-medium text-slate-700 cursor-pointer">
                                    Gunakan sebagai Hari Libur Payroll (Lembur dinilai dengan aturan Hari Libur)
                                </label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    id="h_active"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label htmlFor="h_active" className="text-xs font-medium text-slate-700 cursor-pointer">
                                    Aktif
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-2 pt-2">
                        <SecondaryButton onClick={() => setEditModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton disabled={processing} className="text-xs">
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
