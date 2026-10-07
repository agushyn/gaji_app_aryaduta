import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    FileText,
    Receipt,
    ShieldCheck,
    UserCheck,
} from 'lucide-react';

export default function ApprovalsIndex({ periods, metrics, currentTab, canDecide }) {
    const handleTabChange = (tab) => {
        router.get(
            route('approvals.index'),
            { status: tab },
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <AuthenticatedLayout
            title="Pusat Review & Persetujuan Penggajian"
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 leading-tight">
                            Pusat Review & Persetujuan
                        </h2>
                        <p className="text-xs text-slate-500 mt-1">
                            Verifikasi berkas penggajian, rincian penghasilan, dan otorisasi persetujuan manajerial.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Pusat Review & Persetujuan Penggajian" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Metric Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button
                        type="button"
                        onClick={() => handleTabChange('in_review')}
                        className={`text-left p-5 rounded-2xl border transition cursor-pointer shadow-xs ${
                            currentTab === 'in_review'
                                ? 'bg-amber-500/10 border-amber-500/30 ring-2 ring-amber-500/20'
                                : 'bg-white border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">
                                Menunggu Review
                            </span>
                            <span className="p-2 rounded-xl bg-amber-100 text-amber-800">
                                <Clock className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-slate-900 mt-3">
                            {metrics.in_review}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            Memerlukan verifikasi dan keputusan
                        </p>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('changes_requested')}
                        className={`text-left p-5 rounded-2xl border transition cursor-pointer shadow-xs ${
                            currentTab === 'changes_requested'
                                ? 'bg-rose-500/10 border-rose-500/30 ring-2 ring-rose-500/20'
                                : 'bg-white border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-rose-800">
                                Perlu Perbaikan
                            </span>
                            <span className="p-2 rounded-xl bg-rose-100 text-rose-800">
                                <AlertCircle className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-slate-900 mt-3">
                            {metrics.changes_requested}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            Dikembalikan ke personalia untuk revisi
                        </p>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('approved')}
                        className={`text-left p-5 rounded-2xl border transition cursor-pointer shadow-xs ${
                            currentTab === 'approved'
                                ? 'bg-emerald-500/10 border-emerald-500/30 ring-2 ring-emerald-500/20'
                                : 'bg-white border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-emerald-800">
                                Disetujui
                            </span>
                            <span className="p-2 rounded-xl bg-emerald-100 text-emerald-800">
                                <CheckCircle2 className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-slate-900 mt-3">
                            {metrics.approved}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            Siap untuk tahap finalisasi slip
                        </p>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('all')}
                        className={`text-left p-5 rounded-2xl border transition cursor-pointer shadow-xs ${
                            currentTab === 'all'
                                ? 'bg-blue-500/10 border-blue-500/30 ring-2 ring-blue-500/20'
                                : 'bg-white border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Semua Periode
                            </span>
                            <span className="p-2 rounded-xl bg-slate-100 text-slate-700">
                                <FileSpreadsheet className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-slate-900 mt-3">
                            {metrics.all}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            Seluruh arsip pengajuan penggajian
                        </p>
                    </button>
                </div>

                {/* Filter Navigation Tabs */}
                <div className="flex items-center gap-2 border-b border-slate-200 pb-3">
                    <button
                        type="button"
                        onClick={() => handleTabChange('in_review')}
                        className={`px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            currentTab === 'in_review'
                                ? 'bg-amber-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        }`}
                    >
                        Menunggu Review ({metrics.in_review})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTabChange('changes_requested')}
                        className={`px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            currentTab === 'changes_requested'
                                ? 'bg-rose-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        }`}
                    >
                        Perlu Perbaikan ({metrics.changes_requested})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTabChange('approved')}
                        className={`px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            currentTab === 'approved'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        }`}
                    >
                        Disetujui ({metrics.approved})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTabChange('all')}
                        className={`px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            currentTab === 'all'
                                ? 'bg-slate-800 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        }`}
                    >
                        Semua ({metrics.all})
                    </button>
                </div>

                {/* Periods Review Table */}
                <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead className="bg-slate-50 text-slate-700 text-xs font-semibold uppercase tracking-wider">
                                <tr>
                                    <th scope="col" className="px-6 py-3.5">
                                        Kode & Rentang Periode
                                    </th>
                                    <th scope="col" className="px-6 py-3.5">
                                        Jumlah Slip
                                    </th>
                                    <th scope="col" className="px-6 py-3.5">
                                        Total Take Home Pay
                                    </th>
                                    <th scope="col" className="px-6 py-3.5">
                                        Status
                                    </th>
                                    <th scope="col" className="px-6 py-3.5">
                                        Pengaju & Penyetuju
                                    </th>
                                    <th scope="col" className="px-6 py-3.5 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {periods.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <div className="max-w-sm mx-auto text-center space-y-3">
                                                <div className="h-12 w-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                                                    <FileText className="h-6 w-6" />
                                                </div>
                                                <p className="text-sm font-semibold text-slate-700">
                                                    Tidak ada periode yang ditemukan
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    Saat ini tidak ada data pengajuan dalam status tab ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    periods.data.map((period) => (
                                        <tr key={period.id} className="hover:bg-slate-50 transition">
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-slate-900">
                                                    {period.period_code}
                                                </div>
                                                <div className="text-xs text-slate-500 flex items-center gap-1.5 mt-0.5">
                                                    <Calendar className="h-3.5 w-3.5 text-slate-400" />
                                                    <span>
                                                        {period.start_date} s/d {period.end_date}
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                                                    <Receipt className="h-3.5 w-3.5 text-slate-500" />
                                                    <span>{period.payslips_count} Slip</span>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="font-bold text-slate-900">
                                                    {period.formatted_net_salary}
                                                </span>
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${period.badge_class}`}
                                                >
                                                    {period.status_label}
                                                </span>
                                            </td>

                                            <td className="px-6 py-4 text-xs text-slate-600">
                                                {period.submitter_name ? (
                                                    <div>
                                                        <div className="flex items-center gap-1 font-medium text-slate-800">
                                                            <UserCheck className="h-3.5 w-3.5 text-slate-400" />
                                                            <span>Diajukan: {period.submitter_name}</span>
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 mt-0.5">
                                                            {period.submitted_at}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Belum diajukan</span>
                                                )}

                                                {period.approver_name && (
                                                    <div className="mt-1 pt-1 border-t border-slate-100">
                                                        <div className="flex items-center gap-1 font-medium text-emerald-700">
                                                            <ShieldCheck className="h-3.5 w-3.5" />
                                                            <span>Disetujui: {period.approver_name}</span>
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 mt-0.5">
                                                            {period.approved_at}
                                                        </div>
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                                <div className="flex items-center justify-end gap-2">
                                                    <a
                                                        href={route('approvals.periods.export-excel', period.id)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-700 text-white text-xs font-semibold hover:bg-emerald-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 transition shadow-xs"
                                                        title="Export Rekap Payroll Excel (.xlsx)"
                                                    >
                                                        <FileSpreadsheet className="h-3.5 w-3.5" />
                                                        <span>Export Excel</span>
                                                    </a>
                                                    <Link
                                                        href={route('approvals.periods.show', period.id)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#0B57D0] text-white text-xs font-semibold hover:bg-[#0A58CA] focus:outline-hidden focus:ring-2 focus:ring-[#0B57D0] focus:ring-offset-2 transition"
                                                    >
                                                        <span>Tinjau Rincian</span>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
