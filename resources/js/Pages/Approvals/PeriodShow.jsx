import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    FileSpreadsheet,
    FileText,
    History,
    Receipt,
    ShieldAlert,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function ApprovalPeriodShow({
    period,
    payslips,
    events,
    canApprove,
    canRequestChanges,
    canSubmit,
    canFinalize,
    isCreatorOrSubmitter,
    segregationEnforced,
}) {
    const [approveModalOpen, setApproveModalOpen] = useState(false);
    const [changesModalOpen, setChangesModalOpen] = useState(false);
    const [submitModalOpen, setSubmitModalOpen] = useState(false);

    // Form for Approve
    const approveForm = useForm({
        comment: '',
    });

    // Form for Request Changes (Mandatory Reason)
    const changesForm = useForm({
        comment: '',
    });

    // Form for Submit
    const submitForm = useForm({
        notes: '',
    });

    const handleApproveSubmit = (e) => {
        e.preventDefault();
        approveForm.post(route('approvals.periods.approve', period.id), {
            onSuccess: () => {
                setApproveModalOpen(false);
                approveForm.reset();
            },
        });
    };

    const handleChangesSubmit = (e) => {
        e.preventDefault();
        changesForm.post(route('approvals.periods.request-changes', period.id), {
            onSuccess: () => {
                setChangesModalOpen(false);
                changesForm.reset();
            },
        });
    };

    const handleSubmitForReview = (e) => {
        e.preventDefault();
        submitForm.post(route('approvals.periods.submit', period.id), {
            onSuccess: () => {
                setSubmitModalOpen(false);
                submitForm.reset();
            },
        });
    };

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    return (
        <AuthenticatedLayout
            title={`Review Penggajian Periode ${period.period_code}`}
            header={`Review Periode: ${period.period_code}`}
        >
            <Head title={`Review Penggajian: ${period.period_code}`} />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('approvals.index')}
                            className="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition shadow-xs shrink-0"
                            title="Kembali ke Daftar Review"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                    Review Periode: {period.period_code}
                                </h1>
                                <span
                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${period.badge_class}`}
                                >
                                    {period.status_label}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                <Calendar className="h-3.5 w-3.5 shrink-0" />
                                <span>
                                    Rentang: {period.start_date} s/d {period.end_date}
                                </span>
                                <span>•</span>
                                <span>{period.payslips_count} berkas slip gaji terdaftar</span>
                            </p>
                        </div>
                    </div>

                    {/* Action Decision Buttons */}
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={route('approvals.periods.export-excel', period.id)}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-700 text-white text-xs font-semibold hover:bg-emerald-800 transition shadow-xs"
                            title="Export Rekap Payroll Excel (.xlsx)"
                        >
                            <FileSpreadsheet className="h-4 w-4" />
                            <span>Export Excel</span>
                        </a>

                        {canSubmit && (
                            <button
                                type="button"
                                onClick={() => setSubmitModalOpen(true)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0B57D0] text-white text-xs font-semibold hover:bg-[#0A58CA] transition shadow-xs"
                            >
                                <UserCheck className="h-4 w-4" />
                                <span>Ajukan untuk Review</span>
                            </button>
                        )}

                        {canRequestChanges && (
                            <button
                                type="button"
                                onClick={() => setChangesModalOpen(true)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 transition shadow-xs"
                            >
                                <AlertCircle className="h-4 w-4" />
                                <span>Minta Perbaikan (Reject/Revisi)</span>
                            </button>
                        )}

                        {canApprove && (
                            <button
                                type="button"
                                onClick={() => setApproveModalOpen(true)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition shadow-xs"
                            >
                                <ShieldCheck className="h-4 w-4" />
                                <span>Setujui Periode (Approve)</span>
                            </button>
                        )}

                        {canFinalize && period.status === 'approved' && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (
                                        confirm(
                                            `Apakah Anda yakin ingin memfinalisasi seluruh slip gaji pada periode ${period.period_code}? Tindakan ini akan mengunci seluruh slip dan membuat dokumen PDF privat berstempel SHA-256.`
                                        )
                                    ) {
                                        router.post(route('payroll-periods.finalize', period.id));
                                    }
                                }}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition shadow-xs"
                            >
                                <CheckCircle2 className="h-4 w-4 text-emerald-400" />
                                <span>Finalisasi Seluruh Periode & Buat PDF</span>
                            </button>
                        )}

                        {period.status === 'finalized' && (
                            <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                <span>Final ({period.finalizer_name || 'Petugas'} - {period.finalized_at})</span>
                            </div>
                        )}
                    </div>
                </div>
                {/* Segregation of Duties Warning Banner */}
                {isCreatorOrSubmitter && segregationEnforced && period.status === 'in_review' && (
                    <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3">
                        <ShieldAlert className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                        <div className="text-xs space-y-1">
                            <p className="font-semibold text-amber-950">
                                Penegakan Pemisahan Tugas Aktif (Segregation of Duties)
                            </p>
                            <p className="text-amber-800">
                                Anda tercatat sebagai pembuat atau pengaju berkas penggajian periode ini. Sesuai prinsip audit internal dan kepatuhan finansial, persetujuan manajerial (approval) hanya dapat dilakukan oleh approver yang berbeda.
                            </p>
                        </div>
                    </div>
                )}

                {/* Financial Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="p-5 rounded-xl bg-white border border-slate-200">
                        <div className="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase tracking-wider">
                            <span>Total Gaji Pokok</span>
                            <span className="p-2 rounded-lg bg-slate-100 text-slate-600">
                                <DollarSign className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-xl font-bold text-slate-900 mt-2">
                            {formatRupiah(period.total_basic)}
                        </p>
                        <p className="text-xs text-slate-400 mt-1">Gaji dasar seluruh karyawan</p>
                    </div>

                    <div className="p-5 rounded-xl bg-white border border-slate-200">
                        <div className="flex items-center justify-between text-emerald-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Total Pendapatan</span>
                            <span className="p-2 rounded-lg bg-emerald-100 text-emerald-700">
                                <TrendingUp className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-xl font-bold text-emerald-700 mt-2">
                            {formatRupiah(period.total_earnings)}
                        </p>
                        <p className="text-xs text-slate-400 mt-1">Akumulasi tunjangan & bonus</p>
                    </div>

                    <div className="p-5 rounded-xl bg-white border border-slate-200">
                        <div className="flex items-center justify-between text-rose-700 text-xs font-semibold uppercase tracking-wider">
                            <span>Total Potongan</span>
                            <span className="p-2 rounded-lg bg-rose-100 text-rose-700">
                                <TrendingDown className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-xl font-bold text-rose-700 mt-2">
                            {formatRupiah(period.total_deductions)}
                        </p>
                        <p className="text-xs text-slate-400 mt-1">BPJS, pajak, dan potongan kas</p>
                    </div>

                    <div className="p-5 rounded-xl bg-indigo-50/70 border border-indigo-200">
                        <div className="flex items-center justify-between text-indigo-800 text-xs font-semibold uppercase tracking-wider">
                            <span>Total Take Home Pay</span>
                            <span className="p-2 rounded-lg bg-indigo-100 text-indigo-700">
                                <Receipt className="h-4 w-4" />
                            </span>
                        </div>
                        <p className="text-xl font-black text-indigo-900 mt-2">
                            {formatRupiah(period.total_net)}
                        </p>
                        <p className="text-xs text-indigo-700/80 mt-1">Total pengeluaran gaji bersih</p>
                    </div>
                </div>

                {/* Submitter & Approver Metadata Info */}
                <div className="bg-white rounded-xl border border-slate-200 p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span className="text-slate-400 block font-medium">Pembuat Dokumen</span>
                        <span className="font-semibold text-slate-800 mt-0.5 block">
                            {period.creator_name || 'Sistem'}
                        </span>
                    </div>

                    <div>
                        <span className="text-slate-400 block font-medium">Pengaju Review</span>
                        {period.submitter_name ? (
                            <div>
                                <span className="font-semibold text-slate-800 mt-0.5 block">
                                    {period.submitter_name}
                                </span>
                                <span className="text-slate-500 text-[11px]">
                                    Waktu: {period.submitted_at}
                                </span>
                            </div>
                        ) : (
                            <span className="text-slate-400 italic">Belum diajukan</span>
                        )}
                    </div>

                    <div>
                        <span className="text-slate-400 block font-medium">Persetujuan Terakhir</span>
                        {period.approver_name ? (
                            <div>
                                <span className="font-semibold text-emerald-700 mt-0.5 block">
                                    {period.approver_name}
                                </span>
                                <span className="text-slate-500 text-[11px]">
                                    Waktu: {period.approved_at}
                                </span>
                            </div>
                        ) : (
                            <span className="text-slate-400 italic">Belum disetujui</span>
                        )}
                    </div>
                </div>

                {/* Detailed Payslips Listing */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
                    <div className="p-5 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900">
                                Rincian Slip Gaji Karyawan ({payslips.length})
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Seluruh berkas perhitungan gaji yang diajukan dalam periode ini.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead className="bg-slate-50 text-slate-700 text-xs font-semibold uppercase tracking-wider">
                                <tr>
                                    <th scope="col" className="px-6 py-3">
                                        Karyawan & NIK
                                    </th>
                                    <th scope="col" className="px-6 py-3">
                                        Nomor Slip
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right">
                                        Gaji Pokok
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right">
                                        Pendapatan
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right">
                                        Potongan
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right">
                                        Take Home Pay
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-center">
                                        Status
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {payslips.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-10 text-center text-slate-500 text-xs">
                                            Belum ada slip gaji yang dibuat untuk periode ini.
                                        </td>
                                    </tr>
                                ) : (
                                    payslips.map((slip) => (
                                        <tr key={slip.id} className="hover:bg-slate-50 transition">
                                            <td className="px-6 py-3.5">
                                                <div className="font-semibold text-slate-900">
                                                    {slip.employee_name}
                                                </div>
                                                <div className="text-xs text-slate-500 font-mono">
                                                    NIK: {slip.employee_number} • {slip.department || 'Umum'}
                                                </div>
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap text-xs font-mono text-slate-600">
                                                {slip.slip_number}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap text-xs text-slate-600">
                                                {formatRupiah(slip.basic_salary)}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap text-xs font-semibold text-emerald-700">
                                                +{formatRupiah(slip.total_earnings)}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap text-xs font-semibold text-rose-700">
                                                -{formatRupiah(slip.total_deductions)}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap text-xs font-black text-slate-900">
                                                {slip.formatted_net_salary}
                                            </td>

                                            <td className="px-6 py-3.5 text-center whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ${slip.badge_class}`}
                                                >
                                                    {slip.status_label}
                                                </span>
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                <Link
                                                    href={route('payslips.show', slip.id)}
                                                    className="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Lihat Rincian
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Deliberation History Timeline (Approval Events) */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
                    <div className="flex items-center gap-2 border-b border-slate-200 pb-3 mb-4">
                        <History className="h-5 w-5 text-indigo-600" />
                        <h3 className="text-sm font-bold text-slate-900">
                            Riwayat Keputusan & Audit Trail Approval ({events.length})
                        </h3>
                    </div>

                    {events.length === 0 ? (
                        <p className="text-xs text-slate-400 italic">
                            Belum ada riwayat peristiwa alur persetujuan pada periode ini.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {events.map((evt) => (
                                <div
                                    key={evt.id}
                                    className="p-4 rounded-xl border border-slate-100 bg-slate-50 flex flex-col sm:flex-row sm:items-start justify-between gap-3 text-xs"
                                >
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`inline-flex items-center px-2 py-0.5 rounded-md font-semibold text-[11px] border ${evt.badge_class}`}
                                            >
                                                {evt.decision_label}
                                            </span>
                                            <span className="font-semibold text-slate-800">
                                                Oleh: {evt.actor_name}
                                            </span>
                                            {evt.payslip_id && (
                                                <span className="text-slate-500 font-mono text-[11px]">
                                                    (Slip #{evt.payslip_id})
                                                </span>
                                            )}
                                        </div>

                                        {evt.comment && (
                                            <div className="p-2.5 rounded-lg bg-white border border-slate-200 text-slate-700 mt-2 text-xs">
                                                <span className="font-semibold block text-slate-500 text-[10px] uppercase">
                                                    Catatan / Alasan:
                                                </span>
                                                {evt.comment}
                                            </div>
                                        )}
                                    </div>

                                    <div className="text-[11px] text-slate-400 whitespace-nowrap flex items-center gap-1">
                                        <Clock className="h-3.5 w-3.5" />
                                        <span>{evt.created_at}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Modal: Setujui Periode (Approve) */}
            {approveModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-lg bg-emerald-100 text-emerald-700">
                                    <ShieldCheck className="h-5 w-5" />
                                </span>
                                <h3 className="text-base font-bold text-slate-900">
                                    Setujui Periode Penggajian
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setApproveModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <p className="text-xs text-slate-600 leading-relaxed">
                            Apakah Anda yakin ingin menyetujui periode penggajian <strong>{period.period_code}</strong>? Seluruh slip gaji ({period.payslips_count} slip) yang berstatus Menunggu Review di dalamnya akan otomatis disetujui.
                        </p>

                        <form onSubmit={handleApproveSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Catatan Persetujuan (Opsional)
                                </label>
                                <textarea
                                    rows={3}
                                    value={approveForm.data.comment}
                                    onChange={(e) => approveForm.setData('comment', e.target.value)}
                                    placeholder="Tuliskan catatan verifikasi finansial bila diperlukan..."
                                    className="w-full text-xs rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setApproveModalOpen(false)}
                                    className="px-4 py-2 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={approveForm.processing}
                                    className="px-4 py-2 rounded-lg bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-xs"
                                >
                                    {approveForm.processing ? 'Memproses...' : 'Ya, Setujui Periode'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Minta Perbaikan (Request Changes) - Mandatory Reason */}
            {changesModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-lg bg-rose-100 text-rose-700">
                                    <AlertCircle className="h-5 w-5" />
                                </span>
                                <h3 className="text-base font-bold text-slate-900">
                                    Minta Perbaikan / Revisi
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setChangesModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <p className="text-xs text-slate-600 leading-relaxed">
                            Periode <strong>{period.period_code}</strong> beserta slip terkait akan dikembalikan ke personalia dengan status <em>Perlu Perbaikan</em>. Anda wajib memberikan alasan yang jelas agar personalia dapat memperbaikinya.
                        </p>

                        <form onSubmit={handleChangesSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Alasan Permintaan Perbaikan <span className="text-rose-600">*</span>
                                </label>
                                <textarea
                                    rows={4}
                                    required
                                    value={changesForm.data.comment}
                                    onChange={(e) => changesForm.setData('comment', e.target.value)}
                                    placeholder="Contoh: Nominal tunjangan makan karyawan X tidak sesuai rekapan presensi..."
                                    className="w-full text-xs rounded-lg border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                                />
                                {changesForm.errors.comment && (
                                    <p className="text-xs text-rose-600 mt-1 font-medium">
                                        {changesForm.errors.comment}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setChangesModalOpen(false)}
                                    className="px-4 py-2 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={changesForm.processing}
                                    className="px-4 py-2 rounded-lg bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50 transition shadow-xs"
                                >
                                    {changesForm.processing ? 'Mengirimkan...' : 'Kirim Permintaan Perbaikan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Ajukan untuk Review (Submit) */}
            {submitModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-lg bg-indigo-100 text-indigo-700">
                                    <UserCheck className="h-5 w-5" />
                                </span>
                                <h3 className="text-base font-bold text-slate-900">
                                    Ajukan Periode untuk Review
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSubmitModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <p className="text-xs text-slate-600 leading-relaxed">
                            Apakah seluruh data slip gaji periode <strong>{period.period_code}</strong> telah lengkap dan siap ditinjau oleh Approver? Status periode dan seluruh slip di dalamnya akan berganti menjadi <em>Menunggu Review</em>.
                        </p>

                        <form onSubmit={handleSubmitForReview} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Catatan Pengajuan (Opsional)
                                </label>
                                <textarea
                                    rows={3}
                                    value={submitForm.data.notes}
                                    onChange={(e) => submitForm.setData('notes', e.target.value)}
                                    placeholder="Tuliskan catatan pengajuan untuk approver..."
                                    className="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setSubmitModalOpen(false)}
                                    className="px-4 py-2 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submitForm.processing}
                                    className="px-4 py-2 rounded-xl bg-[#0B57D0] text-xs font-semibold text-white hover:bg-[#0A58CA] disabled:opacity-50 transition shadow-xs"
                                >
                                    {submitForm.processing ? 'Mengajukan...' : 'Ajukan Sekarang'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
