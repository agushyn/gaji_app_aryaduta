import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    FileText,
    History,
    RefreshCw,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

export default function SecurityPayrollReports({
    periods = [],
    currentPeriod,
    activeTab = 'dashboard',
    filters = {},
    offices = [],
    metrics = null,
    reportData = {},
}) {
    const { flash } = usePage().props;

    const [search, setSearch] = useState(filters?.search || '');
    const [selectedPeriodId, setSelectedPeriodId] = useState(currentPeriod?.id || '');
    const [selectedOffice, setSelectedOffice] = useState(filters?.office_id || 'all');
    const [overtimeCategory, setOvertimeCategory] = useState(filters?.overtime_category || 'all');
    const [shortageStatus, setShortageStatus] = useState(filters?.status || 'all');
    const [reconciling, setReconciling] = useState(false);

    const formatRupiah = (num) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(num || 0);
    };

    const formatNumber = (num, decimals = 1) => {
        if (num === null || num === undefined) return '0';
        return Number(num).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimals,
        });
    };

    const applyFilters = (newParams = {}) => {
        router.get(
            route('payroll-security.reports.index'),
            {
                period_id: selectedPeriodId,
                tab: activeTab,
                search: search || undefined,
                office_id: selectedOffice !== 'all' ? selectedOffice : undefined,
                overtime_category: overtimeCategory !== 'all' ? overtimeCategory : undefined,
                status: shortageStatus !== 'all' ? shortageStatus : undefined,
                ...newParams,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handlePeriodChange = (periodId) => {
        setSelectedPeriodId(periodId);
        applyFilters({ period_id: periodId });
    };

    const handleTabChange = (tab) => {
        applyFilters({ tab });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const handleRunReconciliation = () => {
        if (!currentPeriod?.id) return;
        setReconciling(true);
        router.post(
            route('payroll-security.reconciliation.run'),
            {
                period_id: currentPeriod.id,
                search: search || undefined,
                office_id: selectedOffice !== 'all' ? selectedOffice : undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setReconciling(false),
            }
        );
    };

    const getExportUrl = (format = 'xlsx') => {
        const typeMapping = {
            dashboard: 'period-summary',
            detail: 'employee-detail',
            shortage: 'shortage',
            overtime: 'overtime',
            reconciliation: 'reconciliation',
            audit: 'audit',
        };
        const exportType = typeMapping[activeTab] || 'period-summary';
        const params = new URLSearchParams({
            period_id: currentPeriod?.id || '',
            format,
            search: search || '',
            office_id: selectedOffice !== 'all' ? selectedOffice : '',
            overtime_category: overtimeCategory !== 'all' ? overtimeCategory : '',
            status: shortageStatus !== 'all' ? shortageStatus : '',
        });

        return `${route('payroll-security.reports.export', exportType)}?${params.toString()}`;
    };

    const tabs = [
        { id: 'dashboard', label: 'Ringkasan Periode', icon: BarChart3 },
        { id: 'detail', label: 'Rincian Karyawan (24 Kolom)', icon: Users },
        { id: 'shortage', label: 'Kekurangan Jam & Carry-Forward', icon: Clock },
        { id: 'overtime', label: 'Lembur Reguler & Libur', icon: ShieldCheck },
        { id: 'reconciliation', label: 'Uji Rekonsiliasi (10 Integritas)', icon: ShieldAlert },
        { id: 'audit', label: 'Histori Audit', icon: History },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('payroll-security.index', { period_id: currentPeriod?.id })}
                                className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 transition font-medium"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                Kembali ke Input Rekap
                            </Link>
                        </div>
                        <h2 className="text-xl font-bold text-slate-900 leading-tight">
                            Laporan, Rekonsiliasi & Audit Payroll Security
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Analisis komprehensif, verifikasi data, rekonsiliasi non-mutasi, dan audit trail seluruh personel security.
                        </p>
                    </div>

                    {/* Action buttons */}
                    <div className="flex flex-wrap items-center gap-2">
                        {currentPeriod && (
                            <>
                                <a
                                    href={getExportUrl('xlsx')}
                                    className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-xs font-semibold text-emerald-800 shadow-xs transition"
                                >
                                    <FileSpreadsheet className="w-4 h-4 text-emerald-700" />
                                    Export Excel (.xlsx)
                                </a>
                                <a
                                    href={getExportUrl('csv')}
                                    className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-xs transition"
                                >
                                    <Download className="w-4 h-4 text-slate-600" />
                                    Export CSV (.csv)
                                </a>
                            </>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Laporan & Rekonsiliasi Payroll Security" />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3.5 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}
                {flash?.error && (
                    <div className="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-3.5 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                            <span>{flash.error}</span>
                        </div>
                    </div>
                )}

                {/* Filter and Period Selection Bar */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 sm:p-5">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div className="flex flex-wrap items-center gap-3">
                            {/* Period selector */}
                            <div className="w-64">
                                <label className="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    Periode Payroll
                                </label>
                                <select
                                    value={selectedPeriodId}
                                    onChange={(e) => handlePeriodChange(e.target.value)}
                                    className="w-full text-xs font-medium rounded-xl border-slate-300 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                                >
                                    {periods.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.period_code} ({p.formatted_start_date || p.start_date} – {p.formatted_end_date || p.end_date})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Office Filter */}
                            <div className="w-48">
                                <label className="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    Kantor / Cabang
                                </label>
                                <select
                                    value={selectedOffice}
                                    onChange={(e) => {
                                        setSelectedOffice(e.target.value);
                                        applyFilters({ office_id: e.target.value !== 'all' ? e.target.value : undefined });
                                    }}
                                    className="w-full text-xs font-medium rounded-xl border-slate-300 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                                >
                                    <option value="all">Semua Kantor</option>
                                    {offices.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Search */}
                            <form onSubmit={handleSearchSubmit} className="w-56">
                                <label className="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    Cari Petugas
                                </label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Nama / NIK Security..."
                                        className="w-full text-xs font-medium rounded-xl border-slate-300 pl-8 pr-3 shadow-xs focus:border-[#0B57D0] focus:ring-[#0B57D0]/20"
                                    />
                                    <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                                </div>
                            </form>
                        </div>

                        {/* Status Badge & Period Info */}
                        <div className="flex flex-wrap items-center gap-4 lg:border-l lg:border-slate-200 lg:pl-6">
                            <div>
                                <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                                    Status Periode
                                </span>
                                <span
                                    className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold mt-0.5 ${
                                        currentPeriod?.status === 'finalized'
                                            ? 'bg-purple-100 text-purple-800'
                                            : currentPeriod?.status === 'approved'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : currentPeriod?.status === 'changes_requested'
                                            ? 'bg-rose-100 text-rose-800'
                                            : currentPeriod?.status === 'in_review'
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-blue-100 text-blue-800'
                                    }`}
                                >
                                    <span className="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {currentPeriod?.status_label || currentPeriod?.status || 'Draft'}
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                                    Hari Kalender
                                </span>
                                <span className="text-base font-bold text-slate-900 font-mono">
                                    {metrics?.period?.period_days || 0} Hari
                                </span>
                            </div>

                            <div>
                                <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                                    Total Security
                                </span>
                                <span className="text-base font-bold text-slate-900 font-mono">
                                    {metrics?.total_security_employees || 0} Orang
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Top KPI Metrics Cards */}
                {metrics && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {/* Card 1: Status Slip */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                                Status Slip Gaji
                            </span>
                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div className="bg-slate-50 rounded-lg p-2">
                                    <span className="text-slate-500 block text-[10px]">Draft</span>
                                    <span className="font-bold text-slate-800 font-mono text-sm">{metrics.draft_count}</span>
                                </div>
                                <div className="bg-amber-50 rounded-lg p-2">
                                    <span className="text-amber-700 block text-[10px]">Review</span>
                                    <span className="font-bold text-amber-900 font-mono text-sm">{metrics.submitted_count}</span>
                                </div>
                                <div className="bg-emerald-50 rounded-lg p-2">
                                    <span className="text-emerald-700 block text-[10px]">Disetujui</span>
                                    <span className="font-bold text-emerald-900 font-mono text-sm">{metrics.approved_count}</span>
                                </div>
                                <div className="bg-purple-50 rounded-lg p-2">
                                    <span className="text-purple-700 block text-[10px]">Final</span>
                                    <span className="font-bold text-purple-900 font-mono text-sm">{metrics.finalized_count}</span>
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Financial Aggregates */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                                Ringkasan Finansial Slip
                            </span>
                            <div className="space-y-1.5 text-xs">
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Penghasilan:</span>
                                    <span className="font-bold text-slate-800 font-mono">{formatRupiah(metrics.total_earnings)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Potongan:</span>
                                    <span className="font-bold text-rose-600 font-mono">-{formatRupiah(metrics.total_deductions)}</span>
                                </div>
                                <div className="pt-1.5 border-t border-slate-100 flex justify-between items-center">
                                    <span className="font-bold text-slate-800">Total THP:</span>
                                    <span className="font-extrabold text-emerald-700 font-mono text-sm">{formatRupiah(metrics.total_net_salary)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Overtime Breakdown */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                                Lembur: Reguler vs Libur
                            </span>
                            <div className="space-y-1.5 text-xs">
                                <div className="flex justify-between items-center">
                                    <span className="text-blue-700 font-medium">Reguler ({metrics.total_regular_overtime_hours} jam):</span>
                                    <span className="font-bold text-blue-900 font-mono">{formatRupiah(metrics.total_regular_overtime_cost)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-purple-700 font-medium">Libur ({metrics.total_off_holiday_overtime_hours} jam):</span>
                                    <span className="font-bold text-purple-900 font-mono">{formatRupiah(metrics.total_off_holiday_overtime_cost)}</span>
                                </div>
                                <div className="pt-1.5 border-t border-slate-100 flex justify-between items-center">
                                    <span className="font-bold text-slate-800">Total Biaya Lembur:</span>
                                    <span className="font-extrabold text-amber-700 font-mono text-sm">{formatRupiah(metrics.total_overtime_cost)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Card 4: Integrity & Shortage Alerts */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                                Peringatan Integritas
                            </span>
                            <div className="space-y-2 text-xs">
                                <div className={`flex items-center justify-between p-2 rounded-lg ${metrics.employees_with_shortage_count > 0 ? 'bg-amber-50 text-amber-900' : 'bg-slate-50 text-slate-700'}`}>
                                    <div className="flex items-center gap-1.5">
                                        <Clock className="w-3.5 h-3.5 text-amber-600" />
                                        <span>Kurang Jam Kerja</span>
                                    </div>
                                    <span className="font-bold font-mono">{metrics.employees_with_shortage_count} Petugas</span>
                                </div>
                                <div className={`flex items-center justify-between p-2 rounded-lg ${metrics.incomplete_or_issue_count > 0 ? 'bg-rose-50 text-rose-900' : 'bg-slate-50 text-slate-700'}`}>
                                    <div className="flex items-center gap-1.5">
                                        <AlertTriangle className="w-3.5 h-3.5 text-rose-600" />
                                        <span>Data Belum Lengkap</span>
                                    </div>
                                    <span className="font-bold font-mono">{metrics.incomplete_or_issue_count} Orang</span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab Navigation Navigation */}
                <div className="border-b border-slate-200">
                    <nav className="-mb-px flex space-x-2 sm:space-x-4 overflow-x-auto">
                        {tabs.map((tab) => {
                            const Icon = tab.icon;
                            const isActive = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => handleTabChange(tab.id)}
                                    className={`whitespace-nowrap py-3 px-3.5 border-b-2 font-semibold text-xs flex items-center gap-2 transition ${
                                        isActive
                                            ? 'border-[#0B57D0] text-[#0B57D0]'
                                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                    }`}
                                >
                                    <Icon className="w-4 h-4" />
                                    <span>{tab.label}</span>
                                </button>
                            );
                        })}
                    </nav>
                </div>

                {/* TAB 1: Dashboard / Ringkasan Periode */}
                {activeTab === 'dashboard' && (
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Perbandingan Ringkasan Antar-Periode</h3>
                                <p className="text-xs text-slate-500">Histori dan tren payroll security di setiap periode penggajian.</p>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left border-collapse">
                                <thead className="bg-slate-50 text-slate-700 text-[11px] uppercase font-bold border-b border-slate-200">
                                    <tr>
                                        <th className="px-3.5 py-3">Kode Periode</th>
                                        <th className="px-3 py-3">Rentang Tanggal</th>
                                        <th className="px-3 py-3 text-center">Status</th>
                                        <th className="px-3 py-3 text-center">Jml Security</th>
                                        <th className="px-3 py-3 text-right">Lembur Reguler</th>
                                        <th className="px-3 py-3 text-right">Lembur Libur</th>
                                        <th className="px-3 py-3 text-right">Total Lembur</th>
                                        <th className="px-3 py-3 text-right">Total Penghasilan</th>
                                        <th className="px-3 py-3 text-right">Total Potongan</th>
                                        <th className="px-3 py-3 text-right">Total THP</th>
                                        <th className="px-3 py-3 text-center">Kurang Jam</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {reportData?.period_summaries?.length > 0 ? (
                                        reportData.period_summaries.map((ps) => (
                                            <tr key={ps.period_id} className="hover:bg-slate-50/80 transition">
                                                <td className="px-3.5 py-3 font-bold text-slate-900">{ps.period_code}</td>
                                                <td className="px-3 py-3 text-slate-600 font-mono text-[11px]">
                                                    {ps.start_date} – {ps.end_date} ({ps.period_days} Hari)
                                                </td>
                                                <td className="px-3 py-3 text-center">
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                                        {ps.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3 text-center font-mono font-bold">{ps.total_employees}</td>
                                                <td className="px-3 py-3 text-right font-mono">
                                                    <span className="block text-[11px]">{ps.total_regular_overtime_hours} jam</span>
                                                    <span className="text-[10px] text-slate-500">{formatRupiah(ps.total_regular_overtime_cost)}</span>
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono">
                                                    <span className="block text-[11px]">{ps.total_off_holiday_overtime_hours} jam</span>
                                                    <span className="text-[10px] text-slate-500">{formatRupiah(ps.total_off_holiday_overtime_cost)}</span>
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono font-bold text-amber-700">
                                                    {formatRupiah(ps.total_overtime_cost)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono text-slate-800">
                                                    {formatRupiah(ps.total_earnings)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono text-rose-600">
                                                    -{formatRupiah(ps.total_deductions)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono font-extrabold text-emerald-700">
                                                    {formatRupiah(ps.total_net_salary)}
                                                </td>
                                                <td className="px-3 py-3 text-center">
                                                    {ps.shortage_count > 0 ? (
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                                            {ps.shortage_count} org
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 text-[10px]">0</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={11} className="px-4 py-8 text-center text-slate-400">
                                                Tidak ada data periode yang ditemukan.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* TAB 2: Rincian Karyawan (24 Kolom) */}
                {activeTab === 'detail' && (
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Rincian Lengkap Seluruh Personel Security</h3>
                                <p className="text-xs text-slate-500">24 atribut data kehadiran, perhitungan jam, lembur biasa/libur, serta slip gaji.</p>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-slate-200 text-slate-700 text-[10px] uppercase font-extrabold text-center bg-slate-100">
                                        <th colSpan={3} className="px-3 py-2 border-r border-slate-200">Identitas</th>
                                        <th colSpan={7} className="px-3 py-2 border-r border-slate-200 bg-blue-50/70 text-blue-900">Kehadiran (8 Jam/Hari)</th>
                                        <th colSpan={3} className="px-3 py-2 border-r border-slate-200 bg-amber-50/70 text-amber-900">Kekurangan Jam</th>
                                        <th colSpan={4} className="px-3 py-2 border-r border-slate-200 bg-indigo-50/70 text-indigo-900">Lembur Reguler</th>
                                        <th colSpan={4} className="px-3 py-2 border-r border-slate-200 bg-purple-50/70 text-purple-900">Lembur Libur</th>
                                        <th colSpan={4} className="px-3 py-2 bg-emerald-50/70 text-emerald-950">Slip Gaji</th>
                                    </tr>
                                    <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-[10px] font-bold text-center">
                                        <th className="px-2 py-2 text-left">NIK</th>
                                        <th className="px-3 py-2 text-left">Nama</th>
                                        <th className="px-2 py-2 border-r border-slate-200">Kantor</th>
                                        <th className="px-1.5 py-2">Msk</th>
                                        <th className="px-1.5 py-2">OFF</th>
                                        <th className="px-1.5 py-2">Skt</th>
                                        <th className="px-1.5 py-2">Izn</th>
                                        <th className="px-1.5 py-2">Cut</th>
                                        <th className="px-1.5 py-2">Alf</th>
                                        <th className="px-2 py-2 border-r border-slate-200 font-mono">Jam Hdr</th>
                                        <th className="px-1.5 py-2 font-mono">Tgt</th>
                                        <th className="px-1.5 py-2 font-mono">Lalu</th>
                                        <th className="px-2 py-2 border-r border-slate-200 font-mono">Tot Krg</th>
                                        <th className="px-1 py-2">Hari</th>
                                        <th className="px-1.5 py-2 font-mono">Inp</th>
                                        <th className="px-1.5 py-2 font-mono">Net</th>
                                        <th className="px-2 py-2 border-r border-slate-200 text-right">Nominal</th>
                                        <th className="px-1 py-2">Hari</th>
                                        <th className="px-1.5 py-2 font-mono">Inp</th>
                                        <th className="px-1.5 py-2 font-mono">Net</th>
                                        <th className="px-2 py-2 border-r border-slate-200 text-right">Nominal</th>
                                        <th className="px-2 py-2 text-right">Tot Lembur</th>
                                        <th className="px-2 py-2 text-right">Potongan</th>
                                        <th className="px-2 py-2 text-right font-extrabold text-emerald-700">THP</th>
                                        <th className="px-2 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {reportData?.employees?.length > 0 ? (
                                        reportData.employees.map((emp) => (
                                            <tr key={emp.employee_id} className="hover:bg-slate-50/80 transition">
                                                <td className="px-2 py-2 font-mono text-[11px] text-slate-600">{emp.employee_number}</td>
                                                <td className="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{emp.employee_name}</td>
                                                <td className="px-2 py-2 border-r border-slate-200 text-slate-500 text-[10px]">{emp.office}</td>
                                                <td className="px-1.5 py-2 text-center font-mono">{emp.present_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.off_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.sick_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.permission_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.leave_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-rose-600 font-bold">{emp.absent_days}</td>
                                                <td className="px-2 py-2 border-r border-slate-200 text-center font-mono font-bold text-blue-900">{emp.attendance_hours}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.target_hours}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-amber-700">{emp.previous_shortage}</td>
                                                <td className={`px-2 py-2 border-r border-slate-200 text-center font-mono font-bold ${emp.total_shortage > 0 ? 'text-rose-600' : 'text-slate-500'}`}>{emp.total_shortage}</td>
                                                <td className="px-1 py-2 text-center font-mono">{emp.regular_overtime_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.regular_overtime_hours}</td>
                                                <td className="px-1.5 py-2 text-center font-mono font-bold text-indigo-900">{emp.regular_net_overtime_hours}</td>
                                                <td className="px-2 py-2 border-r border-slate-200 text-right font-mono text-slate-800">{formatRupiah(emp.regular_overtime_amount)}</td>
                                                <td className="px-1 py-2 text-center font-mono">{emp.off_holiday_overtime_days}</td>
                                                <td className="px-1.5 py-2 text-center font-mono text-slate-500">{emp.off_holiday_overtime_hours}</td>
                                                <td className="px-1.5 py-2 text-center font-mono font-bold text-purple-900">{emp.off_holiday_net_overtime_hours}</td>
                                                <td className="px-2 py-2 border-r border-slate-200 text-right font-mono text-slate-800">{formatRupiah(emp.off_holiday_overtime_amount)}</td>
                                                <td className="px-2 py-2 text-right font-mono font-bold text-amber-700">{formatRupiah(emp.total_overtime_amount)}</td>
                                                <td className="px-2 py-2 text-right font-mono text-rose-600">-{formatRupiah(emp.total_deductions)}</td>
                                                <td className="px-2 py-2 text-right font-mono font-extrabold text-emerald-700">{formatRupiah(emp.net_salary)}</td>
                                                <td className="px-2 py-2 text-center">
                                                    <span className="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-slate-100 text-slate-700">
                                                        {emp.payslip_status_label}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={25} className="px-4 py-8 text-center text-slate-400">
                                                Tidak ada data rincian security untuk filter saat ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* TAB 3: Kekurangan Jam & Carry-Forward */}
                {activeTab === 'shortage' && (
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Pelacakan Kekurangan Jam Kerja (Shortage) & Kompensasi</h3>
                                <p className="text-xs text-slate-500">Transparansi carry-forward antarperiode dan kompensasi (offset) dari jam lembur.</p>
                            </div>
                            {/* Filter Status Shortage */}
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500 font-medium">Filter:</span>
                                <select
                                    value={shortageStatus}
                                    onChange={(e) => {
                                        setShortageStatus(e.target.value);
                                        applyFilters({ status: e.target.value });
                                    }}
                                    className="text-xs rounded-xl border-slate-300 py-1.5 shadow-xs"
                                >
                                    <option value="all">Semua Status</option>
                                    <option value="shortage">Hanya yang Kurang Jam</option>
                                    <option value="clear">Jam Kerja Tuntas (0)</option>
                                </select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left border-collapse">
                                <thead className="bg-slate-50 text-slate-700 text-[11px] uppercase font-bold border-b border-slate-200">
                                    <tr>
                                        <th className="px-3.5 py-3">NIK</th>
                                        <th className="px-3.5 py-3">Nama Petugas</th>
                                        <th className="px-3 py-3">Kantor</th>
                                        <th className="px-3 py-3 text-center">Target Jam</th>
                                        <th className="px-3 py-3 text-center">Jam Hadir Aktual</th>
                                        <th className="px-3 py-3 text-center">Kurang Jam Berjalan</th>
                                        <th className="px-3 py-3 text-center">Carry-Forward Lalu</th>
                                        <th className="px-3 py-3 text-center">Total Kurang Jam</th>
                                        <th className="px-3 py-3 text-center">Offset Lembur</th>
                                        <th className="px-3 py-3 text-center">Sisa Kurang Jam</th>
                                        <th className="px-3 py-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {reportData?.shortages?.length > 0 ? (
                                        reportData.shortages.map((s) => (
                                            <tr key={s.employee_id} className="hover:bg-slate-50/80 transition">
                                                <td className="px-3.5 py-3 font-mono text-slate-600">{s.employee_number}</td>
                                                <td className="px-3.5 py-3 font-semibold text-slate-900">{s.employee_name}</td>
                                                <td className="px-3 py-3 text-slate-500 text-[11px]">{s.office}</td>
                                                <td className="px-3 py-3 text-center font-mono">{s.target_hours}</td>
                                                <td className="px-3 py-3 text-center font-mono font-bold text-blue-900">{s.attendance_hours}</td>
                                                <td className="px-3 py-3 text-center font-mono text-slate-700">{s.current_shortage}</td>
                                                <td className="px-3 py-3 text-center font-mono">
                                                    <span className={s.previous_shortage > 0 ? 'text-amber-700 font-bold' : 'text-slate-400'}>
                                                        {s.previous_shortage}
                                                    </span>
                                                    {s.source_period && (
                                                        <span className="block text-[9px] text-slate-400">({s.source_period})</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 text-center font-mono font-bold text-rose-600">{s.total_shortage}</td>
                                                <td className="px-3 py-3 text-center font-mono text-emerald-700">-{s.shortage_offset}</td>
                                                <td className="px-3 py-3 text-center font-mono font-extrabold text-slate-900">{s.remaining_shortage}</td>
                                                <td className="px-3 py-3 text-center">
                                                    {s.total_shortage > 0 ? (
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                                            {s.status}
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                                            {s.status}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={11} className="px-4 py-8 text-center text-slate-400">
                                                Tidak ada data kekurangan jam kerja.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* TAB 4: Lembur REGULAR & OFF_HOLIDAY */}
                {activeTab === 'overtime' && (
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Rincian Lembur REGULAR & OFF_HOLIDAY</h3>
                                <p className="text-xs text-slate-500">Pemisahan kategori lembur hari biasa dan hari off/libur beserta verifikasi tarif.</p>
                            </div>
                            {/* Filter Kategori Lembur */}
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500 font-medium">Kategori:</span>
                                <select
                                    value={overtimeCategory}
                                    onChange={(e) => {
                                        setOvertimeCategory(e.target.value);
                                        applyFilters({ overtime_category: e.target.value });
                                    }}
                                    className="text-xs rounded-xl border-slate-300 py-1.5 shadow-xs"
                                >
                                    <option value="all">Semua Kategori</option>
                                    <option value="REGULAR">Lembur Reguler (Hari Biasa)</option>
                                    <option value="OFF_HOLIDAY">Lembur OFF / Libur</option>
                                </select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left border-collapse">
                                <thead className="bg-slate-50 text-slate-700 text-[11px] uppercase font-bold border-b border-slate-200">
                                    <tr>
                                        <th className="px-3.5 py-3">NIK</th>
                                        <th className="px-3.5 py-3">Nama Petugas</th>
                                        <th className="px-3 py-3">Kantor</th>
                                        <th className="px-3 py-3 text-center">Kategori</th>
                                        <th className="px-3 py-3 text-center">Hari Lembur</th>
                                        <th className="px-3 py-3 text-center">Jam Input</th>
                                        <th className="px-3 py-3 text-center">Potongan Istirahat</th>
                                        <th className="px-3 py-3 text-center">Jam Bersih</th>
                                        <th className="px-3 py-3 text-right">Tarif / Jam</th>
                                        <th className="px-3 py-3 text-right">Nominal Lembur</th>
                                        <th className="px-3 py-3 text-center">Status Tarif</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {reportData?.overtimes?.length > 0 ? (
                                        reportData.overtimes.map((o, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50/80 transition">
                                                <td className="px-3.5 py-3 font-mono text-slate-600">{o.employee_number}</td>
                                                <td className="px-3.5 py-3 font-semibold text-slate-900">{o.employee_name}</td>
                                                <td className="px-3 py-3 text-slate-500 text-[11px]">{o.office}</td>
                                                <td className="px-3 py-3 text-center">
                                                    <span
                                                        className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                            o.category === 'REGULAR'
                                                                ? 'bg-blue-100 text-blue-800'
                                                                : 'bg-purple-100 text-purple-800'
                                                        }`}
                                                    >
                                                        {o.category_label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3 text-center font-mono">{o.overtime_days}</td>
                                                <td className="px-3 py-3 text-center font-mono text-slate-600">{o.input_hours} jam</td>
                                                <td className="px-3 py-3 text-center font-mono text-rose-600">-{o.break_deduction} jam</td>
                                                <td className="px-3 py-3 text-center font-mono font-bold text-slate-900">{o.net_hours} jam</td>
                                                <td className="px-3 py-3 text-right font-mono text-slate-700">{formatRupiah(o.hourly_rate)}</td>
                                                <td className="px-3 py-3 text-right font-mono font-bold text-amber-700">{formatRupiah(o.amount)}</td>
                                                <td className="px-3 py-3 text-center">
                                                    <span
                                                        className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                                                            o.status === 'TERVERIFIKASI'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : 'bg-amber-100 text-amber-800'
                                                        }`}
                                                    >
                                                        {o.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={11} className="px-4 py-8 text-center text-slate-400">
                                                Tidak ada data lembur untuk filter kategori saat ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* TAB 5: Uji Rekonsiliasi (10 Pemeriksaan Integritas) */}
                {activeTab === 'reconciliation' && (
                    <div className="space-y-4">
                        {/* Summary & Trigger Card */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-base font-bold text-slate-900">Hasil Audit Rekonsiliasi Integritas Data</h3>
                                        <span
                                            className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${
                                                reportData?.reconciliation?.overall_status === 'MATCH'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-rose-100 text-rose-800'
                                            }`}
                                        >
                                            STATUS: {reportData?.reconciliation?.overall_status || 'UNKNOWN'}
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-500 mt-1 max-w-2xl">
                                        Proses rekonsiliasi memverifikasi 10 kriteria integritas data antar input, formula Excel, ringkasan kalkulasi, dan slip gaji final.
                                        <span className="font-semibold text-slate-700"> Pemeriksaan ini bersifat read-only dan tidak memutasi data.</span>
                                    </p>
                                </div>

                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-4 text-xs font-mono">
                                        <div className="text-center">
                                            <span className="block text-[10px] text-slate-400 uppercase">Total Cek</span>
                                            <span className="font-bold text-slate-800 text-sm">
                                                {reportData?.reconciliation?.total_checks_count || 0}
                                            </span>
                                        </div>
                                        <div className="text-center">
                                            <span className="block text-[10px] text-emerald-600 uppercase">Match</span>
                                            <span className="font-bold text-emerald-700 text-sm">
                                                {reportData?.reconciliation?.match_count || 0}
                                            </span>
                                        </div>
                                        <div className="text-center">
                                            <span className="block text-[10px] text-rose-600 uppercase">Mismatch</span>
                                            <span className="font-bold text-rose-700 text-sm">
                                                {reportData?.reconciliation?.mismatch_count || 0}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={handleRunReconciliation}
                                        disabled={reconciling || !currentPeriod?.id}
                                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-xs font-semibold text-white shadow-xs transition disabled:opacity-50"
                                    >
                                        <RefreshCw className={`w-3.5 h-3.5 ${reconciling ? 'animate-spin' : ''}`} />
                                        <span>Jalankan Rekonsiliasi</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Mismatch Alert Notice if any */}
                        {reportData?.reconciliation?.mismatch_count > 0 && (
                            <div className="bg-rose-50 border border-rose-200 text-rose-900 rounded-2xl p-4 text-xs flex items-start gap-3">
                                <AlertCircle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                                <div>
                                    <h4 className="font-bold text-sm">Ditemukan Ketidaksesuaian Integritas Data (MISMATCH)</h4>
                                    <p className="mt-0.5">
                                        Terdapat {reportData?.reconciliation?.mismatch_count} poin pengecekan yang tidak sesuai. Periksa panduan perbaikan pada tabel di bawah untuk melakukan koreksi sebelum finalisasi periode.
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Reconciliation Checks Table */}
                        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left border-collapse">
                                    <thead className="bg-slate-50 text-slate-700 text-[11px] uppercase font-bold border-b border-slate-200">
                                        <tr>
                                            <th className="px-3.5 py-3">NIK</th>
                                            <th className="px-3.5 py-3">Nama Petugas</th>
                                            <th className="px-3 py-3">Pemeriksaan Integritas</th>
                                            <th className="px-3 py-3 text-right">Nilai Sumber</th>
                                            <th className="px-3 py-3 text-right">Nilai Pembanding</th>
                                            <th className="px-3 py-3 text-right">Selisih</th>
                                            <th className="px-3 py-3 text-center">Status</th>
                                            <th className="px-4 py-3">Panduan / Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {reportData?.reconciliation?.results?.length > 0 ? (
                                            reportData.reconciliation.results.flatMap((res) =>
                                                res.checks.map((chk, idx) => (
                                                    <tr
                                                        key={`${res.employee_id}-${idx}`}
                                                        className={`transition ${chk.status === 'MISMATCH' ? 'bg-rose-50/60' : 'hover:bg-slate-50/80'}`}
                                                    >
                                                        {idx === 0 ? (
                                                            <>
                                                                <td
                                                                    rowSpan={res.checks.length}
                                                                    className="px-3.5 py-3 font-mono text-slate-600 align-top border-r border-slate-100 bg-white"
                                                                >
                                                                    {res.employee_number}
                                                                </td>
                                                                <td
                                                                    rowSpan={res.checks.length}
                                                                    className="px-3.5 py-3 font-semibold text-slate-900 align-top border-r border-slate-100 bg-white"
                                                                >
                                                                    {res.employee_name}
                                                                    <span className="block text-[10px] text-slate-400 font-normal">{res.office}</span>
                                                                </td>
                                                            </>
                                                        ) : null}
                                                        <td className="px-3 py-2.5 font-medium text-slate-800">{chk.name}</td>
                                                        <td className="px-3 py-2.5 text-right font-mono text-slate-700">{chk.source_value}</td>
                                                        <td className="px-3 py-2.5 text-right font-mono text-slate-700">{chk.target_value}</td>
                                                        <td className="px-3 py-2.5 text-right font-mono font-bold text-slate-900">{chk.difference}</td>
                                                        <td className="px-3 py-2.5 text-center">
                                                            <span
                                                                className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                                    chk.status === 'MATCH'
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : 'bg-rose-100 text-rose-800'
                                                                }`}
                                                            >
                                                                {chk.status === 'MATCH' ? (
                                                                    <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                                ) : (
                                                                    <XCircle className="w-3 h-3 text-rose-600" />
                                                                )}
                                                                {chk.status}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-2.5 text-[11px] text-slate-600">{chk.guidance}</td>
                                                    </tr>
                                                ))
                                            )
                                        ) : (
                                            <tr>
                                                <td colSpan={8} className="px-4 py-8 text-center text-slate-400">
                                                    Belum ada hasil rekonsiliasi. Klik "Jalankan Rekonsiliasi" di atas.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* TAB 6: Histori Audit */}
                {activeTab === 'audit' && (
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Histori Audit Trail & Persetujuan Security</h3>
                                <p className="text-xs text-slate-500">Rekap seluruh mutasi data kehadiran, sinkronisasi slip, perubahan konfigurasi, dan keputusan approval.</p>
                            </div>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {reportData?.events?.length > 0 ? (
                                reportData.events.map((evt, idx) => (
                                    <div key={idx} className="p-4 hover:bg-slate-50/80 transition flex items-start gap-3">
                                        <div
                                            className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5 ${
                                                evt.type === 'approval_event'
                                                    ? 'bg-purple-100 text-purple-700'
                                                    : 'bg-blue-100 text-blue-700'
                                            }`}
                                        >
                                            {evt.type === 'approval_event' ? (
                                                <ShieldCheck className="w-4 h-4" />
                                            ) : (
                                                <History className="w-4 h-4" />
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-semibold text-slate-900 text-xs">
                                                    {evt.action_label}
                                                </span>
                                                <span className="text-[10px] text-slate-400 font-mono">
                                                    {evt.created_at}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-600 mt-0.5">
                                                Oleh: <span className="font-medium text-slate-800">{evt.actor_name}</span>
                                            </p>
                                            {evt.comment && (
                                                <p className="text-[11px] text-slate-500 mt-1 bg-slate-50 rounded-lg p-2 font-mono overflow-x-auto whitespace-pre-wrap">
                                                    {evt.comment}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="px-4 py-8 text-center text-slate-400 text-xs">
                                    Belum ada log audit untuk periode ini.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

