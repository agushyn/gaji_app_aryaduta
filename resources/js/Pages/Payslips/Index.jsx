import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Calendar,
    Edit3,
    Eye,
    Plus,
    Receipt,
    Search,
    Trash2,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';

export default function PayslipsIndex({ payslips, periods, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [periodId, setPeriodId] = useState(filters?.period_id || '');
    const [status, setStatus] = useState(filters?.status || '');

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payslips.index'),
            { search, period_id: periodId, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setPeriodId('');
        setStatus('');
        router.get(route('payslips.index'), {}, { preserveState: true, replace: true });
    };

    const handleDelete = (slip) => {
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

    const getStatusBadge = (slipStatus) => {
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
                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${current.class}`}
            >
                {current.label}
            </span>
        );
    };

    return (
        <AuthenticatedLayout header="Data Slip Gaji">
            <Head title="Data Slip Gaji" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                            Daftar Seluruh Slip Gaji
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola rincian slip gaji per karyawan, nominal penghasilan, potongan, dan kalkulasi take home pay.
                        </p>
                    </div>

                    {can('payslips.manage') && (
                        <Link
                            href={route('payslips.create')}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Input Slip Gaji</span>
                        </Link>
                    )}
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
                                placeholder="Cari nomor slip, nama karyawan, NIK, jabatan..."
                                className="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            />
                        </div>

                        <select
                            value={periodId}
                            onChange={(e) => setPeriodId(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[160px]"
                        >
                            <option value="">Semua Periode</option>
                            {periods?.map((p) => (
                                <option key={p.id} value={p.id}>
                                    Periode {p.period_code} ({p.status})
                                </option>
                            ))}
                        </select>

                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[140px]"
                        >
                            <option value="">Semua Status</option>
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

                        {(search || periodId || status) && (
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
                                    <th className="px-6 py-3.5">Nomor Slip & Karyawan</th>
                                    <th className="px-6 py-3.5">Periode</th>
                                    <th className="px-6 py-3.5">Departemen</th>
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

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="font-mono font-semibold text-slate-800">
                                                    {slip.payroll_period?.period_code || '-'}
                                                </span>
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
                                                {getStatusBadge(slip.status)}
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
                                                                onClick={() => handleDelete(slip)}
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
                                        <td colSpan="8" className="px-6 py-10 text-center text-slate-400">
                                            Tidak ada data slip gaji yang ditemukan.
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
        </AuthenticatedLayout>
    );
}
