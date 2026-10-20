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
    Calendar,
    Check,
    CheckCircle,
    Clock,
    Download,
    Edit3,
    FileSpreadsheet,
    HelpCircle,
    Info,
    RefreshCw,
    Search,
    Settings,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function SecurityPayrollIndex({
    periods = [],
    currentPeriod,
    periodDates = [],
    securityEmployees = [],
    attendances = {},
    summaries = {},
    activeConfig,
    totalOvertimeAllSecurity = 0,
    totalEmployees = 0,
    canManage = false,
    canConfig = false,
    filters = {},
}) {
    const { flash } = usePage().props;

    const [search, setSearch] = useState(filters?.search || '');
    const [selectedPeriodId, setSelectedPeriodId] = useState(currentPeriod?.id || '');

    // Modal state for Attendance Cell Editing
    const [attendanceModalOpen, setAttendanceModalOpen] = useState(false);
    const [selectedCell, setSelectedCell] = useState(null);

    // Modal state for Summary Overtime / Break Override
    const [summaryModalOpen, setSummaryModalOpen] = useState(false);
    const [selectedSummaryEmployee, setSelectedSummaryEmployee] = useState(null);

    // Modal state for Formula Configuration
    const [configModalOpen, setConfigModalOpen] = useState(false);

    // Attendance cell form
    const attendanceForm = useForm({
        payroll_period_id: currentPeriod?.id || '',
        employee_id: '',
        date: '',
        status: 'present',
        work_hours: 8,
        overtime_hours: 0,
        note: '',
    });

    // Summary override form
    const summaryForm = useForm({
        payroll_period_id: currentPeriod?.id || '',
        employee_id: '',
        gross_overtime_hours: '',
        break_deduction_hours: '',
    });

    // Config form
    const configForm = useForm({
        hourly_rate: activeConfig?.hourly_rate || 14000,
        first_hours_limit: activeConfig?.first_hours_limit || 5,
        first_hours_multiplier: activeConfig?.first_hours_multiplier || 1.5,
        subsequent_hours_multiplier: activeConfig?.subsequent_hours_multiplier || 2.0,
        break_deduction_default: activeConfig?.break_deduction_default || 0,
        max_break_deduction: activeConfig?.max_break_deduction || 20,
        round_rule: activeConfig?.round_rule || 'round',
    });

    const formatRupiah = (val) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(val || 0);
    };

    const formatNumber = (val) => {
        return Number(val || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        });
    };

    const handlePeriodChange = (newPeriodId) => {
        setSelectedPeriodId(newPeriodId);
        router.get(
            route('payroll-security.index'),
            { period_id: newPeriodId, search },
            { preserveState: true, replace: true }
        );
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payroll-security.index'),
            { period_id: selectedPeriodId, search },
            { preserveState: true, replace: true }
        );
    };

    // Open cell editor
    const openCellEditor = (employee, dateObj) => {
        if (!canManage || !currentPeriod?.is_modifiable) return;

        const key = `${employee.id}_${dateObj.date}`;
        const existing = attendances[key];

        setSelectedCell({ employee, dateObj, existing });

        attendanceForm.setData({
            payroll_period_id: currentPeriod.id,
            employee_id: employee.id,
            date: dateObj.date,
            status: existing ? existing.status : 'present',
            work_hours: existing ? existing.work_hours : 8,
            overtime_hours: existing ? existing.overtime_hours : 0,
            note: existing ? existing.note || '' : '',
        });

        setAttendanceModalOpen(true);
    };

    const applyCellPreset = (status, work, ot) => {
        attendanceForm.setData((prev) => ({
            ...prev,
            status,
            work_hours: work,
            overtime_hours: ot,
        }));
    };

    const handleSaveAttendance = (e) => {
        e.preventDefault();
        attendanceForm.post(route('payroll-security.attendance.update'), {
            preserveScroll: true,
            onSuccess: () => setAttendanceModalOpen(false),
        });
    };

    // Open summary override editor
    const openSummaryEditor = (employee) => {
        if (!canManage || !currentPeriod?.is_modifiable) return;

        const summary = summaries[employee.id];
        setSelectedSummaryEmployee(employee);

        summaryForm.setData({
            payroll_period_id: currentPeriod.id,
            employee_id: employee.id,
            gross_overtime_hours: summary ? summary.gross_overtime_hours : '',
            break_deduction_hours: summary ? summary.break_deduction_hours : '',
        });

        setSummaryModalOpen(true);
    };

    const handleSaveSummary = (e) => {
        e.preventDefault();
        summaryForm.post(route('payroll-security.summary.update'), {
            preserveScroll: true,
            onSuccess: () => setSummaryModalOpen(false),
        });
    };

    // Save formula config
    const handleSaveConfig = (e) => {
        e.preventDefault();
        configForm.post(route('payroll-security.config.update'), {
            preserveScroll: true,
            onSuccess: () => setConfigModalOpen(false),
        });
    };

    // Batch sync to draft payslips
    const handleSyncPayslips = () => {
        if (!currentPeriod) return;
        if (confirm(`Sinkronisasi perhitungan lembur seluruh Security ke slip gaji periode ${currentPeriod.period_code}?`)) {
            router.post(route('payroll-security.periods.sync', currentPeriod.id), {}, {
                preserveScroll: true,
            });
        }
    };

    // Preview calculation with live inputs for Config Modal
    const previewGross = 40;
    const previewDeduction = Number(configForm.data.break_deduction_default || 5);
    const previewNet = Math.max(0, previewGross - previewDeduction);
    const previewLimit = Number(configForm.data.first_hours_limit || 5);
    const previewFirst = Math.min(previewNet, previewLimit);
    const previewSubsequent = Math.max(0, previewNet - previewFirst);
    const previewRate = Number(configForm.data.hourly_rate || 14000);
    const previewMultFirst = Number(configForm.data.first_hours_multiplier || 1.5);
    const previewMultSubsequent = Number(configForm.data.subsequent_hours_multiplier || 2.0);
    const previewFirstCost = Math.round(previewFirst * previewRate * previewMultFirst);
    const previewSubsequentCost = Math.round(previewSubsequent * previewRate * previewMultSubsequent);
    const previewTotalCost = previewFirstCost + previewSubsequentCost;

    return (
        <AuthenticatedLayout
            title="Perhitungan Payroll Khusus Security"
            header="Payroll Khusus Security"
            fullWidth={true}
        >
            <Head title="Payroll Khusus Security" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold shrink-0">
                            <ShieldCheck className="w-6 h-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                    Payroll Khusus Security
                                </h1>
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                    Formula Khusus
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Matriks rekap absensi harian dan perhitungan lembur berjenjang petugas keamanan.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {currentPeriod && (
                            <a
                                href={route('payroll-security.periods.export-excel', currentPeriod.id)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
                                Export Excel
                            </a>
                        )}

                        {canConfig && (
                            <button
                                type="button"
                                onClick={() => setConfigModalOpen(true)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Settings className="w-4 h-4 text-slate-600" />
                                Aturan Rumus (v{activeConfig?.version || 1})
                            </button>
                        )}

                        {canManage && currentPeriod?.is_modifiable && (
                            <button
                                type="button"
                                onClick={handleSyncPayslips}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0B57D0] hover:bg-[#0A58CA] text-xs font-semibold text-white shadow-xs transition"
                            >
                                <RefreshCw className="w-4 h-4" />
                                Sync ke Slip Gaji
                            </button>
                        )}
                    </div>
                </div>

                {/* Filter and Overview Card */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-xs p-5">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        {/* Period & Search Filters */}
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="w-64">
                                <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                    Periode Payroll
                                </label>
                                <select
                                    value={selectedPeriodId}
                                    onChange={(e) => handlePeriodChange(e.target.value)}
                                    className="w-full text-xs font-medium rounded-xl border-gray-300 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                                >
                                    {periods.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.period_code} ({p.formatted_start_date} – {p.formatted_end_date})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <form onSubmit={handleSearch} className="w-60">
                                <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                    Cari Petugas Security
                                </label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Nama / NIK Security..."
                                        className="w-full text-xs font-medium rounded-xl border-gray-300 pl-8 pr-3 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                                    />
                                    <Search className="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" />
                                </div>
                            </form>
                        </div>

                        {/* Status Period Badge & Summary Metrics */}
                        <div className="flex flex-wrap items-center gap-4 lg:border-l lg:border-gray-100 lg:pl-6">
                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Status Periode
                                </span>
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold mt-0.5 ${
                                    currentPeriod?.status === 'finalized'
                                        ? 'bg-purple-100 text-purple-800'
                                        : currentPeriod?.status === 'approved'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-blue-50 text-blue-700'
                                }`}>
                                    <span className="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {currentPeriod?.status_label || 'Draft'}
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Total Security
                                </span>
                                <span className="text-base font-bold text-gray-900">
                                    {totalEmployees} orang
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Total Biaya Lembur
                                </span>
                                <span className="text-base font-bold text-emerald-700 font-mono">
                                    {formatRupiah(totalOvertimeAllSecurity)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Legend Guide for Daily Attendance Cells */}
                <div className="flex flex-wrap items-center justify-between text-xs bg-slate-50 border border-slate-200/60 rounded-xl px-4 py-2.5 text-gray-600">
                    <div className="flex flex-wrap items-center gap-4">
                        <span className="font-semibold text-gray-700 flex items-center gap-1.5">
                            <Info className="w-3.5 h-3.5 text-blue-600" />
                            Petunjuk Nilai Harian:
                        </span>
                        <div className="flex items-center gap-1.5">
                            <span className="inline-block px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold font-mono text-[11px]">8</span>
                            <span>Hadir 8 Jam</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="inline-block px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 font-bold font-mono text-[11px]">16</span>
                            <span>Double Shift (8h Reguler + 8h Lembur)</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="inline-block px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800 font-bold text-[11px]">OFF</span>
                            <span>Libur Jadwal (Bukan Absen)</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="inline-block px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 font-bold text-[11px]">A</span>
                            <span>Tidak Hadir / Absen</span>
                        </div>
                    </div>
                    {canManage && currentPeriod?.is_modifiable && (
                        <span className="text-slate-500 italic mt-1 sm:mt-0">
                            * Klik cell tanggal untuk mengubah absensi harian
                        </span>
                    )}
                </div>

                {/* Main Dynamic Matrix Table */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto max-w-full">
                        <table className="w-full text-left text-xs text-gray-600 border-collapse">
                            {/* Table Header */}
                            <thead className="bg-slate-100 border-b border-gray-200 text-gray-700 select-none">
                                <tr>
                                    {/* Sticky Left Columns */}
                                    <th className="sticky left-0 z-20 bg-slate-100 px-3 py-3 font-bold text-center border-r border-gray-200 w-10">
                                        No.
                                    </th>
                                    <th className="sticky left-10 z-20 bg-slate-100 px-4 py-3 font-bold border-r border-gray-200 min-w-[170px] shadow-sm">
                                        Nama Security
                                    </th>

                                    {/* Dynamic Date Columns */}
                                    {periodDates.map((d) => (
                                        <th
                                            key={d.date}
                                            className={`px-2 py-2 text-center border-r border-gray-200 min-w-[42px] ${
                                                d.is_sunday ? 'bg-rose-50/80 text-rose-700' : ''
                                            }`}
                                            title={d.full_date}
                                        >
                                            <div className="text-[10px] font-semibold uppercase">{d.day_name}</div>
                                            <div className="text-[11px] font-mono font-bold">{d.formatted_date}</div>
                                        </th>
                                    ))}

                                    {/* Summary Columns */}
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-slate-200/80 font-bold min-w-[70px]" title="Jumlah Hari dalam Periode">
                                        Hari
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-slate-200/80 font-bold min-w-[75px]" title="Total Jam Kerja">
                                        Jam Kerja
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-indigo-50 text-indigo-900 font-bold min-w-[65px]" title="Hari OFF (Jadwal Libur)">
                                        OFF
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-rose-50 text-rose-900 font-bold min-w-[65px]" title="Ketidakhadiran (Absen)">
                                        Absen
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-amber-50 text-amber-900 font-bold min-w-[80px]" title="Total Jam Lembur Awal">
                                        Lembur
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-slate-100 font-bold min-w-[75px]" title="Potongan Jam Istirahat">
                                        Pot. Ist.
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-amber-100/70 text-amber-900 font-bold min-w-[85px]" title="Jam Lembur Bersih (Lembur - Potongan)">
                                        Lembur Bersih
                                    </th>
                                    <th className="px-3 py-3 text-right border-r border-gray-200 bg-slate-50 font-bold min-w-[90px]" title="Upah per Jam">
                                        Upah/Jam
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-blue-50 text-blue-900 font-bold min-w-[80px]" title="Jam Pertama">
                                        Jam 1
                                    </th>
                                    <th className="px-3 py-3 text-right border-r border-gray-200 bg-blue-50 text-blue-900 font-bold min-w-[105px]" title="Biaya Jam Pertama">
                                        Biaya Jam 1
                                    </th>
                                    <th className="px-3 py-3 text-center border-r border-gray-200 bg-indigo-50 text-indigo-900 font-bold min-w-[80px]" title="Jam Berikutnya">
                                        Jam Lanjut
                                    </th>
                                    <th className="px-3 py-3 text-right border-r border-gray-200 bg-indigo-50 text-indigo-900 font-bold min-w-[110px]" title="Biaya Jam Berikutnya">
                                        Biaya Jam Lanjut
                                    </th>
                                    <th className="px-3 py-3 text-right border-r border-gray-200 bg-amber-200/60 text-amber-950 font-bold min-w-[125px]" title="Total Biaya Lembur Security">
                                        Total Lembur
                                    </th>
                                    <th className="px-3 py-3 text-right bg-emerald-100/70 text-emerald-950 font-bold min-w-[130px]" title="Total Upah Periode (Pokok + Tunjangan + Lembur)">
                                        Total Upah
                                    </th>
                                </tr>
                            </thead>

                            {/* Table Body */}
                            <tbody className="divide-y divide-gray-200">
                                {securityEmployees.length > 0 ? (
                                    securityEmployees.map((emp, index) => {
                                        const summary = summaries[emp.id] || {};
                                        return (
                                            <tr key={emp.id} className="hover:bg-slate-50/70 transition-colors">
                                                {/* Sticky No & Name */}
                                                <td className="sticky left-0 z-10 bg-white px-3 py-2.5 text-center font-mono border-r border-gray-200">
                                                    {index + 1}
                                                </td>
                                                <td className="sticky left-10 z-10 bg-white px-4 py-2.5 border-r border-gray-200 shadow-sm">
                                                    <div className="font-semibold text-gray-900 truncate">
                                                        {emp.full_name}
                                                    </div>
                                                    <div className="text-[10px] text-gray-500 font-mono">
                                                        {emp.employee_number}
                                                    </div>
                                                </td>

                                                {/* Dynamic Daily Attendance Cells */}
                                                {periodDates.map((d) => {
                                                    const key = `${emp.id}_${d.date}`;
                                                    const att = attendances[key];
                                                    const status = att ? att.status : 'present';
                                                    const work = att ? Number(att.work_hours) : 8;
                                                    const ot = att ? Number(att.overtime_hours) : 0;

                                                    let badgeClass = 'text-gray-700 bg-slate-50';
                                                    let displayVal = work;

                                                    if (status === 'off') {
                                                        badgeClass = 'bg-indigo-100 text-indigo-700 font-bold';
                                                        displayVal = 'OFF';
                                                    } else if (status === 'absent') {
                                                        badgeClass = 'bg-rose-100 text-rose-700 font-bold';
                                                        displayVal = 'A';
                                                    } else if (work === 8 && ot === 8) {
                                                        badgeClass = 'bg-amber-100 text-amber-900 font-bold';
                                                        displayVal = '16';
                                                    } else if (ot > 0) {
                                                        badgeClass = 'bg-amber-50 text-amber-800 font-medium';
                                                        displayVal = `${work + ot}`;
                                                    }

                                                    return (
                                                        <td
                                                            key={d.date}
                                                            onClick={() => openCellEditor(emp, d)}
                                                            className={`px-1 py-1 text-center border-r border-gray-200 cursor-pointer select-none transition ${
                                                                d.is_sunday ? 'bg-rose-50/30' : ''
                                                            } ${canManage && currentPeriod?.is_modifiable ? 'hover:bg-blue-100/50' : ''}`}
                                                            title={`${emp.full_name} (${d.full_date}): ${status === 'off' ? 'OFF' : status === 'absent' ? 'Absen' : `Kerja ${work}h, Lembur ${ot}h`}`}
                                                        >
                                                            <div className={`py-1 px-1 rounded text-[11px] font-mono ${badgeClass}`}>
                                                                {displayVal}
                                                            </div>
                                                        </td>
                                                    );
                                                })}

                                                {/* Summary Columns */}
                                                <td className="px-3 py-2.5 text-center font-mono border-r border-gray-200 bg-slate-50">
                                                    {summary.total_days || periodDates.length}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono border-r border-gray-200 bg-slate-50">
                                                    {formatNumber(summary.total_work_hours)}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono font-bold text-indigo-700 border-r border-gray-200 bg-indigo-50/50">
                                                    {summary.total_off_days || 0}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono font-bold text-rose-700 border-r border-gray-200 bg-rose-50/50">
                                                    {summary.total_absent_days || 0}
                                                </td>
                                                <td
                                                    onClick={() => openSummaryEditor(emp)}
                                                    className={`px-3 py-2.5 text-center font-mono font-bold text-amber-800 border-r border-gray-200 bg-amber-50/50 ${canManage && currentPeriod?.is_modifiable ? 'cursor-pointer hover:bg-amber-100' : ''}`}
                                                    title="Klik untuk ubah lembur awal"
                                                >
                                                    {formatNumber(summary.gross_overtime_hours)}
                                                </td>
                                                <td
                                                    onClick={() => openSummaryEditor(emp)}
                                                    className={`px-3 py-2.5 text-center font-mono text-gray-700 border-r border-gray-200 ${canManage && currentPeriod?.is_modifiable ? 'cursor-pointer hover:bg-slate-100' : ''}`}
                                                    title="Klik untuk ubah potongan istirahat"
                                                >
                                                    {formatNumber(summary.break_deduction_hours)}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono font-bold text-amber-900 border-r border-gray-200 bg-amber-100/40">
                                                    {formatNumber(summary.net_overtime_hours)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-mono border-r border-gray-200 bg-slate-50">
                                                    {formatNumber(summary.hourly_rate)}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono border-r border-gray-200 bg-blue-50/40 text-blue-900">
                                                    {formatNumber(summary.first_hours)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-mono border-r border-gray-200 bg-blue-50/40 text-blue-900">
                                                    {formatNumber(summary.first_hours_cost)}
                                                </td>
                                                <td className="px-3 py-2.5 text-center font-mono border-r border-gray-200 bg-indigo-50/40 text-indigo-900">
                                                    {formatNumber(summary.subsequent_hours)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-mono border-r border-gray-200 bg-indigo-50/40 text-indigo-900">
                                                    {formatNumber(summary.subsequent_hours_cost)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-mono font-bold border-r border-gray-200 bg-amber-100/70 text-amber-950">
                                                    {formatRupiah(summary.total_overtime_cost)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-mono font-bold bg-emerald-50 text-emerald-950">
                                                    {formatRupiah(summary.total_period_salary)}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={periodDates.length + 16}
                                            className="px-6 py-12 text-center text-gray-500"
                                        >
                                            <ShieldAlert className="w-8 h-8 text-amber-500 mx-auto mb-2" />
                                            <p className="font-semibold text-gray-700">Belum ada karyawan Security yang ditemukan.</p>
                                            <p className="text-xs text-gray-400 mt-1">
                                                Pastikan terdapat Jabatan dengan Tipe Payroll "Security" pada master jabatan atau atur tipe payroll Security pada master data karyawan.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>

                            {/* Table Footer: TOTAL LEMBUR SECURITY */}
                            {securityEmployees.length > 0 && (
                                <tfoot className="border-t-2 border-gray-300 font-bold select-none">
                                    <tr className="bg-amber-100/90 text-amber-950">
                                        <td
                                            colSpan={periodDates.length + 14}
                                            className="px-4 py-3 text-right uppercase tracking-wider text-xs border-r border-amber-300"
                                        >
                                            TOTAL LEMBUR SECURITY ({securityEmployees.length} Petugas)
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-sm font-black border-r border-amber-300 bg-amber-200 text-amber-950">
                                            {formatRupiah(totalOvertimeAllSecurity)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-sm font-bold bg-emerald-100 text-emerald-950">
                                            {formatRupiah(
                                                Object.values(summaries).reduce((acc, curr) => acc + Number(curr.total_period_salary || 0), 0)
                                            )}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal: Quick Edit Absensi Harian */}
            <Modal show={attendanceModalOpen} onClose={() => setAttendanceModalOpen(false)} maxWidth="md">
                <form onSubmit={handleSaveAttendance} className="p-6">
                    <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <Clock className="w-5 h-5 text-blue-600" />
                            <h3 className="font-bold text-gray-900 text-sm">
                                Edit Absensi: {selectedCell?.employee?.full_name}
                            </h3>
                        </div>
                        <button
                            type="button"
                            onClick={() => setAttendanceModalOpen(false)}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="py-4 space-y-4">
                        <div className="bg-slate-50 rounded-xl p-3 text-xs flex justify-between items-center">
                            <div>
                                <span className="text-gray-500">Tanggal: </span>
                                <span className="font-bold text-gray-800">{selectedCell?.dateObj?.full_date}</span>
                            </div>
                            <div>
                                <span className="text-gray-500">NIK: </span>
                                <span className="font-mono font-semibold text-gray-800">{selectedCell?.employee?.employee_number}</span>
                            </div>
                        </div>

                        {/* Quick Presets */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                                Preset Cepat
                            </label>
                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <button
                                    type="button"
                                    onClick={() => applyCellPreset('present', 8, 0)}
                                    className={`py-2 px-3 rounded-xl border text-left font-medium transition ${
                                        attendanceForm.data.status === 'present' && attendanceForm.data.work_hours == 8 && attendanceForm.data.overtime_hours == 0
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800 font-bold'
                                            : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    8 Jam (Hadir Normal)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => applyCellPreset('present', 8, 8)}
                                    className={`py-2 px-3 rounded-xl border text-left font-medium transition ${
                                        attendanceForm.data.status === 'present' && attendanceForm.data.work_hours == 8 && attendanceForm.data.overtime_hours == 8
                                            ? 'border-amber-500 bg-amber-50 text-amber-900 font-bold'
                                            : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    16 Jam (Double Shift)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => applyCellPreset('off', 0, 0)}
                                    className={`py-2 px-3 rounded-xl border text-left font-medium transition ${
                                        attendanceForm.data.status === 'off'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 font-bold'
                                            : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    OFF (Libur Jadwal)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => applyCellPreset('absent', 0, 0)}
                                    className={`py-2 px-3 rounded-xl border text-left font-medium transition ${
                                        attendanceForm.data.status === 'absent'
                                            ? 'border-rose-500 bg-rose-50 text-rose-800 font-bold'
                                            : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    Absen (Tidak Hadir)
                                </button>
                            </div>
                        </div>

                        {/* Custom Inputs */}
                        <div className="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <InputLabel htmlFor="work_hours" value="Jam Kerja *" />
                                <TextInput
                                    id="work_hours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="24"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={attendanceForm.data.work_hours}
                                    onChange={(e) => attendanceForm.setData('work_hours', e.target.value)}
                                    required
                                />
                                <InputError message={attendanceForm.errors.work_hours} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="overtime_hours" value="Jam Lembur *" />
                                <TextInput
                                    id="overtime_hours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="24"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={attendanceForm.data.overtime_hours}
                                    onChange={(e) => attendanceForm.setData('overtime_hours', e.target.value)}
                                    required
                                />
                                <InputError message={attendanceForm.errors.overtime_hours} className="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="note" value="Catatan Tambahan (Opsional)" />
                            <TextInput
                                id="note"
                                type="text"
                                className="mt-1 block w-full text-xs"
                                value={attendanceForm.data.note}
                                onChange={(e) => attendanceForm.setData('note', e.target.value)}
                                placeholder="Contoh: Menggantikan shift pagi"
                            />
                            <InputError message={attendanceForm.errors.note} className="mt-1" />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <SecondaryButton type="button" onClick={() => setAttendanceModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={attendanceForm.processing}>
                            Simpan Absensi
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Modal: Edit Rekap Lembur & Potongan Istirahat */}
            <Modal show={summaryModalOpen} onClose={() => setSummaryModalOpen(false)} maxWidth="sm">
                <form onSubmit={handleSaveSummary} className="p-6">
                    <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <Edit3 className="w-5 h-5 text-amber-600" />
                            <h3 className="font-bold text-gray-900 text-sm">
                                Koreksi Rekap Lembur
                            </h3>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSummaryModalOpen(false)}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="py-4 space-y-4">
                        <div className="bg-amber-50 rounded-xl p-3 text-xs text-amber-900">
                            Petugas: <strong>{selectedSummaryEmployee?.full_name}</strong>
                        </div>

                        <div>
                            <InputLabel htmlFor="gross_overtime_hours" value="Total Jam Lembur Awal" />
                            <TextInput
                                id="gross_overtime_hours"
                                type="number"
                                step="0.5"
                                min="0"
                                className="mt-1 block w-full text-xs font-mono"
                                value={summaryForm.data.gross_overtime_hours}
                                onChange={(e) => summaryForm.setData('gross_overtime_hours', e.target.value)}
                            />
                            <p className="text-[11px] text-gray-500 mt-1">
                                Total jam lembur sebelum potongan istirahat.
                            </p>
                        </div>

                        <div>
                            <InputLabel htmlFor="break_deduction_hours" value="Potongan Jam Istirahat" />
                            <TextInput
                                id="break_deduction_hours"
                                type="number"
                                step="0.5"
                                min="0"
                                className="mt-1 block w-full text-xs font-mono"
                                value={summaryForm.data.break_deduction_hours}
                                onChange={(e) => summaryForm.setData('break_deduction_hours', e.target.value)}
                            />
                            <p className="text-[11px] text-gray-500 mt-1">
                                Jumlah jam istirahat yang dipotongkan dari total jam lembur.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <SecondaryButton type="button" onClick={() => setSummaryModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={summaryForm.processing}>
                            Simpan Koreksi
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Modal: Pengaturan Rumus Lembur Security */}
            <Modal show={configModalOpen} onClose={() => setConfigModalOpen(false)} maxWidth="lg">
                <form onSubmit={handleSaveConfig} className="p-6">
                    <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <Settings className="w-5 h-5 text-gray-700" />
                            <div>
                                <h3 className="font-bold text-gray-900 text-sm">
                                    Konfigurasi Rumus Lembur Security
                                </h3>
                                <p className="text-[11px] text-gray-500">
                                    Versi aktif: v{activeConfig?.version || 1}. Nilai divalidasi dan dihitung dengan presisi desimal.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setConfigModalOpen(false)}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="py-4 space-y-4 text-xs">
                        {/* Live Calculation Preview Box */}
                        <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-3.5 space-y-2">
                            <div className="flex items-center gap-2 font-bold text-blue-900">
                                <HelpCircle className="w-4 h-4 text-blue-600" />
                                <span>Simulasi / Preview Formula (Fixture Excel)</span>
                            </div>
                            <div className="grid grid-cols-2 gap-2 text-[11px] text-blue-950 font-mono">
                                <div>• Lembur Awal: {previewGross} jam</div>
                                <div>• Potongan Istirahat: {previewDeduction} jam</div>
                                <div>• Lembur Bersih: {previewNet} jam</div>
                                <div>• Upah/Jam: {formatRupiah(previewRate)}</div>
                                <div>• Jam Pertama: {previewFirst} jam (×{previewMultFirst}) = {formatRupiah(previewFirstCost)}</div>
                                <div>• Jam Lanjut: {previewSubsequent} jam (×{previewMultSubsequent}) = {formatRupiah(previewSubsequentCost)}</div>
                            </div>
                            <div className="pt-1 border-t border-blue-200 flex justify-between items-center font-bold text-blue-950 text-xs">
                                <span>Total Lembur per Security:</span>
                                <span className="font-mono text-sm">{formatRupiah(previewTotalCost)}</span>
                            </div>
                            <div className="flex justify-between items-center text-[11px] text-blue-800">
                                <span>Estimasi 6 Security identik:</span>
                                <span className="font-mono font-bold">{formatRupiah(previewTotalCost * 6)}</span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="hourly_rate" value="Upah Per Jam (Rp) *" />
                                <TextInput
                                    id="hourly_rate"
                                    type="number"
                                    min="0"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.hourly_rate}
                                    onChange={(e) => configForm.setData('hourly_rate', e.target.value)}
                                    required
                                />
                                <InputError message={configForm.errors.hourly_rate} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="first_hours_limit" value="Batas Jam Pertama (Jam) *" />
                                <TextInput
                                    id="first_hours_limit"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="50"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.first_hours_limit}
                                    onChange={(e) => configForm.setData('first_hours_limit', e.target.value)}
                                    required
                                />
                                <InputError message={configForm.errors.first_hours_limit} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="first_hours_multiplier" value="Pengali Jam Pertama *" />
                                <TextInput
                                    id="first_hours_multiplier"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="10"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.first_hours_multiplier}
                                    onChange={(e) => configForm.setData('first_hours_multiplier', e.target.value)}
                                    required
                                />
                                <InputError message={configForm.errors.first_hours_multiplier} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="subsequent_hours_multiplier" value="Pengali Jam Berikutnya *" />
                                <TextInput
                                    id="subsequent_hours_multiplier"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="10"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.subsequent_hours_multiplier}
                                    onChange={(e) => configForm.setData('subsequent_hours_multiplier', e.target.value)}
                                    required
                                />
                                <InputError message={configForm.errors.subsequent_hours_multiplier} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="break_deduction_default" value="Default Potongan Istirahat (Jam)" />
                                <TextInput
                                    id="break_deduction_default"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="50"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.break_deduction_default}
                                    onChange={(e) => configForm.setData('break_deduction_default', e.target.value)}
                                />
                                <InputError message={configForm.errors.break_deduction_default} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="max_break_deduction" value="Maksimum Potongan Istirahat (Jam)" />
                                <TextInput
                                    id="max_break_deduction"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="100"
                                    className="mt-1 block w-full text-xs font-mono"
                                    value={configForm.data.max_break_deduction}
                                    onChange={(e) => configForm.setData('max_break_deduction', e.target.value)}
                                />
                                <InputError message={configForm.errors.max_break_deduction} className="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="round_rule" value="Aturan Pembulatan Nominal *" />
                            <select
                                id="round_rule"
                                value={configForm.data.round_rule}
                                onChange={(e) => configForm.setData('round_rule', e.target.value)}
                                className="mt-1 block w-full text-xs rounded-xl border-gray-300 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                            >
                                <option value="round">Bulatkan Terdekat (Standard Round)</option>
                                <option value="floor">Bulatkan Ke Bawah (Floor)</option>
                                <option value="ceil">Bulatkan Ke Atas (Ceil)</option>
                            </select>
                            <InputError message={configForm.errors.round_rule} className="mt-1" />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <SecondaryButton type="button" onClick={() => setConfigModalOpen(false)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={configForm.processing}>
                            Simpan Perubahan Rumus
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
