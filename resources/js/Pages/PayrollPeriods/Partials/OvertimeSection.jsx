import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    Coffee,
    Edit3,
    FileSpreadsheet,
    HelpCircle,
    Info,
    Moon,
    Plus,
    Receipt,
    Search,
    Sun,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import OvertimeModal from './OvertimeModal';

export default function OvertimeSection({
    period,
    overtimeEntries = [],
    overtimeRecap = [],
    overtimeTotals = {},
    overtimeConfig = {},
    activeEmployees = [],
    canManage = false,
}) {
    const [subTab, setSubTab] = useState('entries'); // 'entries' | 'recap'
    const [search, setSearch] = useState('');
    const [dayTypeFilter, setDayTypeFilter] = useState('');

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [entryToEdit, setEntryToEdit] = useState(null);

    // Delete dialog state
    const [entryToDelete, setEntryToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    // Filtered entries
    const filteredEntries = useMemo(() => {
        return overtimeEntries.filter((entry) => {
            const matchesSearch =
                !search.trim() ||
                entry.employee?.full_name?.toLowerCase().includes(search.toLowerCase()) ||
                entry.employee?.employee_number?.toLowerCase().includes(search.toLowerCase()) ||
                entry.date?.includes(search) ||
                entry.note?.toLowerCase().includes(search.toLowerCase());

            const matchesDayType = !dayTypeFilter || entry.day_type === dayTypeFilter;

            return matchesSearch && matchesDayType;
        });
    }, [overtimeEntries, search, dayTypeFilter]);

    const handleOpenCreateModal = () => {
        setEntryToEdit(null);
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (entry) => {
        setEntryToEdit(entry);
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setEntryToEdit(null);
    };

    const handleConfirmDelete = () => {
        if (!entryToDelete) return;
        setIsDeleting(true);
        router.delete(route('payroll-periods.overtimes.destroy', [period.id, entryToDelete.id]), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setEntryToDelete(null);
            },
        });
    };

    const isLocked = !canManage;

    return (
        <div className="space-y-6">
            {/* Top Overview Cards for Overtime */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-medium text-slate-500">
                        <span>Total Entri Lembur</span>
                        <Clock className="h-4 w-4 text-blue-600" />
                    </div>
                    <div className="mt-1 text-2xl font-bold text-slate-900">
                        {overtimeEntries.length}
                    </div>
                    <p className="mt-0.5 text-[11px] text-slate-400">
                        {overtimeRecap.length} karyawan lembur
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-medium text-indigo-700">
                        <span>Jam Hari Biasa (Bruto)</span>
                        <Sun className="h-4 w-4 text-indigo-500" />
                    </div>
                    <div className="mt-1 text-2xl font-bold text-indigo-900">
                        {Number(overtimeTotals.total_working_day_hours || 0).toFixed(2)}{' '}
                        <span className="text-xs font-normal text-slate-500">jam</span>
                    </div>
                    <p className="mt-0.5 text-[11px] text-slate-400">
                        Threshold: ≥ {overtimeConfig.working_day_threshold ?? 3.0} jam
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-medium text-amber-700">
                        <span>Jam Hari Libur (Bruto)</span>
                        <Coffee className="h-4 w-4 text-amber-500" />
                    </div>
                    <div className="mt-1 text-2xl font-bold text-amber-900">
                        {Number(overtimeTotals.total_holiday_hours || 0).toFixed(2)}{' '}
                        <span className="text-xs font-normal text-slate-500">jam</span>
                    </div>
                    <p className="mt-0.5 text-[11px] text-slate-400">
                        Threshold: ≥ {overtimeConfig.holiday_threshold ?? 4.0} jam
                    </p>
                </div>

                <div className="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-medium text-rose-700">
                        <span>Potongan Istirahat</span>
                        <TrendingDown className="h-4 w-4 text-rose-500" />
                    </div>
                    <div className="mt-1 text-2xl font-bold text-rose-700">
                        {Number(overtimeTotals.total_break_deduction_hours || 0).toFixed(2)}{' '}
                        <span className="text-xs font-normal text-rose-500">jam</span>
                    </div>
                    <p className="mt-0.5 text-[11px] font-semibold text-rose-600">
                        {formatRupiah(overtimeTotals.total_potongan_lembur)}
                    </p>
                </div>

                <div className="rounded-2xl border border-blue-100 bg-blue-50/60 p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-blue-900">
                        <span>Total Bersih Lembur</span>
                        <Wallet className="h-4 w-4 text-[#0B57D0]" />
                    </div>
                    <div className="mt-1 text-2xl font-extrabold text-[#0B57D0]">
                        {formatRupiah(overtimeTotals.total_upah_bersih)}
                    </div>
                    <p className="mt-0.5 text-[11px] text-slate-500 font-medium">
                        Net: {Number(overtimeTotals.total_net_hours || 0).toFixed(2)} jam dibayarkan
                    </p>
                </div>
            </div>

            {/* Sub-tab Navigation and Actions */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-3">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setSubTab('entries')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition ${
                            subTab === 'entries'
                                ? 'bg-slate-900 text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <Clock className="h-3.5 w-3.5" />
                        <span>Daftar Entri Lembur ({overtimeEntries.length})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setSubTab('recap')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition ${
                            subTab === 'recap'
                                ? 'bg-slate-900 text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <FileSpreadsheet className="h-3.5 w-3.5" />
                        <span>Rekap Per Karyawan & Upah/Potongan ({overtimeRecap.length})</span>
                    </button>
                </div>

                <div className="flex items-center gap-2">
                    {canManage && (
                        <button
                            type="button"
                            onClick={handleOpenCreateModal}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Data Lembur</span>
                        </button>
                    )}
                </div>
            </div>

            {/* View 1: Daftar Entri Lembur */}
            {subTab === 'entries' && (
                <div className="space-y-4">
                    {/* Search and Day Type Filter Bar */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2.5 flex-1 min-w-[260px]">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama karyawan, NIK, tanggal, atau keterangan..."
                                    className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-1.5 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                                />
                            </div>

                            <select
                                value={dayTypeFilter}
                                onChange={(e) => setDayTypeFilter(e.target.value)}
                                className="rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            >
                                <option value="">Semua Jenis Hari</option>
                                <option value="working_day">Hari Biasa</option>
                                <option value="holiday">Hari Libur</option>
                            </select>
                        </div>

                        {(search || dayTypeFilter) && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setDayTypeFilter('');
                                }}
                                className="text-xs font-medium text-slate-500 hover:text-slate-800 underline"
                            >
                                Reset Filter
                            </button>
                        )}
                    </div>

                    {/* Entries Table */}
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th className="px-5 py-3.5">Tanggal</th>
                                        <th className="px-5 py-3.5">Karyawan</th>
                                        <th className="px-5 py-3.5">Jenis Hari</th>
                                        <th className="px-5 py-3.5">Mulai</th>
                                        <th className="px-5 py-3.5">Selesai</th>
                                        <th className="px-5 py-3.5 text-right">Durasi</th>
                                        <th className="px-5 py-3.5">Keterangan</th>
                                        <th className="px-5 py-3.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                    {filteredEntries.length > 0 ? (
                                        filteredEntries.map((entry) => {
                                            const startMin = entry.start_time
                                                ? parseInt(entry.start_time.split(':')[0]) * 60 +
                                                  parseInt(entry.start_time.split(':')[1])
                                                : 0;
                                            const endMin = entry.end_time
                                                ? parseInt(entry.end_time.split(':')[0]) * 60 +
                                                  parseInt(entry.end_time.split(':')[1])
                                                : 0;
                                            const crossesMidnight = endMin < startMin;

                                            const wholeH = Math.floor(entry.duration_minutes / 60);
                                            const remM = entry.duration_minutes % 60;
                                            const durationFormatted =
                                                remM > 0 ? `${wholeH} jam ${remM} mnt` : `${wholeH} jam`;

                                            return (
                                                <tr
                                                    key={entry.id}
                                                    className="hover:bg-slate-50/60 transition"
                                                >
                                                    <td className="px-5 py-3.5 font-medium text-slate-900 whitespace-nowrap">
                                                        <div className="flex items-center gap-1.5">
                                                            <Calendar className="h-3.5 w-3.5 text-slate-400" />
                                                            <span>{formatDate(entry.date)}</span>
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-3.5">
                                                        <div className="font-bold text-slate-900 text-sm">
                                                            {entry.employee?.full_name || 'Karyawan'}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 mt-0.5">
                                                            NIK: {entry.employee?.employee_number || '-'} •{' '}
                                                            {entry.employee?.department || 'Umum'}
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-3.5 whitespace-nowrap">
                                                        {entry.day_type === 'holiday' ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700">
                                                                <Coffee className="h-3 w-3 text-rose-500" />
                                                                <span>Hari Libur</span>
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700">
                                                                <Sun className="h-3 w-3 text-amber-500" />
                                                                <span>Hari Biasa</span>
                                                            </span>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-3.5 font-mono text-slate-800 whitespace-nowrap">
                                                        {entry.start_time?.slice(0, 5)}
                                                    </td>

                                                    <td className="px-5 py-3.5 font-mono text-slate-800 whitespace-nowrap">
                                                        <div className="flex items-center gap-1">
                                                            <span>{entry.end_time?.slice(0, 5)}</span>
                                                            {crossesMidnight && (
                                                                <span
                                                                    className="rounded bg-indigo-100 px-1 py-0.2 text-[9px] font-bold text-indigo-700"
                                                                    title="Melewati tengah malam (+1 hari)"
                                                                >
                                                                    🌙 +1
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-3.5 text-right whitespace-nowrap">
                                                        <div className="font-bold text-slate-900">
                                                            {durationFormatted}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400">
                                                            ({Number(entry.duration_hours).toFixed(2)} jam)
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-3.5 max-w-[200px] truncate text-slate-500">
                                                        {entry.note || '-'}
                                                    </td>

                                                    <td className="px-5 py-3.5 text-right whitespace-nowrap">
                                                        {canManage ? (
                                                            <div className="inline-flex items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleOpenEditModal(entry)}
                                                                    className="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 transition"
                                                                    title="Ubah Entri Lembur"
                                                                >
                                                                    <Edit3 className="h-4 w-4" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setEntryToDelete(entry)}
                                                                    className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                                    title="Hapus Entri Lembur"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <span className="text-[11px] text-slate-400 italic">
                                                                Terkunci
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="8" className="px-6 py-12 text-center text-slate-400">
                                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                                    <Clock className="h-6 w-6" />
                                                </div>
                                                <p className="font-medium text-slate-600">
                                                    Belum ada data lembur untuk periode ini.
                                                </p>
                                                {canManage && (
                                                    <button
                                                        type="button"
                                                        onClick={handleOpenCreateModal}
                                                        className="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-[#0B57D0] px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition"
                                                    >
                                                        <Plus className="h-4 w-4" />
                                                        <span>Tambah Data Lembur Karyawan</span>
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* View 2: Rekap Per Karyawan & Pisahkan Upah/Potongan */}
            {subTab === 'recap' && (
                <div className="space-y-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">
                                    Rekapitulasi Upah dan Potongan Lembur Per Karyawan
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Rincian terpisah antara lembur hari biasa, lembur hari libur, status threshold potongan istirahat, serta total upah bersih yang masuk slip gaji.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th className="px-5 py-3.5">Karyawan</th>
                                        <th className="px-4 py-3.5 text-center">Tarif</th>
                                        <th className="px-4 py-3.5 bg-indigo-50/50 text-indigo-900 text-center">
                                            Hari Biasa (Jam)
                                        </th>
                                        <th className="px-4 py-3.5 bg-indigo-50/50 text-indigo-900 text-right">
                                            Upah Hari Biasa
                                        </th>
                                        <th className="px-4 py-3.5 bg-amber-50/50 text-amber-900 text-center">
                                            Hari Libur (Jam)
                                        </th>
                                        <th className="px-4 py-3.5 bg-amber-50/50 text-amber-900 text-right">
                                            Upah Hari Libur
                                        </th>
                                        <th className="px-4 py-3.5 bg-rose-50/40 text-rose-800 text-right">
                                            Potongan Istirahat
                                        </th>
                                        <th className="px-5 py-3.5 bg-blue-50/60 text-[#0B57D0] text-right font-bold">
                                            Total Bersih Lembur
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                    {overtimeRecap.length > 0 ? (
                                        overtimeRecap.map((item) => (
                                            <tr key={item.employee_id} className="hover:bg-slate-50/60 transition">
                                                <td className="px-5 py-3.5">
                                                    <div className="font-bold text-slate-900 text-sm">
                                                        {item.employee_name}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 mt-0.5">
                                                        NIK: {item.employee_number} • {item.department} • {item.entries_count} entri
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3.5 text-center font-mono text-slate-700">
                                                    {item.rate_formatted}
                                                </td>

                                                {/* Hari Biasa */}
                                                <td className="px-4 py-3.5 bg-indigo-50/20 text-center">
                                                    <div className="font-bold text-indigo-900">
                                                        {Number(item.working_day?.gross_hours || 0).toFixed(2)} jam
                                                    </div>
                                                    <div className="mt-0.5">
                                                        {item.working_day?.threshold_reached ? (
                                                            <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.2 text-[10px] font-semibold text-amber-800">
                                                                Dipotong {item.working_day?.break_deduction_hours} jam
                                                            </span>
                                                        ) : (
                                                            <span className="text-[10px] text-slate-400">
                                                                Bebas potong
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3.5 bg-indigo-50/20 text-right font-medium text-indigo-900">
                                                    {item.working_day?.pay_formatted}
                                                </td>

                                                {/* Hari Libur */}
                                                <td className="px-4 py-3.5 bg-amber-50/20 text-center">
                                                    <div className="font-bold text-amber-900">
                                                        {Number(item.holiday?.gross_hours || 0).toFixed(2)} jam
                                                    </div>
                                                    <div className="mt-0.5">
                                                        {item.holiday?.threshold_reached ? (
                                                            <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.2 text-[10px] font-semibold text-amber-800">
                                                                Dipotong {item.holiday?.break_deduction_hours} jam
                                                            </span>
                                                        ) : (
                                                            <span className="text-[10px] text-slate-400">
                                                                Bebas potong
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3.5 bg-amber-50/20 text-right font-medium text-amber-900">
                                                    {item.holiday?.pay_formatted}
                                                </td>

                                                {/* Potongan Istirahat */}
                                                <td className="px-4 py-3.5 bg-rose-50/20 text-right">
                                                    <div className="font-bold text-rose-700">
                                                        {item.total_potongan_lembur_formatted}
                                                    </div>
                                                    <div className="text-[10px] text-rose-500 mt-0.5">
                                                        ({Number(item.total_break_deduction_hours || 0).toFixed(2)} jam)
                                                    </div>
                                                </td>

                                                {/* Total Bersih Lembur */}
                                                <td className="px-5 py-3.5 bg-blue-50/30 text-right">
                                                    <div className="font-extrabold text-[#0B57D0] text-sm">
                                                        {item.total_upah_lembur_formatted}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 font-medium mt-0.5">
                                                        Net: {Number(item.total_net_hours || 0).toFixed(2)} jam
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="8" className="px-6 py-10 text-center text-slate-400">
                                                Belum ada rekapitulasi lembur untuk periode ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* Overtime Modal */}
            <OvertimeModal
                show={isModalOpen}
                onClose={handleCloseModal}
                period={period}
                activeEmployees={activeEmployees}
                overtimeConfig={overtimeConfig}
                entryToEdit={entryToEdit}
            />

            {/* Delete Confirmation Modal */}
            <Modal show={Boolean(entryToDelete)} onClose={() => setEntryToDelete(null)} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                            <Trash2 className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-slate-900">Hapus Data Lembur?</h3>
                            <p className="text-xs text-slate-500">
                                Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-700 space-y-1">
                        <div>
                            Karyawan: <strong>{entryToDelete?.employee?.full_name}</strong>
                        </div>
                        <div>
                            Tanggal: <strong>{formatDate(entryToDelete?.date)}</strong>
                        </div>
                        <div>
                            Jam: <strong>{entryToDelete?.start_time?.slice(0, 5)} - {entryToDelete?.end_time?.slice(0, 5)}</strong> ({entryToDelete?.duration_hours} jam)
                        </div>
                        <div className="text-rose-600 text-[11px] pt-1">
                            Slip gaji draft karyawan akan dihitung ulang secara otomatis.
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setEntryToDelete(null)} disabled={isDeleting}>
                            Batal
                        </SecondaryButton>
                        <button
                            type="button"
                            onClick={handleConfirmDelete}
                            disabled={isDeleting}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 transition disabled:opacity-50"
                        >
                            {isDeleting ? 'Menghapus...' : 'Ya, Hapus Data Lembur'}
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
