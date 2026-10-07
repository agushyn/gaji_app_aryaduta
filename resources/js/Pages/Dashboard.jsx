import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    ExternalLink,
    FileSpreadsheet,
    Plus,
    Receipt,
    Send,
    Shield,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    UserCheck,
    Users,
    Wallet,
} from 'lucide-react';

export default function Dashboard({ stats, payrollStats, recentActivities, latestPeriod }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const primaryRoleLabel = user?.role_labels?.[0] || 'User';

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    const getPeriodStatusBadge = (status) => {
        const badges = {
            draft: { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-300' },
            in_review: { label: 'Menunggu Review', class: 'bg-amber-100 text-amber-900 border-amber-300' },
            changes_requested: { label: 'Perlu Revisi', class: 'bg-rose-100 text-rose-900 border-rose-300' },
            approved: { label: 'Disetujui', class: 'bg-indigo-100 text-indigo-900 border-indigo-300' },
            finalized: { label: 'Final', class: 'bg-emerald-100 text-emerald-900 border-emerald-300' },
            closed: { label: 'Ditutup', class: 'bg-slate-200 text-slate-800 border-slate-300' },
        };
        const current = badges[status] || { label: status || 'Draft', class: 'bg-slate-100 text-slate-700' };

        return (
            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${current.class}`}>
                {current.label}
            </span>
        );
    };

    return (
        <AuthenticatedLayout header="Dashboard Operasional">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Hero Banner with Curated Blue Gradient */}
                <div className="overflow-hidden rounded-2xl bg-gradient-to-r from-[#0B50BA] via-[#1562CF] to-[#0D4191] p-6 sm:p-8 text-white shadow-md border border-blue-400/20 relative">
                    <div className="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-0.5 text-xs font-semibold text-white border border-white/20">
                                    <ShieldCheck className="h-3.5 w-3.5 text-blue-200" />
                                    Portal Personalia CV. Bina Pustaka
                                </span>
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-400/20 px-3 py-0.5 text-xs font-semibold text-emerald-200 border border-emerald-400/30">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                    Sistem Penggajian Aktif
                                </span>
                            </div>

                            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                                Selamat datang, {user?.name}
                            </h1>
                            <p className="text-xs sm:text-sm text-blue-100/90 max-w-2xl leading-relaxed">
                                Anda bertugas dengan peran <strong className="text-white underline decoration-white/40">{primaryRoleLabel}</strong>. Sistem ini mengelola data induk karyawan, perhitungan otomatis payroll, workflow approval, hingga distribusi slip gaji PDF privat via Email dan WhatsApp.
                            </p>
                        </div>

                        {/* Quick Action in Hero */}
                        {latestPeriod && can('payroll-periods.view') && (
                            <div className="rounded-xl bg-white/10 p-4 border border-white/20 backdrop-blur-xs flex flex-col gap-2 shrink-0 lg:min-w-[220px]">
                                <span className="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">
                                    Periode Aktif Terkini
                                </span>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-lg font-bold font-mono text-white">
                                        {latestPeriod.period_code}
                                    </span>
                                    {getPeriodStatusBadge(latestPeriod.status)}
                                </div>
                                <Link
                                    href={route('payroll-periods.show', latestPeriod.id)}
                                    className="mt-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-[#0B57D0] shadow-sm hover:bg-blue-50 transition"
                                >
                                    <span>Buka Periode</span>
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* KPI Cards Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    {/* KPI 1: Karyawan Aktif */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-blue-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Karyawan Aktif
                                </p>
                                <p className="mt-2 text-2xl sm:text-3xl font-bold text-slate-900">
                                    {payrollStats?.active_employees ?? 'Terkunci'}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Berhak atas perhitungan payroll
                                </p>
                            </div>
                            <div className="rounded-2xl bg-blue-50 p-3 text-[#0B57D0] border border-blue-100">
                                <UserCheck className="h-6 w-6" />
                            </div>
                        </div>
                        {can('employees.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('employees.index')}
                                    className="text-xs font-semibold text-[#0B57D0] hover:underline flex items-center gap-1"
                                >
                                    <span>Kelola data karyawan</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* KPI 2: Status Periode Berjalan */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-blue-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Periode Payroll Berjalan
                                </p>
                                <div className="mt-2 flex items-center gap-2">
                                    <p className="text-xl sm:text-2xl font-bold font-mono text-slate-900">
                                        {payrollStats?.current_period_code || 'Belum Ada'}
                                    </p>
                                    {payrollStats?.current_period_status && getPeriodStatusBadge(payrollStats.current_period_status)}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">
                                    {payrollStats?.current_period_slips_count || 0} slip tercatat dalam periode
                                </p>
                            </div>
                            <div className="rounded-2xl bg-indigo-50 p-3 text-indigo-600 border border-indigo-100">
                                <Calendar className="h-6 w-6" />
                            </div>
                        </div>
                        {can('payroll-periods.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('payroll-periods.index')}
                                    className="text-xs font-semibold text-indigo-600 hover:underline flex items-center gap-1"
                                >
                                    <span>Lihat seluruh periode</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* KPI 3: Menunggu Approval */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-amber-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Menunggu Persetujuan
                                </p>
                                <p className="mt-2 text-2xl sm:text-3xl font-bold text-amber-600">
                                    {payrollStats?.pending_approval_count ?? (payrollStats?.pending_periods_count ?? 0)}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Memerlukan review oleh Supervisor
                                </p>
                            </div>
                            <div className="rounded-2xl bg-amber-50 p-3 text-amber-600 border border-amber-100">
                                <Clock className="h-6 w-6" />
                            </div>
                        </div>
                        {can('approvals.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('approvals.index')}
                                    className="text-xs font-semibold text-amber-700 hover:underline flex items-center gap-1"
                                >
                                    <span>Tinjau persetujuan gaji</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* KPI 4: Total Gaji Bersih Periode Berjalan */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-emerald-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Total Gaji Bersih (Net)
                                </p>
                                <p className="mt-2 text-xl sm:text-2xl font-bold text-emerald-700">
                                    {formatRupiah(payrollStats?.current_period_net_salary)}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Akumulasi periode {payrollStats?.current_period_code || 'aktif'}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-emerald-50 p-3 text-emerald-600 border border-emerald-100">
                                <Wallet className="h-6 w-6" />
                            </div>
                        </div>
                        {can('payslips.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('payslips.index')}
                                    className="text-xs font-semibold text-emerald-700 hover:underline flex items-center gap-1"
                                >
                                    <span>Buka rincian slip gaji</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* KPI 5: Slip Sudah Difinalisasi */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-blue-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Slip Gaji Final
                                </p>
                                <p className="mt-2 text-2xl sm:text-3xl font-bold text-slate-900">
                                    {payrollStats?.finalized_slips_count ?? 0}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Terkunci snapshot & siap dikirimkan
                                </p>
                            </div>
                            <div className="rounded-2xl bg-blue-50 p-3 text-[#0B57D0] border border-blue-100">
                                <Receipt className="h-6 w-6" />
                            </div>
                        </div>
                        {can('payslips.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('payslips.index', { status: 'finalized' })}
                                    className="text-xs font-semibold text-[#0B57D0] hover:underline flex items-center gap-1"
                                >
                                    <span>Lihat slip berstatus final</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* KPI 6: Pengiriman Slip Gaji */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-blue-200 transition">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Distribusi Slip Gaji
                                </p>
                                <div className="mt-2 flex items-baseline gap-3">
                                    <span className="text-2xl font-bold text-emerald-600">
                                        {payrollStats?.deliveries_sent_count ?? 0}
                                        <span className="text-xs font-medium text-slate-500 ml-1">Terkirim</span>
                                    </span>
                                    <span className="text-lg font-bold text-rose-600">
                                        {payrollStats?.deliveries_failed_count ?? 0}
                                        <span className="text-xs font-medium text-slate-500 ml-1">Gagal</span>
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-slate-500">
                                    Melalui kanal Email SMTP & WhatsApp
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-100 p-3 text-slate-700 border border-slate-200">
                                <Send className="h-6 w-6" />
                            </div>
                        </div>
                        {can('deliveries.view') && (
                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('deliveries.index')}
                                    className="text-xs font-semibold text-[#0B57D0] hover:underline flex items-center gap-1"
                                >
                                    <span>Pelacakan log pengiriman</span>
                                    <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick Action Shortcuts */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800 mb-4">
                        Aksi Cepat Operasional
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {can('payslips.manage') && (
                            <Link
                                href={route('payslips.create')}
                                className="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-blue-50/50 hover:border-blue-200 transition group"
                            >
                                <div className="p-2 rounded-lg bg-blue-100 text-[#0B57D0] group-hover:bg-[#0B57D0] group-hover:text-white transition">
                                    <Plus className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-800">Input Slip Gaji</p>
                                    <p className="text-[11px] text-slate-500">Pembuatan slip individu</p>
                                </div>
                            </Link>
                        )}

                        {can('payroll-periods.manage') && (
                            <Link
                                href={route('payroll-periods.index')}
                                className="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-emerald-50/50 hover:border-emerald-200 transition group"
                            >
                                <div className="p-2 rounded-lg bg-emerald-100 text-emerald-700 group-hover:bg-emerald-600 group-hover:text-white transition">
                                    <Sparkles className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-800">Auto Generate Gaji</p>
                                    <p className="text-[11px] text-slate-500">Kalkulasi periode massal</p>
                                </div>
                            </Link>
                        )}

                        {can('deliveries.send') && (
                            <Link
                                href={route('deliveries.bulk.create')}
                                className="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-purple-50/50 hover:border-purple-200 transition group"
                            >
                                <div className="p-2 rounded-lg bg-purple-100 text-purple-700 group-hover:bg-purple-600 group-hover:text-white transition">
                                    <Send className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-800">Pengiriman Massal</p>
                                    <p className="text-[11px] text-slate-500">Distribusi slip via Email/WA</p>
                                </div>
                            </Link>
                        )}

                        {can('reports.view') && (
                            <Link
                                href={route('reports.deliveries.index')}
                                className="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-indigo-50/50 hover:border-indigo-200 transition group"
                            >
                                <div className="p-2 rounded-lg bg-indigo-100 text-indigo-700 group-hover:bg-indigo-600 group-hover:text-white transition">
                                    <FileSpreadsheet className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-800">Laporan Pengiriman</p>
                                    <p className="text-[11px] text-slate-500">Filter & ekspor data slip</p>
                                </div>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Recent Activities Section */}
                {can('audit-logs.view') && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                            <div>
                                <h3 className="text-sm font-bold uppercase tracking-wider text-slate-800">
                                    Catatan Aktivitas Terakhir
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Audit trail mutlak tercatat di server tanpa data rahasia
                                </p>
                            </div>
                            <Link
                                href={route('audit-logs.index')}
                                className="text-xs font-semibold text-[#0B57D0] hover:underline"
                            >
                                Lihat Semua Aktivitas &rarr;
                            </Link>
                        </div>

                        <div className="mt-4 divide-y divide-slate-100">
                            {recentActivities && recentActivities.length > 0 ? (
                                recentActivities.map((log) => (
                                    <div key={log.id} className="py-3 flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-full bg-slate-100 p-2 text-slate-600 shrink-0">
                                                <Activity className="h-3.5 w-3.5" />
                                            </div>
                                            <div>
                                                <p className="font-semibold text-slate-800">
                                                    {log.action}
                                                </p>
                                                <p className="text-slate-500 text-[11px]">
                                                    Oleh: <span className="font-medium text-slate-700">{log.user?.name || 'Sistem / Anonim'}</span>
                                                    {log.ip_address && ` • IP: ${log.ip_address}`}
                                                </p>
                                            </div>
                                        </div>
                                        <span className="text-slate-400 whitespace-nowrap text-[11px] font-mono">
                                            {new Date(log.created_at).toLocaleString('id-ID', {
                                                day: '2-digit',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <p className="py-4 text-center text-xs text-slate-400">
                                    Belum ada aktivitas terekam.
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

