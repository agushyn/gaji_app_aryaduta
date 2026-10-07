import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    BarChart3,
    Calendar,
    CheckCircle,
    Clock,
    Download,
    Eye,
    FileSpreadsheet,
    Filter,
    History,
    Mail,
    PieChart,
    RefreshCw,
    Search,
    Send,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function ReportsDeliveries({
    summary = {},
    departmentsBreakdown = {},
    deliveries,
    canViewFinancial = false,
    periods = [],
    departmentOptions = [],
    filters = {},
    can = {},
}) {
    const { flash } = usePage().props;

    const [periodId, setPeriodId] = useState(filters?.period_id || 'all');
    const [department, setDepartment] = useState(filters?.department || 'all');
    const [channel, setChannel] = useState(filters?.channel || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [search, setSearch] = useState(filters?.search || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(
            route('reports.deliveries.index'),
            {
                period_id: periodId,
                department,
                channel,
                status,
                search,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setPeriodId('all');
        setDepartment('all');
        setChannel('all');
        setStatus('all');
        setSearch('');
        router.get(route('reports.deliveries.index'), {}, { preserveState: true, replace: true });
    };

    const handleExportCsv = () => {
        const queryParams = new URLSearchParams({
            period_id: periodId,
            department,
            channel,
            status,
            search,
        }).toString();

        window.location.href = route('reports.deliveries.export') + '?' + queryParams;
    };

    const getStatusBadge = (deliveryStatus) => {
        switch (deliveryStatus) {
            case 'sent':
            case 'delivered':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <CheckCircle className="h-3 w-3" />
                        {deliveryStatus === 'delivered' ? 'Diterima' : 'Terkirim'}
                    </span>
                );
            case 'queued':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        <Clock className="h-3 w-3" />
                        Dalam Antrean
                    </span>
                );
            case 'processing':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        <RefreshCw className="h-3 w-3 animate-spin" />
                        Sedang Dikirim
                    </span>
                );
            case 'failed':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                        <AlertCircle className="h-3 w-3" />
                        Gagal
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        {deliveryStatus}
                    </span>
                );
        }
    };

    return (
        <AuthenticatedLayout header="Laporan Pengiriman Slip Gaji">
            <Head title="Laporan Pengiriman" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                            Laporan & Analitik Pengiriman
                        </h1>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Rekapitulasi riwayat pengiriman slip gaji karyawan melalui kanal Email dan WhatsApp.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.export && (
                            <button
                                type="button"
                                onClick={handleExportCsv}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Download className="h-3.5 w-3.5 text-indigo-600" />
                                <span>Ekspor Data (CSV)</span>
                            </button>
                        )}

                        {can.send && (
                            <Link
                                href={route('deliveries.bulk.create')}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition"
                            >
                                <Send className="h-3.5 w-3.5" />
                                <span>Pengiriman Massal</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Privacy Badge Notice */}
                {!canViewFinancial && (
                    <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 flex items-center gap-3 text-xs text-slate-600">
                        <ShieldAlert className="h-4 w-4 text-slate-400 shrink-0" />
                        <span>
                            Informasi nominal gaji (take home pay) disembunyikan berdasarkan pembatasan hak akses akun Anda. Ekspor CSV juga akan mematuhi pembatasan privasi ini.
                        </span>
                    </div>
                )}

                {/* Summary Metric Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Total Pengiriman</span>
                            <History className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono text-slate-900">
                            {summary.total_deliveries || 0}
                        </div>
                        <div className="mt-1 text-[11px] text-slate-400">
                            Email: {summary.email_total || 0} • WA: {summary.whatsapp_total || 0}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5 shadow-xs">
                        <div className="flex items-center justify-between text-emerald-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Tingkat Keberhasilan</span>
                            <TrendingUp className="h-4 w-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono text-emerald-950">
                            {summary.success_rate || 0}%
                        </div>
                        <div className="mt-1 text-[11px] text-emerald-700">
                            {(summary.delivered || 0) + (summary.sent || 0)} berhasil dari {summary.total_deliveries || 0}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 shadow-xs">
                        <div className="flex items-center justify-between text-indigo-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Terkirim (Sent)</span>
                            <Send className="h-4 w-4 text-indigo-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono text-indigo-950">
                            {summary.sent || 0}
                        </div>
                        <div className="mt-1 text-[11px] text-indigo-700">
                            Terkonfirmasi terkirim dari server
                        </div>
                    </div>

                    <div className="rounded-2xl border border-blue-100 bg-blue-50/40 p-5 shadow-xs">
                        <div className="flex items-center justify-between text-blue-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Diterima (DLR)</span>
                            <CheckCircle className="h-4 w-4 text-blue-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono text-blue-950">
                            {summary.delivered || 0}
                        </div>
                        <div className="mt-1 text-[11px] text-blue-700">
                            Bukti tanda terima valid
                        </div>
                    </div>

                    <div className="rounded-2xl border border-rose-100 bg-rose-50/40 p-5 shadow-xs col-span-2 lg:col-span-1">
                        <div className="flex items-center justify-between text-rose-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Gagal (Failed)</span>
                            <AlertCircle className="h-4 w-4 text-rose-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono text-rose-950">
                            {summary.failed || 0}
                        </div>
                        <div className="mt-1 text-[11px] text-rose-700">
                            Dalam antrean: {summary.queued || 0}
                        </div>
                    </div>
                </div>

                {/* Filter Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                    <form onSubmit={handleFilter} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Periode
                                </label>
                                <select
                                    value={periodId}
                                    onChange={(e) => setPeriodId(e.target.value)}
                                    className="w-full rounded-xl border-slate-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="all">Semua Periode</option>
                                    {periods.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            Periode {p.period_code}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Departemen
                                </label>
                                <select
                                    value={department}
                                    onChange={(e) => setDepartment(e.target.value)}
                                    className="w-full rounded-xl border-slate-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="all">Semua Departemen</option>
                                    {departmentOptions.map((dept) => (
                                        <option key={dept} value={dept}>
                                            {dept}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Kanal
                                </label>
                                <select
                                    value={channel}
                                    onChange={(e) => setChannel(e.target.value)}
                                    className="w-full rounded-xl border-slate-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="all">Semua Kanal</option>
                                    <option value="email">Email</option>
                                    <option value="whatsapp">WhatsApp</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Status
                                </label>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full rounded-xl border-slate-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="all">Semua Status</option>
                                    <option value="sent">Terkirim (Sent)</option>
                                    <option value="delivered">Diterima (Delivered)</option>
                                    <option value="failed">Gagal (Failed)</option>
                                    <option value="queued">Dalam Antrean</option>
                                    <option value="processing">Sedang Diproses</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Pencarian
                                </label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Nama, NIK, tujuan..."
                                        className="w-full rounded-xl border-slate-300 pl-8 pr-3 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={handleReset}
                                className="rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Reset Filter
                            </button>
                            <button
                                type="submit"
                                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 transition"
                            >
                                <Filter className="h-3 w-3" />
                                <span>Terapkan Filter</span>
                            </button>
                        </div>
                    </form>
                </div>

                {/* Table Data Card */}
                <div className="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-xs">
                            <thead className="bg-slate-50 font-bold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th className="px-4 py-3 text-left">No. Slip</th>
                                    <th className="px-4 py-3 text-left">Karyawan & NIK</th>
                                    <th className="px-4 py-3 text-left">Departemen</th>
                                    <th className="px-4 py-3 text-left">Kanal & Tujuan</th>
                                    <th className="px-4 py-3 text-left">Status</th>
                                    <th className="px-4 py-3 text-center">Percobaan</th>
                                    <th className="px-4 py-3 text-right">Take Home Pay</th>
                                    <th className="px-4 py-3 text-left">Waktu Terkirim</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {deliveries.data && deliveries.data.length > 0 ? (
                                    deliveries.data.map((d) => (
                                        <tr key={d.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-4 py-3 font-mono font-medium text-slate-800">
                                                <Link
                                                    href={route('payslips.show', d.payslip?.id)}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {d.payslip?.slip_number || '-'}
                                                </Link>
                                                <div className="text-[10px] text-slate-400">
                                                    Periode {d.payslip?.payroll_period?.period_code || '-'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-800">
                                                    {d.payslip?.employee_name_snapshot || '-'}
                                                </div>
                                                <div className="font-mono text-[10px] text-slate-400">
                                                    {d.payslip?.employee_number_snapshot || '-'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {d.payslip?.department_snapshot || '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    {d.channel === 'email' ? (
                                                        <Mail className="h-3.5 w-3.5 text-indigo-600 shrink-0" />
                                                    ) : (
                                                        <Smartphone className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                                                    )}
                                                    <span className="font-mono text-slate-800">
                                                        {d.recipient}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {getStatusBadge(d.status)}
                                                {d.last_error_summary && (
                                                    <p className="text-[10px] text-rose-600 mt-1 max-w-xs truncate" title={d.last_error_summary}>
                                                        {d.last_error_summary}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center font-mono font-semibold text-slate-700">
                                                {d.attempt_count}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-bold text-slate-800">
                                                {d.payslip?.display_salary || '[TERBATAS]'}
                                            </td>
                                            <td className="px-4 py-3 text-slate-500 font-mono text-[11px]">
                                                {d.sent_at
                                                    ? new Date(d.sent_at).toLocaleString('id-ID')
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="8" className="py-8 text-center text-slate-400">
                                            Tidak ada data pengiriman yang cocok dengan filter yang ditentukan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Links */}
                    {deliveries.links && deliveries.links.length > 3 && (
                        <div className="border-t border-slate-100 p-4 flex items-center justify-between bg-slate-50/50">
                            <span className="text-xs text-slate-500">
                                Menampilkan {deliveries.from || 0} sampai {deliveries.to || 0} dari {deliveries.total || 0} data
                            </span>
                            <div className="flex gap-1">
                                {deliveries.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`rounded-lg px-3 py-1 text-xs font-semibold transition ${
                                            link.active
                                                ? 'bg-[#0B57D0] text-white shadow-xs'
                                                : link.url
                                                ? 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                                                : 'text-slate-300 pointer-events-none'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
