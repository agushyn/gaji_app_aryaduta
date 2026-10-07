import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Ban,
    Briefcase,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Edit3,
    ExternalLink,
    FileCheck,
    FileText,
    History,
    Mail,
    Phone,
    Receipt,
    RotateCcw,
    Send,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';

export default function PayslipsShow({ payslip, auditLogs = [], can = {} }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [voidModalOpen, setVoidModalOpen] = useState(false);
    const [sendingEmail, setSendingEmail] = useState(false);
    const [sendingWhatsApp, setSendingWhatsApp] = useState(false);

    const emailDelivery = payslip.deliveries?.find((d) => d.channel === 'email') || null;
    const whatsAppDelivery = payslip.deliveries?.find((d) => d.channel === 'whatsapp') || null;

    const voidForm = useForm({
        reason: '',
    });

    const handleSendEmail = () => {
        if (!confirm(`Kirim slip gaji ${payslip.slip_number} ke email karyawan (${payslip.email_snapshot || payslip.employee?.email})?`)) {
            return;
        }

        setSendingEmail(true);
        router.post(
            route('payslips.deliveries.email', payslip.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingEmail(false),
            }
        );
    };

    const handleRetryEmail = (deliveryId) => {
        if (!confirm(`Kirim ulang slip gaji ${payslip.slip_number} ke email karyawan?`)) {
            return;
        }

        setSendingEmail(true);
        router.post(
            route('deliveries.retry', deliveryId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingEmail(false),
            }
        );
    };

    const handleSendWhatsApp = () => {
        if (!confirm(`Kirim slip gaji ${payslip.slip_number} via WhatsApp ke karyawan (${payslip.phone_snapshot || payslip.employee?.phone})?`)) {
            return;
        }

        setSendingWhatsApp(true);
        router.post(
            route('payslips.deliveries.whatsapp', payslip.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingWhatsApp(false),
            }
        );
    };

    const handleRetryWhatsApp = (deliveryId) => {
        if (!confirm(`Kirim ulang slip gaji ${payslip.slip_number} via WhatsApp ke karyawan?`)) {
            return;
        }

        setSendingWhatsApp(true);
        router.post(
            route('deliveries.retry', deliveryId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingWhatsApp(false),
            }
        );
    };

    const handleFinalize = () => {
        if (
            confirm(
                `Apakah Anda yakin ingin memfinalisasi slip gaji ${payslip.slip_number} (${payslip.employee_name_snapshot})? Tindakan ini akan mengunci dokumen dan menghasilkan berkas PDF privat berstempel SHA-256.`
            )
        ) {
            router.post(route('payslips.finalize', payslip.id));
        }
    };

    const handleVoidSubmit = (e) => {
        e.preventDefault();
        voidForm.post(route('payslips.void', payslip.id), {
            onSuccess: () => {
                setVoidModalOpen(false);
                voidForm.reset();
            },
        });
    };

    const handleDelete = () => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus slip gaji ${payslip.slip_number} (${payslip.employee_name_snapshot})?`
            )
        ) {
            router.delete(route('payslips.destroy', payslip.id));
        }
    };

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    const formatSlipAmount = (value) => {
        if (value === null || value === undefined || value === '' || value === '-' || value === 0 || value === '0' || value === '0.00' || Number(value) === 0) {
            return '-';
        }
        const num = Number(value);
        if (isNaN(num)) return '-';
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    };

    const getStatusBadge = (slipStatus) => {
        const badges = {
            draft: { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-200' },
            pending_review: { label: 'Menunggu Review', class: 'bg-amber-50 text-amber-800 border-amber-200' },
            changes_requested: { label: 'Perlu Revisi', class: 'bg-rose-50 text-rose-800 border-rose-200' },
            approved: { label: 'Disetujui', class: 'bg-indigo-50 text-indigo-800 border-indigo-200' },
            finalized: { label: 'Final', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
            voided: { label: 'Dibatalkan', class: 'bg-rose-100 text-rose-800 border-rose-300' },
        };
        const current = badges[slipStatus] || { label: slipStatus, class: 'bg-slate-100 text-slate-700' };

        return (
            <span
                className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold border ${current.class}`}
            >
                {current.label}
            </span>
        );
    };

    const STANDARD_EARNINGS_TEMPLATE = [
        { code: 'BASIC_SALARY', name: 'Gaji Pokok', matchCodes: ['BASIC_SALARY', 'GAJI_POKOK', 'GAPOK'], keywords: ['GAJI POKOK'] },
        { code: 'ATTENDANCE_INCENTIVE', name: 'Tunjangan Kehadiran', matchCodes: ['ATTENDANCE_INCENTIVE', 'ATTENDANCE', 'KEHADIRAN', 'TUNJ_KEHADIRAN'], keywords: ['KEHADIRAN'] },
        { code: 'FAMILY_ALLOWANCE', name: 'Tunjangan Keluarga', matchCodes: ['FAMILY_ALLOWANCE', 'FAMILY', 'TUNJ_KELUARGA'], keywords: ['KELUARGA'] },
        { code: 'POSITION_ALLOWANCE', name: 'Tunjangan Jabatan', matchCodes: ['POSITION_ALLOWANCE', 'POSITION', 'TUNJ_JABATAN', 'JABATAN'], keywords: ['JABATAN'] },
        { code: 'MEAL_ALLOWANCE', name: 'Uang Makan', matchCodes: ['MEAL_ALLOWANCE', 'MEAL', 'UANG_MAKAN'], keywords: ['MAKAN'] },
        { code: 'TRANSPORT_ALLOWANCE', name: 'Transport', matchCodes: ['TRANSPORT_ALLOWANCE', 'TRANSPORT', 'UANG_TRANSPORT', 'TRANSPORTASI'], keywords: ['TRANSPORT'] },
        { code: 'OVERTIME', name: 'Lembur', matchCodes: ['OVERTIME', 'LEMBUR', 'UANG_LEMBUR'], keywords: ['LEMBUR'] },
        { code: 'FUNCTIONAL_ALLOWANCE', name: 'Tunjangan Fungsional', matchCodes: ['FUNCTIONAL_ALLOWANCE', 'FUNCTIONAL', 'TUNJ_FUNGSIONAL'], keywords: ['FUNGSIONAL'] },
        { code: 'COMMUNICATION_ALLOWANCE', name: 'Tunjangan Pulsa', matchCodes: ['COMMUNICATION_ALLOWANCE', 'PHONE', 'PULSA', 'TUNJ_PULSA'], keywords: ['PULSA', 'KOMUNIKASI'] },
        { code: 'HEALTH_ALLOWANCE', name: 'Biaya Kesehatan', matchCodes: ['HEALTH_ALLOWANCE', 'HEALTH_ALLOW', 'BIAYA_KESEHATAN', 'KESEHATAN'], keywords: ['KESEHATAN'] },
    ];

    const STANDARD_DEDUCTIONS_TEMPLATE = [
        { code: 'BPJS_TK', name: 'BPJS Ketenagakerjaan', matchCodes: ['BPJS_TK', 'BPJS_KETENAGAKERJAAN', 'POT_BPJS_TK'], keywords: ['BPJS TK', 'KETENAGAKERJAAN'] },
        { code: 'BPJS_KES', name: 'BPJS Kesehatan', matchCodes: ['BPJS_KES', 'BPJS_KESEHATAN', 'POT_BPJS_KES'], keywords: ['BPJS KES'] },
        { code: 'ATTENDANCE_CUT_SIC', name: 'Potongan Kehadiran (S/I/C)', matchCodes: ['ATT_SIC', 'ATTENDANCE_CUT_SIC', 'POT_ABSEN'], keywords: ['S,I,C', 'S/I/C', 'ABSEN'] },
        { code: 'ALPHA_CUT', name: 'Potongan Alpa', matchCodes: ['ATT_ALPA', 'ALPHA_CUT', 'POT_ALPA'], keywords: ['ALPA'] },
        { code: 'KOPERASI_MANDATORY', name: 'Iuran Koperasi', matchCodes: ['KOP_DUES', 'KOPERASI_MANDATORY', 'IURAN_KOPERASI'], keywords: ['IURAN KOPERASI'] },
        { code: 'KOPERASI_LOAN', name: 'Pinjaman Koperasi', matchCodes: ['KOP_LOAN', 'KOPERASI_LOAN', 'PINJAMAN_KOPERASI'], keywords: ['PINJAMAN KOPERASI'] },
        { code: 'LOCAL_LOAN', name: 'Pinjaman Lokal', matchCodes: ['LOCAL_LOAN', 'PINJAMAN_LOKAL', 'PINJAMAN'], keywords: ['PINJAMAN'] },
        { code: 'VEHICLE_INSTALLMENT', name: 'Angsuran Kendaraan', matchCodes: ['VEHICLE_INST', 'VEHICLE_INSTALLMENT', 'ANGSURAN_KENDARAAN'], keywords: ['KENDARAAN', 'ANGSURAN'] },
        { code: 'OFF_DAYS_CUT', name: 'Potongan Jumlah Off', matchCodes: ['OFF_DED', 'OFF_DAYS_CUT', 'JUMLAH_OFF'], keywords: ['OFF'] },
    ];

    const rawItems = payslip.items || [];
    const matchedItemIds = new Set();

    const findItem = (tmpl, type) => {
        for (const item of rawItems) {
            if (item.item_type && item.item_type !== type) continue;
            const code = (item.item_code_snapshot || '').toUpperCase();
            const desc = (item.description_snapshot || '').toUpperCase();
            const isMatch = tmpl.matchCodes?.some((c) => c.toUpperCase() === code) ||
                            tmpl.keywords?.some((k) => desc.includes(k.toUpperCase()));
            if (isMatch) {
                if (item.id) matchedItemIds.add(item.id);
                return item;
            }
        }
        return null;
    };

    const earnings = STANDARD_EARNINGS_TEMPLATE.map((tmpl) => {
        const item = findItem(tmpl, 'earning');
        if (item) {
            return {
                name: tmpl.name,
                code: item.item_code_snapshot || tmpl.code,
                amount: item.amount,
                rate: item.rate,
                quantity: item.quantity,
                unit: item.unit,
                note: item.note,
            };
        }
        let rate = null;
        if (tmpl.code === 'OVERTIME') {
            rate = payslip.employee?.default_overtime_rate || null;
        } else if (tmpl.code === 'MEAL_ALLOWANCE') {
            rate = payslip.employee?.uang_makan_per_hari || null;
        } else if (tmpl.code === 'TRANSPORT_ALLOWANCE') {
            rate = payslip.employee?.uang_transportasi_per_hari || null;
        }
        return {
            name: tmpl.name,
            code: tmpl.code,
            amount: 0,
            rate: rate,
            quantity: null,
            unit: null,
            note: null,
        };
    });

    rawItems.filter((i) => i.item_type === 'earning' && (!i.id || !matchedItemIds.has(i.id))).forEach((item) => {
        earnings.push({
            name: item.description_snapshot,
            code: item.item_code_snapshot,
            amount: item.amount,
            rate: item.rate,
            quantity: item.quantity,
            unit: item.unit,
            note: item.note,
        });
    });

    const deductions = STANDARD_DEDUCTIONS_TEMPLATE.map((tmpl) => {
        const item = findItem(tmpl, 'deduction');
        if (item) {
            return {
                name: tmpl.name,
                code: item.item_code_snapshot || tmpl.code,
                amount: item.amount,
                rate: item.rate,
                quantity: item.quantity,
                unit: item.unit,
                note: item.note,
            };
        }
        return {
            name: tmpl.name,
            code: tmpl.code,
            amount: 0,
            rate: null,
            quantity: null,
            unit: null,
            note: null,
        };
    });

    rawItems.filter((i) => i.item_type === 'deduction' && (!i.id || !matchedItemIds.has(i.id))).forEach((item) => {
        deductions.push({
            name: item.description_snapshot,
            code: item.item_code_snapshot,
            amount: item.amount,
            rate: item.rate,
            quantity: item.quantity,
            unit: item.unit,
            note: item.note,
        });
    });

    const isFinalized = payslip.status === 'finalized';
    const isApproved = payslip.status === 'approved';
    const isVoided = payslip.status === 'voided';

    return (
        <AuthenticatedLayout header={`Slip Gaji: ${payslip.slip_number}`}>
            <Head title={`Slip - ${payslip.employee_name_snapshot}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Navigation and Top Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <Link
                        href={route('payroll-periods.show', payslip.payroll_period_id)}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Periode {payslip.payroll_period?.period_code}</span>
                    </Link>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* PDF Actions */}
                        {isFinalized && (
                            <>
                                <a
                                    href={route('payslips.preview', payslip.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                                >
                                    <ExternalLink className="h-3.5 w-3.5 text-indigo-600" />
                                    <span>Pratinjau PDF</span>
                                </a>

                                <a
                                    href={route('payslips.download', payslip.id)}
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-[#0B57D0] px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition"
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    <span>Unduh PDF</span>
                                </a>

                                {/* Send / Retry Email Action */}
                                {can.sendEmail && (!emailDelivery || emailDelivery.status === 'failed') && (
                                    <button
                                        type="button"
                                        disabled={sendingEmail}
                                        onClick={emailDelivery?.status === 'failed' ? () => handleRetryEmail(emailDelivery.id) : handleSendEmail}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 transition disabled:opacity-50"
                                    >
                                        {emailDelivery?.status === 'failed' ? (
                                            <RotateCcw className={`h-3.5 w-3.5 ${sendingEmail ? 'animate-spin' : ''}`} />
                                        ) : (
                                            <Send className={`h-3.5 w-3.5 ${sendingEmail ? 'animate-spin' : ''}`} />
                                        )}
                                        <span>{emailDelivery?.status === 'failed' ? 'Kirim Ulang Email' : 'Kirim via Email'}</span>
                                    </button>
                                )}

                                {/* Send / Retry WhatsApp Action */}
                                {can.sendWhatsApp && (!whatsAppDelivery || whatsAppDelivery.status === 'failed') && (
                                    <button
                                        type="button"
                                        disabled={sendingWhatsApp}
                                        onClick={whatsAppDelivery?.status === 'failed' ? () => handleRetryWhatsApp(whatsAppDelivery.id) : handleSendWhatsApp}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition disabled:opacity-50"
                                    >
                                        {whatsAppDelivery?.status === 'failed' ? (
                                            <RotateCcw className={`h-3.5 w-3.5 ${sendingWhatsApp ? 'animate-spin' : ''}`} />
                                        ) : (
                                            <Smartphone className={`h-3.5 w-3.5 ${sendingWhatsApp ? 'animate-spin' : ''}`} />
                                        )}
                                        <span>{whatsAppDelivery?.status === 'failed' ? 'Kirim Ulang WA' : 'Kirim via WhatsApp'}</span>
                                    </button>
                                )}
                            </>
                        )}

                        {/* Finalize Action */}
                        {isApproved && can.finalize && (
                            <button
                                type="button"
                                onClick={handleFinalize}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                <span>Finalisasi Slip</span>
                            </button>
                        )}

                        {/* Void Action */}
                        {isFinalized && can.void && (
                            <button
                                type="button"
                                onClick={() => setVoidModalOpen(true)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-semibold text-rose-600 shadow-xs hover:bg-rose-50 transition"
                            >
                                <Ban className="h-3.5 w-3.5" />
                                <span>Batalkan (Void)</span>
                            </button>
                        )}

                        {/* Edit Action (Only draft or changes_requested) */}
                        {can.update && !isFinalized && !isVoided && (
                            <Link
                                href={route('payslips.edit', payslip.id)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Edit3 className="h-3.5 w-3.5 text-indigo-600" />
                                <span>Ubah Rincian</span>
                            </Link>
                        )}

                        {/* Delete Action */}
                        {can.delete && (payslip.status === 'draft' || payslip.status === 'changes_requested') && (
                            <button
                                type="button"
                                onClick={handleDelete}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-semibold text-rose-600 shadow-xs hover:bg-rose-50 transition"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                <span>Hapus Slip</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Finalized Document Security Banner */}
                {isFinalized && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <FileCheck className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                                <div className="space-y-1">
                                    <div className="text-xs font-bold text-emerald-950 uppercase tracking-wide">
                                        Dokumen Slip Gaji Telah Final dan Terkunci
                                    </div>
                                    <p className="text-xs text-emerald-800">
                                        Difinalisasi oleh{' '}
                                        <span className="font-semibold">{payslip.finalizer?.name || 'Petugas'}</span> pada{' '}
                                        <span className="font-mono">
                                            {payslip.finalized_at
                                                ? new Date(payslip.finalized_at).toLocaleString('id-ID')
                                                : '-'}
                                        </span>
                                    </p>
                                    {payslip.pdf_sha256 && (
                                        <div className="flex items-center gap-2 pt-1">
                                            <span className="text-[11px] font-semibold text-slate-600">Checksum (SHA-256):</span>
                                            <code className="text-[10.5px] font-mono text-emerald-900 bg-white/80 px-2 py-0.5 rounded border border-emerald-200 break-all select-all">
                                                {payslip.pdf_sha256}
                                            </code>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Voided Banner */}
                {isVoided && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50/80 p-5 shadow-xs">
                        <div className="flex items-start gap-3">
                            <Ban className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                            <div className="space-y-1 text-xs">
                                <div className="font-bold text-rose-950 uppercase tracking-wide">
                                    Dokumen Slip Gaji Ini Telah Dibatalkan (Void)
                                </div>
                                <p className="text-rose-800">
                                    Dibatalkan oleh{' '}
                                    <span className="font-semibold">{payslip.voided_by?.name || 'Petugas'}</span> pada{' '}
                                    <span className="font-mono">
                                        {payslip.voided_at
                                            ? new Date(payslip.voided_at).toLocaleString('id-ID')
                                            : '-'}
                                    </span>
                                </p>
                                <p className="text-rose-900 font-medium bg-white/70 p-2 rounded border border-rose-200">
                                    Alasan: {payslip.void_reason || '-'}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Printable Document Style Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-10 shadow-xs space-y-8">
                    {/* Header: Company & Slip Metadata */}
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-slate-200 pb-6">
                        <div>
                            <div className="text-base font-bold text-slate-900 tracking-tight">
                                {payslip.office_name_snapshot || payslip.office?.name || 'CV. BINA PUSTAKA'}
                            </div>
                            {(payslip.office_address_snapshot || payslip.office?.address) && (
                                <p className="text-[11px] text-slate-500 max-w-md mt-0.5 whitespace-pre-line">
                                    {payslip.office_address_snapshot || payslip.office?.address}
                                </p>
                            )}
                            <div className="mt-3">
                                <span className="text-xs font-bold uppercase tracking-widest text-[#0B57D0]">
                                    SLIP GAJI KARYAWAN
                                </span>
                                <h1 className="mt-0.5 text-2xl font-black font-mono text-slate-900 tracking-tight">
                                    {payslip.slip_number}
                                </h1>
                            </div>
                            <p className="mt-1 text-xs text-slate-500 flex flex-wrap items-center gap-2">
                                <span>Periode Penggajian:</span>
                                <span className="font-mono font-bold text-slate-800">
                                    {payslip.period_month_snapshot || payslip.payroll_period?.period_code}
                                </span>
                                <span>
                                    ({payslip.period_start_date_snapshot || payslip.payroll_period?.start_date} s/d{' '}
                                    {payslip.period_end_date_snapshot || payslip.payroll_period?.end_date})
                                </span>
                                <span>•</span>
                                <span>{payslip.working_days_snapshot || 25} Hari Kerja</span>
                            </p>
                        </div>

                        <div className="sm:text-right">
                            {getStatusBadge(payslip.status)}
                            <div className="mt-2 text-xs text-slate-400">
                                <div>
                                    Dibuat oleh:{' '}
                                    <span className="font-semibold text-slate-700">
                                        {payslip.creator?.name || 'Petugas'}
                                    </span>
                                </div>
                                <div className="mt-0.5">{new Date(payslip.created_at).toLocaleString('id-ID')}</div>
                            </div>
                        </div>
                    </div>

                    {/* Snapshot Employee Info Box */}
                    <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-5">
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 text-xs">
                            <div>
                                <span className="text-slate-400 block text-[11px] uppercase tracking-wider">Nama Penerima</span>
                                <span className="mt-1 font-bold text-slate-900 block text-sm">
                                    {payslip.employee_name_snapshot}
                                </span>
                            </div>

                            <div>
                                <span className="text-slate-400 block text-[11px] uppercase tracking-wider">Nomor Induk (NIK)</span>
                                <span className="mt-1 font-mono font-bold text-indigo-700 block">
                                    {payslip.employee_number_snapshot}
                                </span>
                            </div>

                            <div>
                                <span className="text-slate-400 block text-[11px] uppercase tracking-wider">Kantor & Divisi</span>
                                <span className="mt-1 font-semibold text-slate-800 block">
                                    {payslip.office_name_snapshot || payslip.office?.name || '-'}
                                </span>
                                <span className="text-slate-500 text-[11px] block mt-0.5">
                                    {payslip.division_name_snapshot || payslip.department_snapshot || '-'}
                                </span>
                            </div>

                            <div>
                                <span className="text-slate-400 block text-[11px] uppercase tracking-wider">Jabatan</span>
                                <span className="mt-1 font-semibold text-slate-800 block">
                                    {payslip.position_name_snapshot || payslip.position_snapshot || '-'}
                                </span>
                            </div>

                            <div>
                                <span className="text-slate-400 block text-[11px] uppercase tracking-wider">Kontak Pengiriman</span>
                                <span className="mt-1 font-mono text-[11px] text-slate-700 block">
                                    {payslip.email_snapshot || 'Email tidak diisi'}
                                </span>
                                <span className="font-mono text-[11px] text-emerald-700 block">
                                    {payslip.phone_snapshot ? `+${payslip.phone_snapshot}` : 'WA tidak diisi'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Dual Column Breakdown: Earnings vs Deductions */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Earnings Section */}
                        <div className="rounded-xl border border-slate-200 overflow-hidden">
                            <div className="bg-emerald-50/70 border-b border-emerald-100 px-4 py-3 flex items-center justify-between">
                                <span className="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                                    <TrendingUp className="h-4 w-4 text-emerald-600" />
                                    A. Rincian Pendapatan (GAJI)
                                </span>
                            </div>

                            <table className="min-w-full divide-y divide-slate-100 text-xs">
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {earnings.map((item, idx) => {
                                        const formatted = formatSlipAmount(item.amount);
                                        const isZero = formatted === '-';
                                        return (
                                            <tr key={idx} className="hover:bg-slate-50/50">
                                                <td className="px-4 py-2.5">
                                                    <div className="font-semibold text-slate-800">
                                                        {item.name}
                                                    </div>
                                                    <div className="font-mono text-[10px] text-slate-400">
                                                        {item.code}
                                                        {item.rate && Number(item.rate) > 0 && item.quantity && Number(item.quantity) > 0 && (
                                                            <span> • {Number(item.quantity)} {item.unit} x {formatRupiah(item.rate)}</span>
                                                        )}
                                                        {item.code === 'OVERTIME' && (!item.quantity || Number(item.quantity) === 0) && item.rate && Number(item.rate) > 0 && (
                                                            <span> • Upah Lembur {formatRupiah(item.rate)}/jam</span>
                                                        )}
                                                        {item.note && <span> ({item.note})</span>}
                                                    </div>
                                                </td>
                                                <td className={`px-4 py-2.5 text-right font-mono font-bold ${isZero ? 'text-slate-400' : 'text-emerald-700'}`}>
                                                    {formatted}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot className="border-t border-slate-200 bg-slate-50 font-semibold text-slate-800">
                                    <tr>
                                        <td className="px-4 py-3 uppercase tracking-wider text-[11px]">
                                            Gaji Bruto
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono font-bold text-emerald-700 text-sm">
                                            {formatRupiah(payslip.gaji_bruto || payslip.total_earnings)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* Deductions & Official Duty Section */}
                        <div className="space-y-4">
                            <div className="rounded-xl border border-slate-200 overflow-hidden">
                                <div className="bg-rose-50/70 border-b border-rose-100 px-4 py-3 flex items-center justify-between">
                                    <span className="text-xs font-bold uppercase tracking-wider text-rose-900 flex items-center gap-1.5">
                                        <TrendingDown className="h-4 w-4 text-rose-600" />
                                        B. Rincian Potongan (POTONGAN GAJI)
                                    </span>
                                </div>

                                <table className="min-w-full divide-y divide-slate-100 text-xs">
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {deductions.map((item, idx) => {
                                            const formatted = formatSlipAmount(item.amount);
                                            const isZero = formatted === '-';
                                            return (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="px-4 py-2.5">
                                                        <div className="font-semibold text-slate-800">
                                                            {item.name}
                                                        </div>
                                                        <div className="font-mono text-[10px] text-slate-400">
                                                            {item.code}
                                                            {item.rate && Number(item.rate) > 0 && item.quantity && Number(item.quantity) > 0 && (
                                                                <span> • {Number(item.quantity)} {item.unit} x {formatRupiah(item.rate)}</span>
                                                            )}
                                                            {item.quantity && Number(item.quantity) > 0 && (!item.rate || Number(item.rate) === 0) && (
                                                                <span> • {Number(item.quantity)} {item.unit || 'hari'}</span>
                                                            )}
                                                            {item.note && <span> ({item.note})</span>}
                                                        </div>
                                                    </td>
                                                    <td className={`px-4 py-2.5 text-right font-mono font-bold ${isZero ? 'text-slate-400' : 'text-rose-700'}`}>
                                                        {formatted}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot className="border-t border-slate-200 bg-slate-50 font-semibold text-slate-800">
                                        <tr>
                                            <td className="px-4 py-3 uppercase tracking-wider text-[11px]">
                                                Total Potongan
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-bold text-rose-700 text-sm">
                                                {formatRupiah(payslip.total_deductions)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {/* Uang Dinas Summary Box */}
                            <div className="rounded-xl border border-slate-200 bg-white p-3 flex items-center justify-between text-xs">
                                <span className="font-bold text-slate-700 uppercase tracking-wider">
                                    Uang Dinas (PJD Luar Kota)
                                </span>
                                <span className="font-mono font-bold text-indigo-700">
                                    {formatRupiah(payslip.uang_dinas || 0)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Net Salary Summary Box */}
                    <div className="rounded-2xl border-2 border-indigo-200 bg-indigo-50/60 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-900 block">
                                Total Terima Gaji (Take Home Pay)
                            </span>
                            <span className="text-xs text-indigo-700 block mt-0.5">
                                Gaji Bruto - Total Potongan + Uang Dinas (Kalkulasi Decimal Backend)
                            </span>
                        </div>

                        <div className="text-right">
                            <span className="text-3xl font-black font-mono text-indigo-800 block">
                                {formatRupiah(payslip.net_salary)}
                            </span>
                            <span className="text-xs font-semibold text-slate-500 mt-1 block">
                                Mata Uang: {payslip.currency}
                            </span>
                        </div>
                    </div>

                    {/* Signatures Snapshot Preview */}
                    <div className="border-t border-slate-200 pt-6">
                        <span className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block mb-4">
                            Pihak Penandatangan Slip
                        </span>
                        <div className="grid grid-cols-3 gap-4 text-center text-xs">
                            <div>
                                <div className="font-bold text-slate-800 underline">
                                    {payslip.signatory_personalia_name || 'Singgih Sugiarto'}
                                </div>
                                <div className="text-[11px] text-slate-500 mt-0.5">
                                    {payslip.signatory_personalia_title || 'Personalia'}
                                </div>
                            </div>
                            <div>
                                <div className="font-bold text-slate-800 underline">
                                    {payslip.signatory_reliever_name || 'Sheren Regiana'}
                                </div>
                                <div className="text-[11px] text-slate-500 mt-0.5">
                                    {payslip.signatory_reliever_title || 'Yang Menyerahkan'}
                                </div>
                            </div>
                            <div>
                                <div className="font-bold text-slate-800 underline">
                                    {payslip.signatory_receiver_name || payslip.employee_name_snapshot}
                                </div>
                                <div className="text-[11px] text-slate-500 mt-0.5">
                                    Yang Menerima
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Email Delivery Status Card */}
                {isFinalized && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2">
                                <Mail className="h-5 w-5 text-indigo-600" />
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">
                                        Status Pengiriman Email Slip Gaji
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Penerima: <span className="font-semibold text-slate-800">{payslip.email_snapshot || payslip.employee?.email || 'Belum ada email'}</span>
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {can.sendEmail && (!emailDelivery || emailDelivery.status === 'failed') && (
                                    <button
                                        type="button"
                                        disabled={sendingEmail}
                                        onClick={emailDelivery?.status === 'failed' ? () => handleRetryEmail(emailDelivery.id) : handleSendEmail}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-[#0B57D0] px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition disabled:opacity-50"
                                    >
                                        {emailDelivery?.status === 'failed' ? (
                                            <RotateCcw className={`h-3.5 w-3.5 ${sendingEmail ? 'animate-spin' : ''}`} />
                                        ) : (
                                            <Send className={`h-3.5 w-3.5 ${sendingEmail ? 'animate-spin' : ''}`} />
                                        )}
                                        <span>{emailDelivery?.status === 'failed' ? 'Kirim Ulang Email' : 'Kirim Sekarang'}</span>
                                    </button>
                                )}

                                <Link
                                    href={route('deliveries.index', { search: payslip.slip_number })}
                                    className="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                                >
                                    <span>Buka di Pelacakan</span>
                                </Link>
                            </div>
                        </div>

                        {emailDelivery ? (
                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Status Pengiriman</span>
                                    <div>{getStatusBadge(emailDelivery.status)}</div>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Total Percobaan</span>
                                    <span className="font-semibold text-slate-800">
                                        {emailDelivery.attempt_count} dari 3 percobaan
                                    </span>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Waktu Terkirim</span>
                                    <span className="font-semibold text-slate-800">
                                        {emailDelivery.sent_at
                                            ? new Date(emailDelivery.sent_at).toLocaleString('id-ID')
                                            : '-'}
                                    </span>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Percobaan Terakhir</span>
                                    <span className="font-semibold text-slate-800">
                                        {emailDelivery.last_attempt_at
                                            ? new Date(emailDelivery.last_attempt_at).toLocaleString('id-ID')
                                            : '-'}
                                    </span>
                                </div>

                                {emailDelivery.last_error_summary && (
                                    <div className="sm:col-span-4 p-3 rounded-xl bg-rose-50 border border-rose-100 text-rose-800">
                                        <span className="font-bold block mb-0.5">Catatan Kesalahan Terakhir:</span>
                                        <p className="text-[11px] font-mono">{emailDelivery.last_error_summary}</p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="py-4 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                Slip gaji ini belum pernah diantrekan untuk pengiriman email ke karyawan.
                            </div>
                        )}
                    </div>
                )}

                {/* WhatsApp Delivery Status Card */}
                {isFinalized && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2">
                                <Smartphone className="h-5 w-5 text-emerald-600" />
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">
                                        Status Pengiriman WhatsApp Slip Gaji
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Nomor Penerima: <span className="font-semibold text-slate-800">{payslip.phone_snapshot || payslip.employee?.phone || 'Belum ada nomor HP'}</span>
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {can.sendWhatsApp && (!whatsAppDelivery || whatsAppDelivery.status === 'failed') && (
                                    <button
                                        type="button"
                                        disabled={sendingWhatsApp}
                                        onClick={whatsAppDelivery?.status === 'failed' ? () => handleRetryWhatsApp(whatsAppDelivery.id) : handleSendWhatsApp}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition disabled:opacity-50"
                                    >
                                        {whatsAppDelivery?.status === 'failed' ? (
                                            <RotateCcw className={`h-3.5 w-3.5 ${sendingWhatsApp ? 'animate-spin' : ''}`} />
                                        ) : (
                                            <Smartphone className={`h-3.5 w-3.5 ${sendingWhatsApp ? 'animate-spin' : ''}`} />
                                        )}
                                        <span>{whatsAppDelivery?.status === 'failed' ? 'Kirim Ulang WA' : 'Kirim Sekarang'}</span>
                                    </button>
                                )}

                                <Link
                                    href={route('deliveries.index', { search: payslip.slip_number })}
                                    className="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                                >
                                    <span>Buka di Pelacakan</span>
                                </Link>
                            </div>
                        </div>

                        {whatsAppDelivery ? (
                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Status WhatsApp</span>
                                    <div>{getStatusBadge(whatsAppDelivery.status)}</div>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Total Percobaan</span>
                                    <span className="font-semibold text-slate-800">
                                        {whatsAppDelivery.attempt_count} dari 3 percobaan
                                    </span>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Waktu Terkirim</span>
                                    <span className="font-semibold text-slate-800">
                                        {whatsAppDelivery.sent_at
                                            ? new Date(whatsAppDelivery.sent_at).toLocaleString('id-ID')
                                            : '-'}
                                    </span>
                                </div>

                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span className="text-slate-400 block mb-1">Percobaan Terakhir</span>
                                    <span className="font-semibold text-slate-800">
                                        {whatsAppDelivery.last_attempt_at
                                            ? new Date(whatsAppDelivery.last_attempt_at).toLocaleString('id-ID')
                                            : '-'}
                                    </span>
                                </div>

                                {whatsAppDelivery.last_error_summary && (
                                    <div className="sm:col-span-4 p-3 rounded-xl bg-rose-50 border border-rose-100 text-rose-800">
                                        <span className="font-bold block mb-0.5">Catatan Kesalahan Terakhir:</span>
                                        <p className="text-[11px] font-mono">{whatsAppDelivery.last_error_summary}</p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="py-4 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                Slip gaji ini belum pernah diantrekan untuk pengiriman WhatsApp ke karyawan.
                            </div>
                        )}
                    </div>
                )}

                {/* Audit Trail Section */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div className="flex items-center gap-2">
                            <History className="h-4 w-4 text-indigo-600" />
                            <h2 className="text-sm font-bold text-slate-900">
                                Jejak Audit Slip Gaji
                            </h2>
                        </div>
                        <span className="text-xs text-slate-400">
                            Aktivitas perubahan dokumen slip
                        </span>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-2.5">Waktu</th>
                                    <th className="px-4 py-2.5">Aktor</th>
                                    <th className="px-4 py-2.5">Aksi</th>
                                    <th className="px-4 py-2.5">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-600">
                                {auditLogs && auditLogs.length > 0 ? (
                                    auditLogs.map((log) => (
                                        <tr key={log.id} className="hover:bg-slate-50/50">
                                            <td className="px-4 py-3 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                                {new Date(log.created_at).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <div className="font-semibold text-slate-800">
                                                    {log.user ? log.user.name : 'Sistem'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-mono font-medium">
                                                    {log.action}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-[11px]">
                                                {log.new_values ? (
                                                    <pre className="font-mono text-[11px] text-slate-700 bg-slate-50 p-1.5 rounded">
                                                        {JSON.stringify(log.new_values, null, 2)}
                                                    </pre>
                                                ) : (
                                                    <span className="text-slate-400 italic">-</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="4" className="px-4 py-6 text-center text-slate-400">
                                            Belum ada aktivitas tercatat untuk slip ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Void Modal Dialog */}
            {voidModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div className="flex items-center gap-2 text-rose-600 font-bold text-sm">
                                <Ban className="h-5 w-5" />
                                <span>Batalkan Slip Gaji (Void)</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setVoidModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 transition"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <p className="text-xs text-slate-600 leading-relaxed">
                            Membatalkan slip gaji yang telah final akan mencatat status <span className="font-semibold text-rose-700">Dibatalkan</span> pada sistem dan jejak audit. Dokumen historis tidak akan dihapus diam-diam.
                        </p>

                        <form onSubmit={handleVoidSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Alasan Pembatalan (Wajib diisi)
                                </label>
                                <textarea
                                    value={voidForm.data.reason}
                                    onChange={(e) => voidForm.setData('reason', e.target.value)}
                                    placeholder="Contoh: Terjadi kesalahan input jam lembur karyawan; diajukan revisi ulang."
                                    rows={3}
                                    className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-rose-500 focus:ring-rose-500"
                                    required
                                />
                                {voidForm.errors.reason && (
                                    <p className="text-rose-600 text-[11px] mt-1">{voidForm.errors.reason}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setVoidModalOpen(false)}
                                    className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={voidForm.processing || !voidForm.data.reason.trim()}
                                    className="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50 transition shadow-xs"
                                >
                                    {voidForm.processing ? 'Memproses...' : 'Konfirmasi Batalkan (Void)'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
