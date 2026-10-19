import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Clock,
    Coins,
    Edit3,
    Eye,
    FileSpreadsheet,
    Mail,
    Plus,
    Receipt,
    RefreshCw,
    Search,
    Send,
    Smartphone,
    Sparkles,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    UserCheck,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import OvertimeSection from './Partials/OvertimeSection';

export default function PayrollPeriodsShow({
    period,
    payslips,
    totals,
    filters,
    overtimeEntries = [],
    overtimeRecap = [],
    overtimeTotals = {},
    overtimeConfig = {},
    activeEmployees = [],
    can: permissions = {},
}) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [mainTab, setMainTab] = useState('slips'); // 'slips' | 'overtime'
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [sendingEmails, setSendingEmails] = useState(false);
    const [sendingWhatsApp, setSendingWhatsApp] = useState(false);
    const [generatingPayroll, setGeneratingPayroll] = useState(false);

    const handleAutoGeneratePayroll = () => {
        const workingDays = period.working_days || 25;
        if (
            !confirm(
                `Jalankan Auto Generate Payroll untuk periode ${period.period_code}?\n\n` +
                `- Komponen tetap diambil dari master karyawan.\n` +
                `- Uang makan & transportasi dikalikan ${workingDays} hari kerja.\n` +
                `- Potongan BPJS Ketenagakerjaan diterapkan sesuai master karyawan.\n` +
                `- Komponen dinamis yang sudah diinput sebelumnya (seperti lembur/pinjaman) tetap dipertahankan.\n\n` +
                `Lanjutkan proses?`
            )
        ) {
            return;
        }

        setGeneratingPayroll(true);
        router.post(
            route('payroll-periods.generate-payroll', period.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setGeneratingPayroll(false),
            }
        );
    };

    const handleSendPeriodEmails = () => {
        if (
            !confirm(
                `Kirim seluruh slip gaji berstatus Final pada periode ${period.period_code} ke email masing-masing karyawan secara individual?`
            )
        ) {
            return;
        }

        setSendingEmails(true);
        router.post(
            route('payroll-periods.deliveries.email', period.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingEmails(false),
            }
        );
    };

    const handleSendPeriodWhatsApp = () => {
        if (
            !confirm(
                `Kirim seluruh slip gaji berstatus Final pada periode ${period.period_code} via WhatsApp ke masing-masing karyawan secara individual?`
            )
        ) {
            return;
        }

        setSendingWhatsApp(true);
        router.post(
            route('payroll-periods.deliveries.whatsapp', period.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingWhatsApp(false),
            }
        );
    };

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payroll-periods.show', period.id),
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        router.get(route('payroll-periods.show', period.id), {}, { preserveState: true, replace: true });
    };

    const handleDeleteSlip = (slip) => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus slip gaji ${slip.slip_number} (${slip.employee_name_snapshot})?`
            )
        ) {
            router.delete(route('payslips.destroy', slip.id));
        }
    };

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    const getPeriodStatusBadge = (periodStatus) => {
        const badges = {
            draft: { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-200' },
            in_review: { label: 'Menunggu Review', class: 'bg-amber-50 text-amber-800 border-amber-200' },
            changes_requested: { label: 'Perlu Revisi', class: 'bg-rose-50 text-rose-800 border-rose-200' },
            approved: { label: 'Disetujui', class: 'bg-indigo-50 text-indigo-800 border-indigo-200' },
            finalized: { label: 'Final', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
            closed: { label: 'Ditutup', class: 'bg-slate-100 text-slate-600 border-slate-300' },
        };
        const current = badges[periodStatus] || { label: periodStatus, class: 'bg-slate-100 text-slate-700' };

        return (
            <span
                className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold border ${current.class}`}
            >
                {current.label}
            </span>
        );
    };

    const getSlipStatusBadge = (slipStatus) => {
        const badges = {
            draft: { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-200' },
            pending_review: { label: 'Review', class: 'bg-amber-50 text-amber-800 border-amber-200' },
            changes_requested: { label: 'Revisi', class: 'bg-rose-50 text-rose-800 border-rose-200' },
            approved: { label: 'Disetujui', class: 'bg-indigo-50 text-indigo-800 border-indigo-200' },
            finalized: { label: 'Final', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
            voided: { label: 'Dibatalkan', class: 'bg-rose-100 text-rose-800 border-rose-300' },
        };
        const current = badges[slipStatus] || { label: slipStatus, class: 'bg-slate-100 text-slate-700' };

        return (
            <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border ${current.class}`}
            >
                {current.label}
            </span>
        );
    };

    return (
        <AuthenticatedLayout header={`Periode Gaji: ${period.period_code}`}>
            <Head title={`Periode ${period.period_code}`} />

            <div className="space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center justify-between">
                    <Link
                        href={route('payroll-periods.index')}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Daftar Periode</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        {can('payroll-periods.manage') && period.status === 'draft' && (
                            <Link
                                href={route('payroll-periods.edit', period.id)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Edit3 className="h-3.5 w-3.5 text-indigo-600" />
                                <span>Ubah Periode</span>
                            </Link>
                        )}

                        {can('payroll-periods.manage') && (period.status === 'draft' || period.status === 'changes_requested') && (
                            <button
                                type="button"
                                disabled={generatingPayroll}
                                onClick={handleAutoGeneratePayroll}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition disabled:opacity-50"
                                title="Auto Generate Gaji untuk seluruh karyawan aktif"
                            >
                                <Sparkles className={`h-3.5 w-3.5 ${generatingPayroll ? 'animate-spin' : ''}`} />
                                <span>{generatingPayroll ? 'Memproses Gaji...' : 'Auto Generate Gaji'}</span>
                            </button>
                        )}

                        {permissions?.manage_overtime && (
                            <button
                                type="button"
                                onClick={() => setMainTab('overtime')}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-semibold text-[#0B57D0] shadow-xs hover:bg-blue-100 transition"
                                title="Buka data lembur karyawan untuk periode ini"
                            >
                                <Clock className="h-3.5 w-3.5" />
                                <span>Data Lembur ({overtimeEntries?.length || 0})</span>
                            </button>
                        )}

                        {can('payslips.manage') && (period.status === 'draft' || period.status === 'changes_requested') && (
                            <Link
                                href={route('payslips.create', { period_id: period.id })}
                                className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition"
                            >
                                <Plus className="h-4 w-4" />
                                <span>Input Slip Gaji</span>
                            </Link>
                        )}

                        {can('approvals.view') && (
                            <Link
                                href={route('approvals.periods.show', period.id)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-semibold text-indigo-700 shadow-xs hover:bg-indigo-100 transition"
                            >
                                <span>Tinjau Persetujuan</span>
                            </Link>
                        )}

                        {period.status === 'finalized' && can('deliveries.send') && (
                            <>
                                <button
                                    type="button"
                                    disabled={sendingEmails}
                                    onClick={handleSendPeriodEmails}
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 transition disabled:opacity-50"
                                >
                                    {sendingEmails ? (
                                        <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Send className="h-3.5 w-3.5" />
                                    )}
                                    <span>Kirim via Email</span>
                                </button>

                                <button
                                    type="button"
                                    disabled={sendingWhatsApp}
                                    onClick={handleSendPeriodWhatsApp}
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition disabled:opacity-50"
                                >
                                    {sendingWhatsApp ? (
                                        <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Smartphone className="h-3.5 w-3.5" />
                                    )}
                                    <span>Kirim via WhatsApp</span>
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Period Overview Header Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold font-mono text-slate-900 tracking-tight">
                                    Periode {period.period_code}
                                </h1>
                                {getPeriodStatusBadge(period.status)}
                            </div>
                            <p className="mt-1 text-xs text-slate-500 flex items-center gap-1.5">
                                <Calendar className="h-3.5 w-3.5 text-slate-400" />
                                <span>
                                    Rentang: {new Date(period.start_date).toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                    {' sampai '}
                                    {new Date(period.end_date).toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                    {' • '}
                                    Hari Kerja: <strong className="font-semibold text-slate-800">{period.working_days || 25} hari</strong>
                                </span>
                            </p>
                        </div>

                        <div className="text-right text-xs text-slate-400">
                            <div>Dibuat oleh: <span className="font-semibold text-slate-700">{period.creator?.name || 'Sistem'}</span></div>
                            <div className="mt-0.5">{new Date(period.created_at).toLocaleString('id-ID')}</div>
                        </div>
                    </div>

                    {/* Financial Summary Cards */}
                    <div className="mt-5 grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
                                <span>Total Slip Dibuat</span>
                                <Receipt className="h-4 w-4 text-indigo-500" />
                            </div>
                            <div className="mt-1 text-2xl font-bold text-slate-900">
                                {totals?.total_slips || 0}
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <div className="flex items-center justify-between text-emerald-700 text-xs font-medium">
                                <span>Total Pendapatan</span>
                                <TrendingUp className="h-4 w-4 text-emerald-500" />
                            </div>
                            <div className="mt-1 text-xl font-bold text-emerald-700">
                                {formatRupiah(totals?.total_earnings)}
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <div className="flex items-center justify-between text-rose-700 text-xs font-medium">
                                <span>Total Potongan</span>
                                <TrendingDown className="h-4 w-4 text-rose-500" />
                            </div>
                            <div className="mt-1 text-xl font-bold text-rose-700">
                                {formatRupiah(totals?.total_deductions)}
                            </div>
                        </div>

                        <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                            <div className="flex items-center justify-between text-blue-900 text-xs font-bold uppercase tracking-wider">
                                <span>Total Gaji Bersih</span>
                                <Wallet className="h-4 w-4 text-[#0B57D0]" />
                            </div>
                            <div className="mt-1 text-xl font-extrabold text-[#0B57D0]">
                                {formatRupiah(totals?.total_net)}
                            </div>
                        </div>
                    </div>
                </div>                {/* Period Navigation Tabs: Slip Gaji vs Data Lembur Karyawan */}
                <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
                    <button
                        type="button"
                        onClick={() => setMainTab('slips')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition ${
                            mainTab === 'slips'
                                ? 'bg-slate-900 text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <Receipt className="h-4 w-4" />
                        <span>Daftar Slip Gaji ({totals?.total_slips || payslips?.total || 0})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setMainTab('overtime')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition ${
                            mainTab === 'overtime'
                                ? 'bg-[#0B57D0] text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <Clock className="h-4 w-4" />
                        <span>Data Lembur Karyawan ({overtimeEntries?.length || 0})</span>
                    </button>
                </div>

                {/* Overtime Module Tab */}
                {mainTab === 'overtime' && (
                    <OvertimeSection
                        period={period}
                        overtimeEntries={overtimeEntries}
                        overtimeRecap={overtimeRecap}
                        overtimeTotals={overtimeTotals}
                        overtimeConfig={overtimeConfig}
                        activeEmployees={activeEmployees}
                        canManage={permissions?.manage_overtime}
                    />
                )}

                {/* Slips Module Tab */}
                {mainTab === 'slips' && (
                    <div className="space-y-6">
                        {/* Filter Slips Inside Period */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                            <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                                <div className="relative flex-1 min-w-[240px]">
                                    <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Cari nomor slip, nama karyawan, NIK, jabatan..."
                                        className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                                    />
                                </div>

                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[140px]"
                                >
                                    <option value="">Semua Status Slip</option>
                                    <option value="draft">Draft</option>
                                    <option value="pending_review">Menunggu Review</option>
                                    <option value="changes_requested">Perlu Revisi</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="finalized">Final</option>
                                </select>

                                <button
                                    type="submit"
                                    className="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition"
                                >
                                    Filter
                                </button>

                                {(search || status) && (
                                    <button
                                        type="button"
                                        onClick={handleReset}
                                        className="text-xs font-medium text-slate-500 hover:text-slate-700 underline"
                                    >
                                        Reset
                                    </button>
                                )}
                            </form>
                        </div>

                        {/* Payslips Table */}
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th className="px-6 py-3.5">Nomor Slip & Karyawan</th>
                                            <th className="px-6 py-3.5">Departemen & Posisi</th>
                                            <th className="px-6 py-3.5 text-right">Pendapatan</th>
                                            <th className="px-6 py-3.5 text-right">Potongan</th>
                                            <th className="px-6 py-3.5 text-right">Gaji Bersih</th>
                                            <th className="px-6 py-3.5">Status</th>
                                            <th className="px-6 py-3.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                        {payslips?.data?.length > 0 ? (
                                            payslips.data.map((slip) => (
                                                <tr key={slip.id} className="hover:bg-slate-50/60 transition">
                                                    <td className="px-6 py-4">
                                                        <div className="font-bold text-slate-900 text-sm">
                                                            {slip.employee_name_snapshot}
                                                        </div>
                                                        <div className="font-mono text-xs text-indigo-600 mt-0.5">
                                                            {slip.slip_number}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 mt-0.5">
                                                            NIK: {slip.employee_number_snapshot}
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-4">
                                                        <div className="font-medium text-slate-800">
                                                            {slip.position_snapshot || '-'}
                                                        </div>
                                                        <div className="text-slate-400 text-[11px] mt-0.5">
                                                            {slip.department_snapshot || 'Umum'}
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-4 text-right font-medium text-slate-700">
                                                        {formatRupiah(slip.total_earnings)}
                                                    </td>

                                                    <td className="px-6 py-4 text-right font-medium text-rose-600">
                                                        {formatRupiah(slip.total_deductions)}
                                                    </td>

                                                    <td className="px-6 py-4 text-right font-bold text-indigo-700">
                                                        {formatRupiah(slip.net_salary)}
                                                    </td>

                                                    <td className="px-6 py-4">
                                                        {getSlipStatusBadge(slip.status)}
                                                    </td>

                                                    <td className="px-6 py-4 text-right whitespace-nowrap">
                                                        <div className="inline-flex items-center gap-1.5">
                                                            <Link
                                                                href={route('payslips.show', slip.id)}
                                                                className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                                                                title="Lihat Rincian Slip Gaji"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Link>

                                                            {can('payslips.manage') && (slip.status === 'draft' || slip.status === 'changes_requested') && (
                                                                <>
                                                                    <Link
                                                                        href={route('payslips.edit', slip.id)}
                                                                        className="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 transition"
                                                                        title="Ubah Rincian Komponen"
                                                                    >
                                                                        <Edit3 className="h-4 w-4" />
                                                                    </Link>

                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleDeleteSlip(slip)}
                                                                        className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                                        title="Hapus Slip Gaji"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="7" className="px-6 py-10 text-center text-slate-400">
                                                    Belum ada slip gaji yang diinputkan untuk periode ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {payslips?.links && payslips.links.length > 3 && (
                                <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                                    <span className="text-xs text-slate-500">
                                        Menampilkan {payslips.from || 0} - {payslips.to || 0} dari {payslips.total} slip gaji
                                    </span>
                                    <div className="flex gap-1">
                                        {payslips.links.map((link, idx) => (
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
                )}
            </div>
        </AuthenticatedLayout>
    );
}
