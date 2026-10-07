import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle,
    Clock,
    Eye,
    FileSpreadsheet,
    Filter,
    History,
    Mail,
    RefreshCw,
    RotateCcw,
    Search,
    Send,
    ShieldCheck,
    Smartphone,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function DeliveriesIndex({ deliveries, periods, counts, filters, can }) {
    const { flash } = usePage().props;

    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [channel, setChannel] = useState(filters?.channel || 'all');
    const [periodId, setPeriodId] = useState(filters?.period_id || 'all');

    const [selectedDelivery, setSelectedDelivery] = useState(null);
    const [retryProcessingId, setRetryProcessingId] = useState(null);
    const [retryingAll, setRetryingAll] = useState(false);

    const handleRetryAllFailed = () => {
        if (!confirm('Kirim ulang seluruh pengiriman yang gagal pada filter saat ini?')) {
            return;
        }

        setRetryingAll(true);
        router.post(
            route('deliveries.retry-failed'),
            {
                period_id: periodId,
                channel: channel,
            },
            {
                preserveScroll: true,
                onFinish: () => setRetryingAll(false),
            }
        );
    };

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(
            route('deliveries.index'),
            { search, status, channel, period_id: periodId },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        setChannel('all');
        setPeriodId('all');
        router.get(route('deliveries.index'), {}, { preserveState: true, replace: true });
    };

    const handleRetry = (delivery) => {
        if (!confirm(`Kirim ulang slip gaji ${delivery.payslip?.slip_number} ke email ${delivery.recipient}?`)) {
            return;
        }

        setRetryProcessingId(delivery.id);
        router.post(
            route('deliveries.retry', delivery.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRetryProcessingId(null),
            }
        );
    };

    const getStatusBadge = (deliveryStatus) => {
        switch (deliveryStatus) {
            case 'sent':
            case 'delivered':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <CheckCircle className="h-3 w-3" />
                        Terkirim
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
        <AuthenticatedLayout header="Pelacakan Pengiriman Slip Gaji">
            <Head title="Pelacakan Pengiriman" />

            <div className="space-y-6">
                {/* Header Title */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <Send className="h-6 w-6 text-indigo-600" />
                            Pelacakan & Riwayat Pengiriman
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Pantau status antrean, keberhasilan pengiriman email PDF slip gaji, serta riwayat percobaan perorangan.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {can.retry && counts.failed > 0 && (
                            <button
                                type="button"
                                disabled={retryingAll}
                                onClick={handleRetryAllFailed}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 shadow-xs transition disabled:opacity-50"
                            >
                                <RotateCcw className={`h-3.5 w-3.5 ${retryingAll ? 'animate-spin' : ''}`} />
                                <span>Retry Semua Gagal ({counts.failed})</span>
                            </button>
                        )}

                        <Link
                            href={route('reports.deliveries.index')}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition"
                        >
                            <FileSpreadsheet className="h-3.5 w-3.5 text-indigo-600" />
                            <span>Laporan</span>
                        </Link>

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

                {/* Status Banners */}
                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-sm flex items-start gap-3">
                        <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Berhasil</p>
                            <p className="text-xs text-emerald-700 mt-0.5">{flash.success}</p>
                        </div>
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-sm flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Perhatian</p>
                            <p className="text-xs text-rose-700 mt-0.5">{flash.error}</p>
                        </div>
                    </div>
                )}

                {/* Metric Summary Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <span className="text-xs font-medium text-slate-500 block">Total Pengiriman</span>
                        <span className="mt-1 text-2xl font-bold text-slate-900 block">{counts.total || 0}</span>
                    </div>

                    <div className="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 shadow-xs">
                        <span className="text-xs font-medium text-blue-700 block">Dalam Antrean</span>
                        <span className="mt-1 text-2xl font-bold text-blue-800 block">{counts.queued || 0}</span>
                    </div>

                    <div className="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-xs">
                        <span className="text-xs font-medium text-amber-700 block">Sedang Diproses</span>
                        <span className="mt-1 text-2xl font-bold text-amber-800 block">{counts.processing || 0}</span>
                    </div>

                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs">
                        <span className="text-xs font-medium text-emerald-700 block">Berhasil Terkirim</span>
                        <span className="mt-1 text-2xl font-bold text-emerald-800 block">{counts.sent || 0}</span>
                    </div>

                    <div className="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 shadow-xs">
                        <span className="text-xs font-medium text-rose-700 block">Gagal Terkirim</span>
                        <span className="mt-1 text-2xl font-bold text-rose-800 block">{counts.failed || 0}</span>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-5 gap-3">
                        <div className="sm:col-span-2 relative">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama, NIK, no slip, atau email..."
                                className="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="all">Semua Status</option>
                                <option value="queued">Dalam Antrean</option>
                                <option value="processing">Sedang Diproses</option>
                                <option value="sent">Berhasil Terkirim</option>
                                <option value="failed">Gagal Terkirim</option>
                            </select>
                        </div>

                        <div>
                            <select
                                value={periodId}
                                onChange={(e) => setPeriodId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="all">Semua Periode</option>
                                {periods.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.period_code} ({p.status})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0B57D0] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0A58CA] transition"
                            >
                                <Filter className="h-3.5 w-3.5" />
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={handleReset}
                                className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                {/* Deliveries Table */}
                <div className="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-50 border-b border-slate-200 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3.5">Slip & Periode</th>
                                    <th className="px-4 py-3.5">Karyawan</th>
                                    <th className="px-4 py-3.5">Saluran & Tujuan</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5 text-center">Percobaan</th>
                                    <th className="px-4 py-3.5">Waktu Update</th>
                                    <th className="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {deliveries.data && deliveries.data.length > 0 ? (
                                    deliveries.data.map((delivery) => (
                                        <tr key={delivery.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-900">
                                                    {delivery.payslip?.slip_number}
                                                </div>
                                                <div className="text-[11px] text-slate-400 mt-0.5">
                                                    Periode: {delivery.payslip?.payroll_period?.period_code || '-'}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-900">
                                                    {delivery.payslip?.employee_name_snapshot}
                                                </div>
                                                <div className="text-[11px] text-slate-400">
                                                    NIK: {delivery.payslip?.employee_number_snapshot}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-1.5 font-medium text-slate-800">
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] uppercase font-bold">
                                                        {delivery.channel}
                                                    </span>
                                                    <span className="truncate max-w-[180px]">{delivery.recipient}</span>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3.5">
                                                <div>{getStatusBadge(delivery.status)}</div>
                                                {delivery.last_error_summary && (
                                                    <p
                                                        className="mt-1 text-[11px] text-rose-600 truncate max-w-[200px]"
                                                        title={delivery.last_error_summary}
                                                    >
                                                        {delivery.last_error_summary}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-4 py-3.5 text-center">
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                                                        delivery.attempt_count >= 3
                                                            ? 'bg-rose-100 text-rose-800'
                                                            : 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {delivery.attempt_count} / 3
                                                </span>
                                            </td>

                                            <td className="px-4 py-3.5 text-[11px] text-slate-500">
                                                {delivery.sent_at ? (
                                                    <div>
                                                        <span className="font-medium text-slate-700">Terkirim:</span>
                                                        <div>{new Date(delivery.sent_at).toLocaleString('id-ID')}</div>
                                                    </div>
                                                ) : delivery.last_attempt_at ? (
                                                    <div>
                                                        <span className="font-medium text-slate-700">Coba:</span>
                                                        <div>{new Date(delivery.last_attempt_at).toLocaleString('id-ID')}</div>
                                                    </div>
                                                ) : (
                                                    <div>{new Date(delivery.updated_at).toLocaleString('id-ID')}</div>
                                                )}
                                            </td>

                                            <td className="px-4 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedDelivery(delivery)}
                                                        className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 transition"
                                                        title="Lihat Riwayat Percobaan"
                                                    >
                                                        <History className="h-3.5 w-3.5 text-slate-500" />
                                                        Riwayat ({delivery.attempts?.length || 0})
                                                    </button>

                                                    {delivery.status === 'failed' && can.retry && (
                                                        <button
                                                            type="button"
                                                            disabled={retryProcessingId === delivery.id}
                                                            onClick={() => handleRetry(delivery)}
                                                            className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition disabled:opacity-50"
                                                            title="Kirim Ulang (Retry)"
                                                        >
                                                            <RotateCcw
                                                                className={`h-3.5 w-3.5 ${
                                                                    retryProcessingId === delivery.id ? 'animate-spin' : ''
                                                                }`}
                                                            />
                                                            Retry
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-10 text-center text-slate-400">
                                            Tidak ada riwayat pengiriman slip gaji yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {deliveries?.links && deliveries.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Menampilkan {deliveries.from || 0} - {deliveries.to || 0} dari {deliveries.total} pengiriman
                            </span>
                            <div className="flex gap-1">
                                {deliveries.links.map((link, idx) => (
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
            </div>

            {/* Attempt Details Modal */}
            {selectedDelivery && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
                    aria-modal="true"
                    role="dialog"
                >
                    <div className="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                        <div className="flex items-start justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <History className="h-5 w-5 text-indigo-600" />
                                    Riwayat Percobaan Pengiriman
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Slip: <strong>{selectedDelivery.payslip?.slip_number}</strong> | Penerima:{' '}
                                    <strong>{selectedDelivery.recipient}</strong>
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedDelivery(null)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Tutup"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Privacy Banner */}
                        <div className="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 flex items-center gap-2">
                            <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0" />
                            <span>
                                Log disanitasi: Bebas dari password, kredensial SMTP, lampiran, dan rincian finansial gaji.
                            </span>
                        </div>

                        {/* Attempt List */}
                        <div className="max-h-80 overflow-y-auto space-y-3 pr-1">
                            {selectedDelivery.attempts && selectedDelivery.attempts.length > 0 ? (
                                selectedDelivery.attempts.map((attempt) => (
                                    <div
                                        key={attempt.id}
                                        className="rounded-xl border border-slate-200 p-3.5 text-xs space-y-2 bg-white"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-slate-800">
                                                    Percobaan #{attempt.attempt_number}
                                                </span>
                                                {getStatusBadge(attempt.status)}
                                            </div>
                                            <span className="text-[11px] text-slate-400">
                                                {attempt.started_at
                                                    ? new Date(attempt.started_at).toLocaleString('id-ID')
                                                    : '-'}
                                            </span>
                                        </div>

                                        {attempt.error_code && (
                                            <div className="rounded-lg bg-rose-50 border border-rose-100 p-2.5 text-rose-800 space-y-1">
                                                <div className="font-mono text-[11px] font-semibold text-rose-900">
                                                    Kode Error: {attempt.error_code}
                                                </div>
                                                <div className="text-xs text-rose-700">
                                                    {attempt.error_summary || 'Tidak ada deskripsi kesalahan tambahan.'}
                                                </div>
                                            </div>
                                        )}

                                        {attempt.provider_message_id && (
                                            <div className="text-[11px] text-slate-500 font-mono">
                                                Message ID: {attempt.provider_message_id}
                                            </div>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <div className="py-6 text-center text-xs text-slate-400">
                                    Belum ada catatan log percobaan untuk pengiriman ini.
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setSelectedDelivery(null)}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
