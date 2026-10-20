import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Edit3,
    Eye,
    Info,
    Plus,
    RotateCcw,
    Save,
    Search,
    Sliders,
    Sparkles,
    ToggleLeft,
    ToggleRight,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

export default function PayrollConfigurationsIndex({
    configs,
    counts,
    filters,
    overtimeSettings,
}) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [dayType, setDayType] = useState(filters?.day_type || '');
    const [status, setStatus] = useState(filters?.status || '');

    // Overtime threshold configuration form state
    const [workingDayThreshold, setWorkingDayThreshold] = useState(
        overtimeSettings?.working_day_threshold ?? 3.0
    );
    const [holidayThreshold, setHolidayThreshold] = useState(
        overtimeSettings?.holiday_threshold ?? 4.0
    );
    const [isSavingSettings, setIsSavingSettings] = useState(false);
    const [settingsErrors, setSettingsErrors] = useState({});
    const [settingsSuccess, setSettingsSuccess] = useState(null);

    useEffect(() => {
        if (overtimeSettings) {
            if (overtimeSettings.working_day_threshold !== undefined) {
                setWorkingDayThreshold(overtimeSettings.working_day_threshold);
            }
            if (overtimeSettings.holiday_threshold !== undefined) {
                setHolidayThreshold(overtimeSettings.holiday_threshold);
            }
        }
    }, [overtimeSettings]);

    // Format minutes helper for preview simulation cards
    const formatMinutes = (totalMinutes) => {
        const m = Math.max(0, Math.round(totalMinutes));
        const h = Math.floor(m / 60);
        const rem = m % 60;
        return `${h}:${rem.toString().padStart(2, '0')}`;
    };

    const getLiveExamples = (thresholdHours, durationPerDay) => {
        const thresholdMin = Math.round(Number(thresholdHours || 0) * 60);
        return [
            {
                time: formatMinutes(thresholdMin - 1),
                deducted: false,
                label: 'Tidak dipotong',
            },
            {
                time: formatMinutes(thresholdMin),
                deducted: true,
                label: `Dipotong (${durationPerDay} jam)`,
            },
            {
                time: formatMinutes(thresholdMin + 1),
                deducted: true,
                label: `Dipotong (${durationPerDay} jam)`,
            },
            {
                time: formatMinutes(thresholdMin + 60),
                deducted: true,
                label: `Dipotong (${durationPerDay} jam)`,
            },
        ];
    };

    const handleSaveOvertimeSettings = (e) => {
        e.preventDefault();
        setIsSavingSettings(true);
        setSettingsErrors({});
        setSettingsSuccess(null);

        router.post(
            route('payroll-configurations.overtime-settings'),
            {
                working_day_threshold: parseFloat(workingDayThreshold),
                holiday_threshold: parseFloat(holidayThreshold),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSavingSettings(false);
                    setSettingsSuccess('Pengaturan jam lembur dan batas potongan istirahat berhasil disimpan ke database.');
                    setTimeout(() => setSettingsSuccess(null), 5000);
                },
                onError: (errs) => {
                    setIsSavingSettings(false);
                    setSettingsErrors(errs || {});
                },
            }
        );
    };

    const handleResetDefaults = () => {
        setWorkingDayThreshold(3.0);
        setHolidayThreshold(4.0);
    };

    // Preview modal state
    const [previewItem, setPreviewItem] = useState(null);

    // Safe formatter to prevent 'Objects are not valid as a React child'
    const formatFormulaPreviewText = (preview) => {
        if (!preview) return '-';
        if (typeof preview === 'string') return preview;
        if (typeof preview === 'object') {
            if (preview.text) return String(preview.text);
            if (preview.formatted_text) return String(preview.formatted_text);
            if (preview.formula_text) return String(preview.formula_text);
            if (preview.regular_overtime || preview.holiday_overtime) {
                const parts = [];
                if (preview.regular_overtime) {
                    const reg = preview.regular_overtime;
                    parts.push(typeof reg === 'string' ? reg : (reg.text || JSON.stringify(reg, null, 2)));
                }
                if (preview.holiday_overtime) {
                    const hol = preview.holiday_overtime;
                    parts.push(typeof hol === 'string' ? hol : (hol.text || JSON.stringify(hol, null, 2)));
                }
                return parts.join('\n\n---\n\n');
            }
            return JSON.stringify(preview, null, 2);
        }
        return String(preview);
    };
    const [deleteItem, setDeleteItem] = useState(null);

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*') || user.roles?.includes('super_admin')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payroll-configurations.index'),
            { search, day_type: dayType, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setDayType('');
        setStatus('');
        router.get(route('payroll-configurations.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (config) => {
        router.patch(
            route('payroll-configurations.toggle-status', config.id),
            {},
            { preserveScroll: true }
        );
    };

    const handleDuplicate = (config) => {
        if (confirm(`Duplikasi konfigurasi '${config.name}'?`)) {
            router.post(
                route('payroll-configurations.duplicate', config.id),
                {},
                { preserveScroll: true }
            );
        }
    };

    const confirmDelete = (config) => {
        setDeleteItem(config);
    };

    const handleDelete = () => {
        if (!deleteItem) return;
        router.delete(
            route('payroll-configurations.destroy', deleteItem.id),
            {
                preserveScroll: true,
                onSuccess: () => setDeleteItem(null),
            }
        );
    };

    return (
        <AuthenticatedLayout
            title="Konfigurasi Perhitungan Payroll"
            header="Konfigurasi Perhitungan Payroll"
        >
            <Head title="Konfigurasi Perhitungan Payroll" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0] shrink-0">
                            <Sliders className="h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                Konfigurasi Perhitungan Payroll
                            </h1>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Atur logic perhitungan lembur, formula fleksibel, dan versi formula per jenis hari.
                            </p>
                        </div>
                    </div>
                    {can('payroll.configuration.create') && (
                        <Link
                            href={route('payroll-configurations.create')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-[#0B57D0] hover:bg-[#0A58CA] text-white text-xs font-semibold rounded-xl shadow-xs transition"
                        >
                            <Plus className="h-4 w-4" />
                            Tambah Konfigurasi
                        </Link>
                    )}
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-slate-500 font-medium">Total Aturan</div>
                        <div className="text-2xl font-bold text-slate-900 mt-1">{counts.total}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-emerald-600 font-medium">Aturan Aktif</div>
                        <div className="text-2xl font-bold text-emerald-700 mt-1">{counts.active}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-blue-600 font-medium">Hari Kerja</div>
                        <div className="text-2xl font-bold text-blue-700 mt-1">{counts.working_day}</div>
                    </div>
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <div className="text-xs text-amber-600 font-medium">Hari Libur</div>
                        <div className="text-2xl font-bold text-amber-700 mt-1">{counts.holiday}</div>
                    </div>
                </div>

                {/* Pengaturan Jam Lembur & Potongan */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-indigo-50/40">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div className="flex items-center space-x-3">
                                <div className="p-2.5 bg-indigo-600 text-white rounded-xl shadow-xs">
                                    <Clock className="w-5 h-5" />
                                </div>
                                <div>
                                    <div className="flex items-center space-x-2">
                                        <h3 className="text-base font-bold text-slate-800">
                                            Pengaturan Jam Lembur & Potongan Istirahat
                                        </h3>
                                        <span className="px-2 py-0.5 text-[11px] font-semibold bg-emerald-100 text-emerald-800 rounded-full">
                                            Operator Inclusive (≥)
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        Atur batas durasi lembur aktual minimum yang memicu potongan jam istirahat. Berlaku otomatis pada kalkulasi payroll berikutnya.
                                    </p>
                                </div>
                            </div>

                            {/* Status Pill Indicator */}
                            <div className="flex items-center space-x-2 text-xs">
                                <span className="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-slate-600 font-mono text-[11px]">
                                    Hari Biasa: <strong className="text-indigo-600">≥ {workingDayThreshold} jam</strong>
                                </span>
                                <span className="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-slate-600 font-mono text-[11px]">
                                    Hari Libur: <strong className="text-amber-600">≥ {holidayThreshold} jam</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <form onSubmit={handleSaveOvertimeSettings} className="p-5 space-y-5">
                        {settingsSuccess && (
                            <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 flex items-center space-x-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>{settingsSuccess}</span>
                            </div>
                        )}

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            {/* Card 1: Lembur Hari Biasa */}
                            <div className="rounded-xl border border-indigo-100 bg-indigo-50/20 p-4 space-y-3.5">
                                <div className="flex items-center justify-between pb-2 border-b border-indigo-100/60">
                                    <div className="flex items-center space-x-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                                        <span className="text-xs font-bold uppercase tracking-wider text-indigo-900">
                                            Lembur Hari Biasa (Hari Kerja)
                                        </span>
                                    </div>
                                    <span className="text-[11px] font-semibold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-md">
                                        Versi Aktif: v{overtimeSettings?.working_day_version ?? 1}
                                    </span>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="block text-xs font-semibold text-slate-700">
                                        Batas Jam Minimum untuk Potongan
                                        <span className="text-slate-400 font-normal ml-1">(Default awal: 3.0 jam)</span>
                                    </label>
                                    <div className="flex items-center space-x-2">
                                        <div className="relative flex-1">
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0.5"
                                                max="24"
                                                value={workingDayThreshold}
                                                disabled={!can('payroll.configuration.update') || isSavingSettings}
                                                onChange={(e) => setWorkingDayThreshold(e.target.value)}
                                                className="w-full text-xs font-bold text-slate-800 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:cursor-not-allowed"
                                                placeholder="3.0"
                                            />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">
                                                Jam
                                            </span>
                                        </div>
                                        <span className="text-xs font-mono font-bold px-2.5 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-slate-700">
                                            = {Math.round(Number(workingDayThreshold || 0) * 60)} menit
                                        </span>
                                    </div>
                                    {settingsErrors.working_day_threshold && (
                                        <p className="text-xs text-rose-600 font-medium">
                                            {settingsErrors.working_day_threshold}
                                        </p>
                                    )}
                                </div>

                                {/* Rule Info Callout */}
                                <div className="text-[11px] text-slate-600 bg-white rounded-lg p-3 border border-indigo-100 space-y-1">
                                    <div className="flex items-center justify-between font-semibold text-indigo-950">
                                        <span>Ketentuan Potongan:</span>
                                        <span className="font-mono text-indigo-600">Durasi Aktual ≥ {workingDayThreshold} Jam</span>
                                    </div>
                                    <div>• Nominal potongan: <strong>0.5 jam</strong> per hari lembur</div>
                                    <div>• Jam lembur bersih: <strong>Total Jam - (Hari Lembur × 0.5 jam)</strong></div>
                                </div>

                                {/* Live Simulation Matrix */}
                                <div className="pt-2 border-t border-indigo-100/60">
                                    <div className="text-[11px] font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                                        <span>Simulasi Perhitungan Aktual:</span>
                                        <span className="text-[10px] text-slate-400">Presisi Menit Tanpa Prematur Rounding</span>
                                    </div>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-center text-xs">
                                        {getLiveExamples(workingDayThreshold, 0.5).map((ex, idx) => (
                                            <div
                                                key={idx}
                                                className={`rounded-lg p-2 border transition ${
                                                    ex.deducted
                                                        ? 'bg-amber-50/80 border-amber-200'
                                                        : 'bg-white border-slate-200'
                                                }`}
                                            >
                                                <div className={`font-mono font-bold ${ex.deducted ? 'text-amber-900' : 'text-slate-800'}`}>
                                                    {ex.time}
                                                </div>
                                                <div className={`text-[10px] mt-0.5 ${ex.deducted ? 'text-amber-700 font-bold' : 'text-slate-500 font-medium'}`}>
                                                    {ex.label}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Card 2: Lembur Hari Libur */}
                            <div className="rounded-xl border border-amber-100 bg-amber-50/20 p-4 space-y-3.5">
                                <div className="flex items-center justify-between pb-2 border-b border-amber-100/60">
                                    <div className="flex items-center space-x-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                        <span className="text-xs font-bold uppercase tracking-wider text-amber-950">
                                            Lembur Hari Libur (Weekend / Nasional)
                                        </span>
                                    </div>
                                    <span className="text-[11px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                        Versi Aktif: v{overtimeSettings?.holiday_version ?? 1}
                                    </span>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="block text-xs font-semibold text-slate-700">
                                        Batas Jam Minimum untuk Potongan
                                        <span className="text-slate-400 font-normal ml-1">(Default awal: 4.0 jam)</span>
                                    </label>
                                    <div className="flex items-center space-x-2">
                                        <div className="relative flex-1">
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0.5"
                                                max="24"
                                                value={holidayThreshold}
                                                disabled={!can('payroll.configuration.update') || isSavingSettings}
                                                onChange={(e) => setHolidayThreshold(e.target.value)}
                                                className="w-full text-xs font-bold text-slate-800 rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500 disabled:bg-slate-100 disabled:cursor-not-allowed"
                                                placeholder="4.0"
                                            />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">
                                                Jam
                                            </span>
                                        </div>
                                        <span className="text-xs font-mono font-bold px-2.5 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-slate-700">
                                            = {Math.round(Number(holidayThreshold || 0) * 60)} menit
                                        </span>
                                    </div>
                                    {settingsErrors.holiday_threshold && (
                                        <p className="text-xs text-rose-600 font-medium">
                                            {settingsErrors.holiday_threshold}
                                        </p>
                                    )}
                                </div>

                                {/* Rule Info Callout */}
                                <div className="text-[11px] text-slate-600 bg-white rounded-lg p-3 border border-amber-100 space-y-1">
                                    <div className="flex items-center justify-between font-semibold text-amber-950">
                                        <span>Ketentuan Potongan:</span>
                                        <span className="font-mono text-amber-700">Durasi Aktual ≥ {holidayThreshold} Jam</span>
                                    </div>
                                    <div>• Nominal potongan: <strong>1.0 jam</strong> per hari lembur</div>
                                    <div>• Jam lembur bersih: <strong>Total Jam - (Hari Lembur × 1.0 jam)</strong></div>
                                </div>

                                {/* Live Simulation Matrix */}
                                <div className="pt-2 border-t border-amber-100/60">
                                    <div className="text-[11px] font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                                        <span>Simulasi Perhitungan Aktual:</span>
                                        <span className="text-[10px] text-slate-400">Presisi Menit Tanpa Prematur Rounding</span>
                                    </div>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-center text-xs">
                                        {getLiveExamples(holidayThreshold, 1.0).map((ex, idx) => (
                                            <div
                                                key={idx}
                                                className={`rounded-lg p-2 border transition ${
                                                    ex.deducted
                                                        ? 'bg-amber-50/80 border-amber-200'
                                                        : 'bg-white border-slate-200'
                                                }`}
                                            >
                                                <div className={`font-mono font-bold ${ex.deducted ? 'text-amber-900' : 'text-slate-800'}`}>
                                                    {ex.time}
                                                </div>
                                                <div className={`text-[10px] mt-0.5 ${ex.deducted ? 'text-amber-700 font-bold' : 'text-slate-500 font-medium'}`}>
                                                    {ex.label}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Action Bar */}
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-100">
                            <div className="flex items-center space-x-2 text-xs text-slate-500">
                                <Info className="w-4 h-4 text-slate-400 shrink-0" />
                                <span>Nilai tersimpan di database dan langsung berlaku pada kalkulasi & slip gaji berikutnya.</span>
                            </div>

                            {can('payroll.configuration.update') ? (
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={handleResetDefaults}
                                        disabled={isSavingSettings}
                                        className="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition disabled:opacity-50"
                                    >
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        Reset Default
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={isSavingSettings}
                                        className="inline-flex items-center gap-2 px-4 py-2.5 bg-[#0B57D0] hover:bg-[#0A58CA] text-white text-xs font-semibold rounded-xl shadow-xs transition disabled:opacity-50"
                                    >
                                        <Save className="w-3.5 h-3.5" />
                                        {isSavingSettings ? 'Menyimpan...' : 'Simpan Pengaturan Lembur'}
                                    </button>
                                </div>
                            ) : (
                                <span className="text-xs text-slate-400 italic">
                                    Hanya personalia/admin dengan hak akses edit yang dapat mengubah pengaturan ini.
                                </span>
                            )}
                        </div>
                    </form>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Cari nama konfigurasi atau kode..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <select
                            value={dayType}
                            onChange={(e) => setDayType(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Jenis Hari</option>
                            <option value="working_day">Hari Kerja</option>
                            <option value="holiday">Hari Libur</option>
                            <option value="national_holiday">Libur Nasional</option>
                            <option value="joint_leave">Cuti Bersama</option>
                            <option value="weekend">Weekend</option>
                        </select>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition"
                            >
                                Filter
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

                {/* Table of Configurations */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-3.5">Nama Configuration</th>
                                    <th className="px-4 py-3.5">Komponen</th>
                                    <th className="px-4 py-3.5">Jenis Hari</th>
                                    <th className="px-4 py-3.5">Versi</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5">Updated At</th>
                                    <th className="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {configs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-8 text-center text-slate-400">
                                            Belum ada konfigurasi perhitungan payroll.
                                        </td>
                                    </tr>
                                ) : (
                                    configs.data.map((cfg) => (
                                        <tr key={cfg.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-3 font-medium text-slate-800">
                                                <div className="font-semibold text-slate-900">{cfg.name}</div>
                                                <div className="text-[11px] text-slate-400 font-mono">{cfg.code}</div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {cfg.pay_component ? (
                                                    <span className="font-medium text-slate-800">
                                                        {cfg.pay_component.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 italic">Semua</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                                        cfg.day_type === 'working_day'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-amber-100 text-amber-800'
                                                    }`}
                                                >
                                                    {cfg.day_type_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-md font-mono text-[11px] font-semibold">
                                                    v{cfg.current_version}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {cfg.is_active ? (
                                                    <span className="inline-flex items-center text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                                        <Check className="w-3 h-3 mr-1" />
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center text-[11px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-500 text-[11px]">
                                                {cfg.updated_at}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center space-x-1.5">
                                                    {/* View Formula Preview */}
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewItem(cfg)}
                                                        title="Lihat Formula"
                                                        className="p-1 text-slate-400 hover:text-indigo-600 rounded transition"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </button>

                                                    {/* Edit */}
                                                    {can('payroll.configuration.update') && (
                                                        <Link
                                                            href={route('payroll-configurations.edit', cfg.id)}
                                                            title="Edit Konfigurasi"
                                                            className="p-1 text-slate-400 hover:text-blue-600 rounded transition"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </Link>
                                                    )}

                                                    {/* Duplicate */}
                                                    {can('payroll.configuration.create') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDuplicate(cfg)}
                                                            title="Duplikat Konfigurasi"
                                                            className="p-1 text-slate-400 hover:text-emerald-600 rounded transition"
                                                        >
                                                            <Copy className="w-4 h-4" />
                                                        </button>
                                                    )}

                                                    {/* Activate / Deactivate Toggle */}
                                                    {can('payroll.configuration.update') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleToggleStatus(cfg)}
                                                            title={cfg.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                            className="p-1 text-slate-400 hover:text-amber-600 rounded transition"
                                                        >
                                                            {cfg.is_active ? (
                                                                <ToggleRight className="w-5 h-5 text-emerald-600" />
                                                            ) : (
                                                                <ToggleLeft className="w-5 h-5 text-slate-400" />
                                                            )}
                                                        </button>
                                                    )}

                                                    {/* Delete */}
                                                    {can('payroll.configuration.delete') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => confirmDelete(cfg)}
                                                            title={
                                                                cfg.is_used_in_finalized_payslips
                                                                    ? 'Tidak dapat dihapus karena telah digunakan pada slip gaji finalized'
                                                                    : 'Hapus Konfigurasi'
                                                            }
                                                            className={`p-1 rounded transition ${
                                                                cfg.is_used_in_finalized_payslips
                                                                    ? 'text-slate-300 cursor-not-allowed'
                                                                    : 'text-slate-400 hover:text-rose-600'
                                                            }`}
                                                            disabled={cfg.is_used_in_finalized_payslips}
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
                    {configs.links && configs.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>Menampilkan {configs.data.length} dari {configs.total} data</div>
                            <div className="flex space-x-1">
                                {configs.links.map((link, idx) => (
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

            {/* Formula Preview Modal */}
            <Modal show={!!previewItem} onClose={() => setPreviewItem(null)}>
                <div className="p-6 space-y-4 max-w-2xl mx-auto">
                    <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div className="flex items-center gap-2">
                            <Sparkles className="h-5 w-5 text-indigo-600" />
                            <h3 className="font-bold text-slate-800 text-base">
                                Preview Formula: {previewItem?.name}
                            </h3>
                        </div>
                        <span className="font-mono text-xs bg-indigo-100 text-indigo-700 px-2.5 py-0.5 rounded-full font-semibold">
                            Versi {previewItem?.current_version}
                        </span>
                    </div>

                    {/* Ketentuan Potongan Jam Lembur (Threshold Box) */}
                    <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-blue-900">
                                {previewItem?.preview_details?.title || (previewItem?.day_type === 'holiday'
                                    ? 'LEMBUR HARI LIBUR'
                                    : 'LEMBUR HARI BIASA')}
                            </span>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-blue-600 text-white">
                                Threshold ≥ {previewItem?.preview_details?.threshold_hours ?? (previewItem?.day_type === 'holiday' ? 4 : 3)} jam (≥ {previewItem?.preview_details?.threshold_minutes ?? (previewItem?.day_type === 'holiday' ? 240 : 180)} mnt)
                            </span>
                        </div>
                        <p className="text-xs text-blue-900 font-medium">
                            {previewItem?.preview_details?.rule_description || 'Potongan istirahat mulai berlaku pada lembur ≥ threshold.'}
                            {' '}Besar potongan: Jumlah Hari Lembur × {previewItem?.preview_details?.duration_per_day ?? (previewItem?.day_type === 'holiday' ? 1.0 : 0.5)} jam.
                        </p>

                        {/* Contoh Pengujian Threshold */}
                        <div className="mt-2 pt-2 border-t border-blue-200/60">
                            <div className="text-[11px] font-semibold text-blue-800 mb-1.5 flex items-center justify-between">
                                <span>Contoh Pengujian Durasi Lembur (Kalkulasi Engine):</span>
                                <span className="text-[10px] text-blue-600 font-mono">Operator: ≥ (Inclusive)</span>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                {(previewItem?.preview_details?.examples || []).map((ex, idx) => (
                                    <div
                                        key={idx}
                                        className={`rounded-lg p-2 border shadow-xs ${
                                            ex.deducted
                                                ? 'bg-amber-50 border-amber-200'
                                                : 'bg-white border-blue-200'
                                        }`}
                                    >
                                        <div className={`font-mono font-bold ${ex.deducted ? 'text-amber-900' : 'text-slate-800'}`}>
                                            {ex.time}
                                        </div>
                                        <div className={`text-[11px] mt-0.5 ${ex.deducted ? 'text-amber-700 font-bold' : 'text-slate-500 font-medium'}`}>
                                            {ex.label} {ex.deducted && ex.deduction ? `(${ex.deduction} jam)` : ''}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Dark Code Box for Full Formula Preview Text */}
                    <div className="bg-slate-900 text-indigo-100 p-4 rounded-xl text-xs font-mono space-y-2 leading-relaxed">
                        <div className="text-slate-400 text-[11px] uppercase tracking-wider font-sans">
                            Rumus & Evaluasi Engine:
                        </div>
                        <div className="text-white font-medium whitespace-pre-wrap">
                            {formatFormulaPreviewText(previewItem?.formula_preview)}
                        </div>
                    </div>

                    <div className="text-xs text-slate-500 bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1">
                        <div><strong>Komponen Terkait:</strong> {previewItem?.pay_component?.name || '-'}</div>
                        <div><strong>Jenis Hari:</strong> {previewItem?.day_type_label || '-'}</div>
                        <div><strong>Tarif Dasar:</strong> Diambil dari default_overtime_rate Master Karyawan</div>
                    </div>

                    <div className="flex justify-end pt-2">
                        <SecondaryButton onClick={() => setPreviewItem(null)}>Tutup</SecondaryButton>
                    </div>
                </div>
            </Modal>

            {/* Delete Confirmation Modal */}
            <Modal show={!!deleteItem} onClose={() => setDeleteItem(null)}>
                <div className="p-6 space-y-4">
                    <div className="flex items-center space-x-3 text-rose-600">
                        <AlertCircle className="w-6 h-6 flex-shrink-0" />
                        <h3 className="font-bold text-slate-800 text-base">Hapus Konfigurasi Perhitungan</h3>
                    </div>
                    <p className="text-xs text-slate-600">
                        Apakah Anda yakin ingin menghapus konfigurasi <strong>{deleteItem?.name}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div className="flex justify-end space-x-2 pt-2">
                        <SecondaryButton onClick={() => setDeleteItem(null)}>Batal</SecondaryButton>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition"
                        >
                            Hapus Konfigurasi
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
