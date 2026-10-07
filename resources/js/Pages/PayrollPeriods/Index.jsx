import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Calendar,
    CalendarCheck,
    CalendarDays,
    CalendarPlus,
    Clock,
    Edit3,
    Eye,
    FileSpreadsheet,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

export default function PayrollPeriodsIndex({ periods, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payroll-periods.index'),
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        router.get(route('payroll-periods.index'), {}, { preserveState: true, replace: true });
    };

    const handleDelete = (period) => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus periode gaji ${period.period_code}? Periode yang memiliki slip gaji tidak dapat dihapus.`
            )
        ) {
            router.delete(route('payroll-periods.destroy', period.id));
        }
    };

    const getStatusBadge = (periodStatus) => {
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
                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${current.class}`}
            >
                {current.label}
            </span>
        );
    };

    return (
        <AuthenticatedLayout header="Periode Penggajian">
            <Head title="Periode Gaji" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                            Manajemen Periode Gaji
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola siklus penggajian bulanan, pantau jumlah slip gaji per periode, dan lakukan input rincian.
                        </p>
                    </div>

                    {can('payroll-periods.manage') && (
                        <Link
                            href={route('payroll-periods.create')}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                        >
                            <CalendarPlus className="h-4 w-4" />
                            <span>Buka Periode Baru</span>
                        </Link>
                    )}
                </div>

                {/* Counts Overview Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Total Periode
                                </p>
                                <p className="mt-1 text-2xl font-bold text-slate-900">
                                    {counts?.total || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-indigo-50 p-2.5 text-indigo-600">
                                <CalendarDays className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Periode Berjalan (Draft)
                                </p>
                                <p className="mt-1 text-2xl font-bold text-amber-700">
                                    {counts?.draft || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                                <Clock className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Periode Final
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-700">
                                    {counts?.finalized || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                                <CalendarCheck className="h-5 w-5" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[240px]">
                            <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari kode periode atau catatan..."
                                className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            />
                        </div>

                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[160px]"
                        >
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="in_review">Menunggu Review</option>
                            <option value="changes_requested">Perlu Revisi</option>
                            <option value="approved">Disetujui</option>
                            <option value="finalized">Final</option>
                            <option value="closed">Ditutup</option>
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

                {/* Table Data */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Kode Periode</th>
                                    <th className="px-6 py-3.5">Rentang Tanggal</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5">Jumlah Slip Terdaftar</th>
                                    <th className="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                {periods?.data?.length > 0 ? (
                                    periods.data.map((period) => (
                                        <tr key={period.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-6 py-4">
                                                <div className="font-bold font-mono text-sm text-indigo-700">
                                                    {period.period_code}
                                                </div>
                                                {period.approval_comment && (
                                                    <div className="text-[11px] text-slate-400 truncate max-w-xs mt-0.5">
                                                        {period.approval_comment}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="font-medium text-slate-800">
                                                    {new Date(period.start_date).toLocaleDateString('id-ID', {
                                                        day: '2-digit',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    })}
                                                    {' s/d '}
                                                    {new Date(period.end_date).toLocaleDateString('id-ID', {
                                                        day: '2-digit',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    })}
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                {getStatusBadge(period.status)}
                                            </td>

                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800">
                                                    <FileSpreadsheet className="h-3.5 w-3.5 text-slate-500" />
                                                    {period.payslips_count ?? 0} Slip
                                                </span>
                                            </td>

                                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center gap-1.5">
                                                    <Link
                                                        href={route('payroll-periods.show', period.id)}
                                                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                                                        title="Buka Rincian Periode & Slip"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>

                                                    {can('payroll-periods.manage') && period.status === 'draft' && (
                                                        <>
                                                            <Link
                                                                href={route('payroll-periods.edit', period.id)}
                                                                className="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 transition"
                                                                title="Ubah Tanggal Periode"
                                                            >
                                                                <Edit3 className="h-4 w-4" />
                                                            </Link>

                                                            {period.payslips_count === 0 && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDelete(period)}
                                                                    className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                                    title="Hapus Periode Kosong"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </button>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-10 text-center text-slate-400">
                                            Belum ada periode gaji yang dibuat.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {periods?.links && periods.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Menampilkan {periods.from || 0} - {periods.to || 0} dari {periods.total} periode
                            </span>
                            <div className="flex gap-1">
                                {periods.links.map((link, idx) => (
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
        </AuthenticatedLayout>
    );
}
