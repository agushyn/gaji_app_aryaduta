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
    AlertTriangle,
    BarChart3,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Eye,
    FileSpreadsheet,
    HelpCircle,
    Receipt,
    RefreshCw,
    Save,
    Search,
    Settings,
    Shield,
    ShieldAlert,
    ShieldCheck,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function SecurityPayrollIndex({
    periods = [],
    currentPeriod,
    securityEmployees = [],
    summaries = {},
    activeConfig,
    totalOvertimeAllSecurity = 0,
    totalEmployees = 0,
    canManage = false,
    canConfig = false,
    filters = {},
}) {
    const { flash, errors } = usePage().props;

    const [search, setSearch] = useState(filters?.search || '');
    const [selectedPeriodId, setSelectedPeriodId] = useState(currentPeriod?.id || '');

    // Modal state for Formula Configuration
    const [configModalOpen, setConfigModalOpen] = useState(false);

    // Modal state for Calculation Breakdown & Preview
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState(null);

    // Per-row edit state for simplified input
    const [rows, setRows] = useState({});
    const [savingId, setSavingId] = useState(null);
    const [bulkSaving, setBulkSaving] = useState(false);
    const dirtyRef = useRef({});

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

    const periodDays = currentPeriod?.total_days || (
        currentPeriod?.start_date && currentPeriod?.end_date
            ? Math.round((new Date(currentPeriod.end_date) - new Date(currentPeriod.start_date)) / (1000 * 60 * 60 * 24)) + 1
            : 30
    );

    // Seed row inputs from server summaries; keep unsaved (dirty) edits intact.
    useEffect(() => {
        setRows((prev) => {
            const map = {};
            securityEmployees.forEach((emp) => {
                if (dirtyRef.current[emp.id]) {
                    map[emp.id] = prev[emp.id];
                    return;
                }
                const s = summaries[emp.id] || {};
                map[emp.id] = {
                    present_days: s.present_days ?? s.total_work_days ?? currentPeriod?.working_days ?? '',
                    off_days: s.off_days ?? s.total_off_days ?? 0,
                    sick_days: s.sick_days ?? 0,
                    permission_days: s.permission_days ?? 0,
                    leave_days: s.leave_days ?? 0,
                    absent_days: s.absent_days ?? s.total_absent_days ?? 0,
                    regular_overtime_days: s.regular_overtime_days ?? 0,
                    regular_overtime_hours: s.regular_overtime_hours ?? s.gross_overtime_hours ?? 0,
                    off_holiday_overtime_days: s.off_holiday_overtime_days ?? 0,
                    off_holiday_overtime_hours: s.off_holiday_overtime_hours ?? 0,
                };
            });
            return map;
        });
    }, [summaries, currentPeriod?.id, securityEmployees]);

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

    const setRowValue = (empId, field, value) => {
        dirtyRef.current[empId] = true;
        setRows((prev) => ({
            ...prev,
            [empId]: {
                ...prev[empId],
                [field]: value,
            },
        }));
    };

    // Save one row: attendance + regular & holiday overtime.
    const handleSaveRow = (e, emp) => {
        e.preventDefault();
        const isPeriodModifiable = Boolean(
            currentPeriod?.is_modifiable ?? (
                currentPeriod?.status === 'draft' || currentPeriod?.status === 'changes_requested'
            )
        );
        if (!canManage || !isPeriodModifiable) return;

        const row = rows[emp.id] || {};
        setSavingId(emp.id);

        router.post(
            route('payroll-security.summary.update'),
            {
                payroll_period_id: currentPeriod.id,
                employee_id: emp.id,
                present_days: row.present_days === '' ? 0 : Number(row.present_days),
                off_days: Number(row.off_days || 0),
                sick_days: Number(row.sick_days || 0),
                permission_days: Number(row.permission_days || 0),
                leave_days: Number(row.leave_days || 0),
                absent_days: Number(row.absent_days || 0),
                regular_overtime_days: Number(row.regular_overtime_days || 0),
                regular_overtime_hours: Number(row.regular_overtime_hours || 0),
                off_holiday_overtime_days: Number(row.off_holiday_overtime_days || 0),
                off_holiday_overtime_hours: Number(row.off_holiday_overtime_hours || 0),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    dirtyRef.current[emp.id] = false;
                    setSavingId(null);
                },
                onError: () => setSavingId(null),
            }
        );
    };

    // Bulk save all security rows in one transaction
    const handleBulkSave = () => {
        const isPeriodModifiable = Boolean(
            currentPeriod?.is_modifiable ?? (
                currentPeriod?.status === 'draft' || currentPeriod?.status === 'changes_requested'
            )
        );
        if (!canManage || !isPeriodModifiable || bulkSaving) return;

        const items = securityEmployees.map((emp) => {
            const row = rows[emp.id] || {};
            return {
                employee_id: emp.id,
                present_days: row.present_days === '' ? 0 : Number(row.present_days),
                off_days: Number(row.off_days || 0),
                sick_days: Number(row.sick_days || 0),
                permission_days: Number(row.permission_days || 0),
                leave_days: Number(row.leave_days || 0),
                absent_days: Number(row.absent_days || 0),
                regular_overtime_days: Number(row.regular_overtime_days || 0),
                regular_overtime_hours: Number(row.regular_overtime_hours || 0),
                off_holiday_overtime_days: Number(row.off_holiday_overtime_days || 0),
                off_holiday_overtime_hours: Number(row.off_holiday_overtime_hours || 0),
            };
        });

        setBulkSaving(true);
        router.post(
            route('payroll-security.bulk-update'),
            {
                payroll_period_id: currentPeriod.id,
                items,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    dirtyRef.current = {};
                    setBulkSaving(false);
                },
                onError: () => setBulkSaving(false),
            }
        );
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

    // Live preview calculations for formula config modal
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

    const isPeriodModifiable = Boolean(
        currentPeriod?.is_modifiable ?? (
            currentPeriod?.status === 'draft' || currentPeriod?.status === 'changes_requested'
        )
    );
    const canEdit = canManage && isPeriodModifiable;
    const dirtyCount = Object.keys(dirtyRef.current).filter((k) => dirtyRef.current[k]).length;

    return (
        <AuthenticatedLayout
            title="Payroll Security"
            header="Payroll Security"
            fullWidth={true}
        >
            <Head title="Payroll Security" />

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
                                    Payroll Security
                                </h1>
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                    Input Sederhana & Otomatis
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Input rekap kehadiran dan lembur karyawan Security. Jam aktual kehadiran dihitung otomatis (8 jam/hari).
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {currentPeriod && (
                            <Link
                                href={route('payroll-periods.show', currentPeriod.id)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Receipt className="w-4 h-4 text-slate-500" />
                                Periode & Slip Gaji
                            </Link>
                        )}

                        {currentPeriod && (
                            <Link
                                href={route('payroll-security.reports.index', { period_id: currentPeriod.id })}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-xs font-semibold text-indigo-700 shadow-xs transition"
                            >
                                <BarChart3 className="w-4 h-4 text-indigo-600" />
                                Laporan & Rekonsiliasi
                            </Link>
                        )}

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

                        {canEdit && (
                            <>
                                <button
                                    type="button"
                                    onClick={handleSyncPayslips}
                                    className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-100 text-xs font-semibold text-blue-700 shadow-xs transition"
                                >
                                    <RefreshCw className="w-4 h-4" />
                                    Sync ke Slip Gaji
                                </button>

                                <button
                                    type="button"
                                    onClick={handleBulkSave}
                                    disabled={bulkSaving || securityEmployees.length === 0}
                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0B57D0] hover:bg-[#0A58CA] text-xs font-semibold text-white shadow-xs transition disabled:opacity-50"
                                >
                                    {bulkSaving ? (
                                        <RefreshCw className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <Save className="w-4 h-4" />
                                    )}
                                    <span>Simpan Semua Data</span>
                                    {dirtyCount > 0 && (
                                        <span className="ml-1 px-1.5 py-0.2 bg-white/20 rounded-full text-[10px]">
                                            {dirtyCount} diubah
                                        </span>
                                    )}
                                </button>
                            </>
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
                                        : currentPeriod?.status === 'changes_requested'
                                        ? 'bg-rose-100 text-rose-800'
                                        : currentPeriod?.status === 'in_review'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-blue-100 text-blue-800'
                                }`}>
                                    <span className="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {currentPeriod?.status_label || currentPeriod?.status || 'Draft'}
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Hari Kalender Periode
                                </span>
                                <span className="text-base font-bold text-gray-900 font-mono">
                                    {periodDays} Hari
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Total Petugas
                                </span>
                                <span className="text-base font-bold text-gray-900 font-mono">
                                    {totalEmployees} Orang
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-gray-400 uppercase tracking-wider">
                                    Total Lembur Seluruh Security
                                </span>
                                <span className="text-base font-extrabold text-amber-600 font-mono">
                                    {formatRupiah(totalOvertimeAllSecurity)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Validation Errors Notice */}
                {Object.keys(errors || {}).length > 0 && (
                    <div className="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-3 text-xs flex items-start gap-2">
                        <AlertCircle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Terdapat kesalahan pada input rekap:</p>
                            <ul className="list-disc list-inside mt-1 space-y-0.5">
                                {Object.values(errors).slice(0, 3).map((err, idx) => (
                                    <li key={idx}>{err}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Main Table: Mass Input Table */}
                <div className="bg-white rounded-2xl border border-gray-200/80 shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left border-collapse">
                            {/* Visual Grouped Headers */}
                            <thead>
                                <tr className="border-b border-gray-300 text-slate-800 text-[11px] uppercase tracking-wider font-extrabold text-center select-none">
                                    <th colSpan={2} className="px-3 py-2 bg-slate-200/80 border-r border-gray-300 text-slate-700">
                                        Identitas Karyawan
                                    </th>
                                    <th colSpan={7} className="px-3 py-2 bg-blue-100/80 border-r border-blue-300 text-blue-900">
                                        Kehadiran & Ketidakhadiran (Shift Standar 8 Jam)
                                    </th>
                                    <th colSpan={2} className="px-3 py-2 bg-amber-100/80 border-r border-amber-300 text-amber-900">
                                        Lembur Hari Kerja Biasa
                                    </th>
                                    <th colSpan={2} className="px-3 py-2 bg-purple-100/80 border-r border-purple-300 text-purple-900">
                                        Lembur Hari OFF / Libur
                                    </th>
                                    <th colSpan={5} className="px-3 py-2 bg-emerald-100/70 text-emerald-950">
                                        Hasil Kalkulasi & Aksi
                                    </th>
                                </tr>
                                <tr className="bg-slate-100 border-b border-gray-300 text-gray-700 text-[11px] font-bold select-none text-center">
                                    {/* Identitas */}
                                    <th className="px-2 py-2.5 border-r border-gray-200 w-8">No</th>
                                    <th className="px-3 py-2.5 text-left border-r border-gray-300 min-w-[150px]">Nama Security</th>

                                    {/* Kehadiran */}
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50 text-blue-950 w-16" title="Hari Masuk Kerja">Masuk</th>
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50 text-blue-950 w-14" title="Hari OFF Rutin">OFF</th>
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50/70 text-blue-950 w-14" title="Hari Sakit">Sakit</th>
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50/70 text-blue-950 w-14" title="Hari Izin">Izin</th>
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50/70 text-blue-950 w-14" title="Hari Cuti">Cuti</th>
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-blue-50/70 text-blue-950 w-14" title="Hari Alfa">Alfa</th>
                                    <th className="px-2 py-2.5 border-r border-blue-300 bg-blue-100 text-blue-950 min-w-[75px]" title="Jam Aktual = Masuk * 8 Jam">Jam Hadir</th>

                                    {/* Lembur Hari Biasa */}
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-amber-50 text-amber-950 w-14" title="Jumlah Hari Lembur Biasa">Hari</th>
                                    <th className="px-1.5 py-2.5 border-r border-amber-300 bg-amber-50 text-amber-950 w-16" title="Total Jam Lembur Biasa">Jam</th>

                                    {/* Lembur OFF/Libur */}
                                    <th className="px-1.5 py-2.5 border-r border-gray-200 bg-purple-50 text-purple-950 w-14" title="Jumlah Hari Lembur OFF/Libur">Hari</th>
                                    <th className="px-1.5 py-2.5 border-r border-purple-300 bg-purple-50 text-purple-950 w-16" title="Total Jam Lembur OFF/Libur">Jam</th>

                                    {/* Ringkasan */}
                                    <th className="px-2 py-2.5 border-r border-gray-200 bg-emerald-50 text-emerald-950 min-w-[70px]" title="Jam Lembur Bersih">Bersih</th>
                                    <th className="px-2.5 py-2.5 text-right border-r border-gray-200 bg-emerald-50 text-emerald-950 min-w-[95px]" title="Total Biaya Lembur">Total Lembur</th>
                                    <th className="px-2.5 py-2.5 text-right border-r border-gray-200 bg-emerald-50 text-emerald-950 min-w-[105px]" title="Total Gaji Periode">Total Upah</th>
                                    <th className="px-2 py-2.5 border-r border-gray-200 bg-slate-100 min-w-[85px]">Status</th>
                                    <th className="px-2 py-2.5 bg-slate-100 min-w-[70px]">Aksi</th>
                                </tr>
                            </thead>

                            {/* Table Body */}
                            <tbody className="divide-y divide-gray-200 font-mono text-xs">
                                {securityEmployees.length > 0 ? (
                                    securityEmployees.map((emp, index) => {
                                        const summary = summaries[emp.id] || {};
                                        const row = rows[emp.id] || {
                                            present_days: summary.present_days ?? summary.total_work_days ?? currentPeriod?.working_days ?? '',
                                            off_days: summary.off_days ?? summary.total_off_days ?? 0,
                                            sick_days: summary.sick_days ?? 0,
                                            permission_days: summary.permission_days ?? 0,
                                            leave_days: summary.leave_days ?? 0,
                                            absent_days: summary.absent_days ?? summary.total_absent_days ?? 0,
                                            regular_overtime_days: summary.regular_overtime_days ?? 0,
                                            regular_overtime_hours: summary.regular_overtime_hours ?? summary.gross_overtime_hours ?? 0,
                                            off_holiday_overtime_days: summary.off_holiday_overtime_days ?? 0,
                                            off_holiday_overtime_hours: summary.off_holiday_overtime_hours ?? 0,
                                        };

                                        // Real-time live calculations for this row
                                        const presentDaysNum = row.present_days === '' ? 0 : Number(row.present_days);
                                        const offDaysNum = Number(row.off_days || 0);
                                        const sickDaysNum = Number(row.sick_days || 0);
                                        const permissionDaysNum = Number(row.permission_days || 0);
                                        const leaveDaysNum = Number(row.leave_days || 0);
                                        const absentDaysNum = Number(row.absent_days || 0);

                                        const liveAttendanceHours = presentDaysNum * 8;
                                        const totalRecordedDays = presentDaysNum + offDaysNum + sickDaysNum + permissionDaysNum + leaveDaysNum + absentDaysNum;
                                        const isOverDays = totalRecordedDays > periodDays;
                                        const isComplete = totalRecordedDays === periodDays;

                                        const isSaving = savingId === emp.id;
                                        const isDirty = dirtyRef.current[emp.id];

                                        return (
                                            <tr key={emp.id} className={`hover:bg-slate-50/80 transition-colors ${isOverDays ? 'bg-rose-50/40' : ''}`}>
                                                {/* No */}
                                                <td className="px-2 py-2 text-center text-gray-500 border-r border-gray-200">
                                                    {index + 1}
                                                </td>

                                                {/* Nama & NIK */}
                                                <td className="px-3 py-2 border-r border-gray-300 font-sans">
                                                    <div className="font-semibold text-gray-900 truncate">
                                                        {emp.full_name}
                                                    </div>
                                                    <div className="text-[10px] text-gray-500 font-mono">
                                                        {emp.employee_number}
                                                    </div>
                                                </td>

                                                {/* Hari Masuk (present_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.present_days}
                                                        onChange={(e) => setRowValue(emp.id, 'present_days', e.target.value)}
                                                        className="w-14 text-center text-xs font-mono font-bold text-blue-900 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 disabled:text-gray-400 py-1"
                                                        title="Jumlah hari masuk shift standar 8 jam"
                                                    />
                                                </td>

                                                {/* Hari OFF (off_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.off_days}
                                                        onChange={(e) => setRowValue(emp.id, 'off_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-gray-700 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Hari OFF"
                                                    />
                                                </td>

                                                {/* Hari Sakit (sick_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/20">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.sick_days}
                                                        onChange={(e) => setRowValue(emp.id, 'sick_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-gray-700 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Hari Sakit"
                                                    />
                                                </td>

                                                {/* Hari Izin (permission_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/20">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.permission_days}
                                                        onChange={(e) => setRowValue(emp.id, 'permission_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-gray-700 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Hari Izin"
                                                    />
                                                </td>

                                                {/* Hari Cuti (leave_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/20">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.leave_days}
                                                        onChange={(e) => setRowValue(emp.id, 'leave_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-gray-700 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Hari Cuti"
                                                    />
                                                </td>

                                                {/* Hari Alfa (absent_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-blue-50/20">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.absent_days}
                                                        onChange={(e) => setRowValue(emp.id, 'absent_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-rose-700 rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Hari Alfa / Tanpa Keterangan"
                                                    />
                                                </td>

                                                {/* Jam Masuk Aktual (present_days * 8) */}
                                                <td className="px-1.5 py-1 text-center border-r border-blue-300 bg-blue-100/60 font-bold text-blue-900" title="Jam Aktual = Hari Masuk * 8 Jam">
                                                    <div>{liveAttendanceHours}j</div>
                                                    <div className="text-[10px] text-blue-700/80 font-normal">
                                                        Tgt: {summary.target_hours ? `${summary.target_hours}j` : `${liveAttendanceHours}j`}
                                                    </div>
                                                    {Number(summary.total_shortage_hours || 0) > 0 && (
                                                        <span className="inline-block px-1 py-0.2 rounded bg-rose-100 text-rose-800 text-[9px] font-bold mt-0.5" title={`Total kekurangan jam: ${summary.total_shortage_hours} jam`}>
                                                            -{summary.total_shortage_hours}j
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Lembur Biasa - Hari (regular_overtime_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-amber-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.regular_overtime_days}
                                                        onChange={(e) => setRowValue(emp.id, 'regular_overtime_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-amber-900 font-bold rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Jumlah hari lembur hari biasa"
                                                    />
                                                </td>

                                                {/* Lembur Biasa - Jam (regular_overtime_hours) */}
                                                <td className="p-1 text-center border-r border-amber-300 bg-amber-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.5"
                                                        disabled={!canEdit}
                                                        value={row.regular_overtime_hours}
                                                        onChange={(e) => setRowValue(emp.id, 'regular_overtime_hours', e.target.value)}
                                                        className="w-14 text-center text-xs font-mono text-amber-950 font-bold rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Total jam lembur hari biasa"
                                                    />
                                                </td>

                                                {/* Lembur OFF/Libur - Hari (off_holiday_overtime_days) */}
                                                <td className="p-1 text-center border-r border-gray-200 bg-purple-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={periodDays}
                                                        step="1"
                                                        disabled={!canEdit}
                                                        value={row.off_holiday_overtime_days}
                                                        onChange={(e) => setRowValue(emp.id, 'off_holiday_overtime_days', e.target.value)}
                                                        className="w-12 text-center text-xs font-mono text-purple-900 font-bold rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Jumlah hari lembur hari OFF/Libur"
                                                    />
                                                </td>

                                                {/* Lembur OFF/Libur - Jam (off_holiday_overtime_hours) */}
                                                <td className="p-1 text-center border-r border-purple-300 bg-purple-50/30">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.5"
                                                        disabled={!canEdit}
                                                        value={row.off_holiday_overtime_hours}
                                                        onChange={(e) => setRowValue(emp.id, 'off_holiday_overtime_hours', e.target.value)}
                                                        className="w-14 text-center text-xs font-mono text-purple-950 font-bold rounded-lg border-gray-300 shadow-2xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20 disabled:bg-gray-50 py-1"
                                                        title="Total jam lembur hari OFF/Libur"
                                                    />
                                                </td>

                                                {/* Hasil: Lembur Bersih */}
                                                <td className="px-2 py-2 text-center border-r border-gray-200 bg-emerald-50/50 text-emerald-950 font-bold">
                                                    {formatNumber(summary.net_overtime_hours)}j
                                                </td>

                                                {/* Hasil: Total Biaya Lembur */}
                                                <td className="px-2.5 py-2 text-right border-r border-gray-200 bg-emerald-50/60 font-bold text-amber-900">
                                                    {formatRupiah(summary.total_overtime_cost)}
                                                </td>

                                                {/* Hasil: Total Upah Periode */}
                                                <td className="px-2.5 py-2 text-right border-r border-gray-200 bg-emerald-100/60 font-bold text-emerald-950">
                                                    {formatRupiah(summary.total_period_salary)}
                                                </td>

                                                {/* Status Kelengkapan Hari */}
                                                <td className="px-2 py-2 text-center border-r border-gray-200 font-sans">
                                                    {isOverDays ? (
                                                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800" title={`Melebihi: ${totalRecordedDays} dari ${periodDays} hari`}>
                                                            <AlertCircle className="w-3 h-3 text-rose-600" />
                                                            {totalRecordedDays}/{periodDays}
                                                        </span>
                                                    ) : isComplete ? (
                                                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                                            <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                            Lengkap
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                                                            {totalRecordedDays}/{periodDays}h
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Tombol Simpan & Detail Per Baris */}
                                                <td className="px-2 py-1.5 text-center bg-slate-50 font-sans">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setSelectedEmployee(emp);
                                                                setDetailModalOpen(true);
                                                            }}
                                                            className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-semibold bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 shadow-2xs transition"
                                                            title="Lihat rincian kalkulasi lengkap"
                                                        >
                                                            <Eye className="w-3 h-3 text-[#0B57D0]" />
                                                            <span>Detail</span>
                                                        </button>

                                                        {canEdit ? (
                                                            <button
                                                                type="button"
                                                                onClick={(e) => handleSaveRow(e, emp)}
                                                                disabled={isSaving}
                                                                className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold transition ${
                                                                    isSaving
                                                                        ? 'bg-slate-200 text-slate-400 cursor-wait'
                                                                        : isDirty
                                                                        ? 'bg-[#0B57D0] hover:bg-[#0A58CA] text-white shadow-xs'
                                                                        : 'bg-slate-200 hover:bg-slate-300 text-slate-700'
                                                                }`}
                                                                title={isDirty ? 'Ada perubahan belum disimpan' : 'Simpan baris ini'}
                                                            >
                                                                {isSaving ? (
                                                                    <RefreshCw className="w-3 h-3 animate-spin" />
                                                                ) : (
                                                                    <Save className="w-3 h-3" />
                                                                )}
                                                                {isSaving ? '...' : 'Simpan'}
                                                            </button>
                                                        ) : (
                                                            <span className="text-[10px] text-gray-400 italic">
                                                                Terkunci
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={18} className="px-6 py-12 text-center text-gray-500 font-sans">
                                            <ShieldAlert className="w-8 h-8 text-amber-500 mx-auto mb-2" />
                                            <p className="font-semibold text-gray-700">Belum ada karyawan Security yang ditemukan.</p>
                                            <p className="text-xs text-gray-400 mt-1">
                                                Pastikan terdapat Jabatan dengan Tipe Payroll "Security" pada master jabatan atau atur tipe payroll Security pada master data karyawan.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>

                            {/* Table Footer: Totals */}
                            {securityEmployees.length > 0 && (
                                <tfoot className="border-t-2 border-gray-300 font-bold select-none text-xs">
                                    <tr className="bg-amber-100/90 text-amber-950 font-mono">
                                        <td colSpan={14} className="px-4 py-2.5 text-right uppercase tracking-wider text-[11px] font-sans border-r border-amber-300">
                                            TOTAL LEMBUR SECURITY ({securityEmployees.length} Petugas)
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-black border-r border-amber-300 bg-amber-200 text-amber-950">
                                            {formatRupiah(totalOvertimeAllSecurity)}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-bold bg-emerald-100 text-emerald-950 border-r border-gray-300">
                                            {formatRupiah(
                                                Object.values(summaries).reduce((acc, curr) => acc + Number(curr.total_period_salary || 0), 0)
                                            )}
                                        </td>
                                        <td colSpan={2} className="bg-amber-100/90"></td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>

                {/* Input Instructions & Rules */}
                <div className="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs text-slate-700 space-y-2">
                    <div className="font-bold flex items-center gap-2 text-slate-900">
                        <Check className="w-4 h-4 text-emerald-600" />
                        <span>Petunjuk Entri Payroll Security:</span>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px] text-slate-600">
                        <div className="bg-white p-3 rounded-xl border border-slate-200">
                            <span className="font-semibold text-slate-900 block mb-1">1. Kehadiran & Shift</span>
                            Isi jumlah <strong>Hari Masuk</strong>. Jam aktual kehadiran dihitung otomatis <code>Hari Masuk × 8 jam</code> oleh backend. Lengkapi juga hari OFF, Sakit, Izin, Cuti, dan Alfa.
                        </div>
                        <div className="bg-white p-3 rounded-xl border border-slate-200">
                            <span className="font-semibold text-slate-900 block mb-1">2. Pemisahan Lembur</span>
                            Pisahkan antara <strong>Lembur Hari Kerja Biasa</strong> (Hari & Jam) dan <strong>Lembur Hari OFF/Libur</strong> (Hari & Jam). Hari lembur menentukan alokasi jam pertama secara proporsional.
                        </div>
                        <div className="bg-white p-3 rounded-xl border border-slate-200">
                            <span className="font-semibold text-slate-900 block mb-1">3. Penyimpanan Data</span>
                            Gunakan tombol <strong>Simpan</strong> pada masing-masing baris atau klik tombol <strong>Simpan Semua Data</strong> di pojok kanan atas untuk menyimpan seluruh baris sekaligus dalam 1 transaksi.
                        </div>
                    </div>
                </div>
            </div>

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

            {/* Modal: Rincian & Preview Kalkulasi Security */}
            <Modal show={detailModalOpen} onClose={() => setDetailModalOpen(false)} maxWidth="2xl">
                {selectedEmployee && (() => {
                    const emp = selectedEmployee;
                    const s = summaries[emp.id] || {};
                    const snapshot = s.calculation_snapshot || {};
                    const reg = snapshot.regular_overtime || {};
                    const off = snapshot.off_holiday_overtime || {};

                    return (
                        <div className="p-6 space-y-4 font-sans text-xs">
                            {/* Header */}
                            <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
                                        <ShieldCheck className="w-6 h-6" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900 text-sm">
                                            Rincian & Preview Kalkulasi Payroll Security
                                        </h3>
                                        <p className="text-xs text-gray-500">
                                            {emp.full_name} ({emp.employee_number}) • Periode {currentPeriod?.period_code}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setDetailModalOpen(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            {/* Section 1: Attendance & Target Hours */}
                            <div className="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-2">
                                <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                    <Clock className="w-4 h-4 text-blue-600" />
                                    1. Target Jam Kerja vs Jam Aktual Kehadiran
                                </span>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono text-slate-700">
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Hari Masuk</span>
                                        <span className="font-bold text-blue-900 text-xs">{s.present_days || 0} Hari</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Jam Aktual Hadir</span>
                                        <span className="font-bold text-blue-900 text-xs">{s.attendance_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Hari OFF / Absen</span>
                                        <span className="font-bold text-slate-800 text-xs">{s.off_days || 0} OFF / {s.total_absence_days || 0} Absen</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Target Jam Kerja</span>
                                        <span className="font-bold text-emerald-700 text-xs">{s.target_hours || 0} Jam</span>
                                    </div>
                                </div>
                            </div>

                            {/* Section 2: Shortage & Carry-Forward */}
                            <div className="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-2">
                                <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                    <AlertCircle className="w-4 h-4 text-amber-600" />
                                    2. Kekurangan Jam Kerja & Kompensasi Lembur
                                </span>
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 text-[11px] font-mono text-slate-700">
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Kurang Bulan Ini</span>
                                        <span className="font-bold text-amber-900">{s.current_shortage_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Bulan Lalu (Carry)</span>
                                        <span className="font-bold text-purple-900">{s.previous_shortage_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Total Kekurangan</span>
                                        <span className="font-bold text-rose-800">{s.total_shortage_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Kompensasi Lembur</span>
                                        <span className="font-bold text-blue-900">-{s.shortage_offset_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-slate-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Sisa Dibawa Lanjut</span>
                                        <span className="font-bold text-slate-900">{s.remaining_shortage_hours || 0} Jam</span>
                                    </div>
                                </div>
                            </div>

                            {/* Section 3: Regular Overtime Breakdown */}
                            <div className="bg-amber-50/60 border border-amber-200 rounded-xl p-3.5 space-y-2">
                                <div className="flex items-center justify-between font-bold text-xs text-amber-950">
                                    <span className="flex items-center gap-1.5">
                                        <Shield className="w-4 h-4 text-amber-700" />
                                        3. Lembur Hari Kerja Biasa (REGULAR)
                                    </span>
                                    <span className="text-amber-900 font-mono text-sm font-black">
                                        {formatRupiah(s.regular_overtime_cost || 0)}
                                    </span>
                                </div>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono text-amber-950">
                                    <div className="bg-white p-2 rounded-lg border border-amber-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Hari / Jam Lembur</span>
                                        <span>{s.regular_overtime_days || 0} Hari / {s.regular_overtime_hours || 0}j</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-amber-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Pot. Istirahat (1j/8j)</span>
                                        <span className="text-rose-700">-{s.regular_break_deduction_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-amber-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Jam Bersih</span>
                                        <span className="font-bold">{s.regular_net_overtime_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-amber-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Alokasi Jam 1 & Lanjut</span>
                                        <span>J1: {s.regular_first_hours || 0}j | JL: {s.regular_subsequent_hours || 0}j</span>
                                    </div>
                                </div>
                            </div>

                            {/* Section 4: OFF / Holiday Overtime Breakdown */}
                            <div className="bg-purple-50/60 border border-purple-200 rounded-xl p-3.5 space-y-2">
                                <div className="flex items-center justify-between font-bold text-xs text-purple-950">
                                    <span className="flex items-center gap-1.5">
                                        <Calendar className="w-4 h-4 text-purple-700" />
                                        4. Lembur Hari OFF / Libur (OFF_HOLIDAY)
                                    </span>
                                    <span className="text-purple-900 font-mono text-sm font-black">
                                        {formatRupiah(s.off_holiday_overtime_cost || 0)}
                                    </span>
                                </div>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-[11px] font-mono text-purple-950">
                                    <div className="bg-white p-2 rounded-lg border border-purple-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Hari / Jam Lembur</span>
                                        <span>{s.off_holiday_overtime_days || 0} Hari / {s.off_holiday_overtime_hours || 0}j</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-purple-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Lembur Bersih</span>
                                        <span className="font-bold">{s.off_holiday_net_overtime_hours || 0} Jam</span>
                                    </div>
                                    <div className="bg-white p-2 rounded-lg border border-purple-200">
                                        <span className="text-gray-500 block text-[10px] font-sans">Status Tarif</span>
                                        {s.off_holiday_rate_confirmed ? (
                                            <span className="text-emerald-700 font-bold">Terkonfirmasi</span>
                                        ) : Number(s.off_holiday_overtime_hours || 0) > 0 ? (
                                            <span className="text-amber-800 font-bold">Menunggu Kebijakan</span>
                                        ) : (
                                            <span className="text-gray-500">-</span>
                                        )}
                                    </div>
                                </div>
                                {!s.off_holiday_rate_confirmed && Number(s.off_holiday_overtime_hours || 0) > 0 && (
                                    <div className="bg-amber-100/70 border border-amber-300 rounded-lg p-2.5 text-[11px] text-amber-900 flex items-start gap-2">
                                        <AlertTriangle className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                        <div>
                                            <strong>Tarif Lembur OFF/Libur Belum Dikonfirmasi:</strong> Biaya belum dimasukkan ke slip gaji (Rp 0) untuk mencegah pencairan tanpa otorisasi.
                                            {snapshot.off_holiday_overtime?.estimated_cost > 0 && (
                                                <span className="block mt-0.5 text-amber-800">
                                                    Estimasi acuan PP 35/2021: {formatRupiah(snapshot.off_holiday_overtime.estimated_cost)}.
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Section 5: Totals */}
                            <div className="bg-emerald-50/70 border border-emerald-200 rounded-xl p-3.5 space-y-2 font-mono">
                                <div className="flex justify-between items-center text-xs font-sans font-bold text-emerald-950">
                                    <span>Total Komponen Lembur (Masuk ke Slip Gaji):</span>
                                    <span className="text-base font-extrabold text-emerald-900">{formatRupiah(s.total_overtime_cost || 0)}</span>
                                </div>
                                <div className="flex justify-between items-center text-xs font-sans text-emerald-800 pt-1 border-t border-emerald-200">
                                    <span>Total Upah Periode (Gaji Pokok + Lembur):</span>
                                    <span className="font-bold">{formatRupiah(s.total_period_salary || 0)}</span>
                                </div>
                            </div>

                            <div className="flex justify-end pt-2 border-t border-gray-100">
                                <SecondaryButton onClick={() => setDetailModalOpen(false)}>
                                    Tutup
                                </SecondaryButton>
                            </div>
                        </div>
                    );
                })()}
            </Modal>
        </AuthenticatedLayout>
    );
}
