import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, Calendar, Filter, Lock, Search, User } from 'lucide-react';
import { useState } from 'react';

export default function AuditLogsIndex({ logs, users, filters }) {
    const [actionFilter, setActionFilter] = useState(filters?.action || '');
    const [userFilter, setUserFilter] = useState(filters?.user_id || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(
            route('audit-logs.index'),
            { action: actionFilter, user_id: userFilter },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setActionFilter('');
        setUserFilter('');
        router.get(route('audit-logs.index'), {}, { preserveState: true, replace: true });
    };

    const formatActionBadge = (action) => {
        if (action.startsWith('auth.login')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        if (action.startsWith('auth.logout')) {
            return 'bg-slate-100 text-slate-700 border-slate-200';
        }
        if (action.startsWith('auth.failed') || action.startsWith('auth.rejected')) {
            return 'bg-rose-50 text-rose-700 border-rose-200';
        }
        if (action.startsWith('user.')) {
            return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        }
        return 'bg-blue-50 text-blue-700 border-blue-200';
    };

    return (
        <AuthenticatedLayout header="Audit Trail Aktivitas Sistem">
            <Head title="Audit Trail" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                        Catatan Jejak Audit (Audit Trail)
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Seluruh aktivitas autentikasi dan manipulasi data dicatat secara permanen tanpa menyimpan data rahasia.
                    </p>
                </div>

                {/* Filter Bar */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-3">
                        <div className="flex-1 min-w-[200px]">
                            <input
                                type="text"
                                value={actionFilter}
                                onChange={(e) => setActionFilter(e.target.value)}
                                placeholder="Cari nama aksi (mis: auth.login)..."
                                className="w-full rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            />
                        </div>

                        <select
                            value={userFilter}
                            onChange={(e) => setUserFilter(e.target.value)}
                            className="rounded-xl border border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 min-w-[180px]"
                        >
                            <option value="">Semua Aktor Pengguna</option>
                            {users?.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name} ({u.email})
                                </option>
                            ))}
                        </select>

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition"
                        >
                            Filter
                        </button>

                        {(actionFilter || userFilter) && (
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

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Waktu</th>
                                    <th className="px-6 py-3.5">Aksi</th>
                                    <th className="px-6 py-3.5">Pengguna (Aktor)</th>
                                    <th className="px-6 py-3.5">IP & User Agent</th>
                                    <th className="px-6 py-3.5">Rincian Perubahan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                {logs?.data?.length > 0 ? (
                                    logs.data.map((log) => (
                                        <tr key={log.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-6 py-4 whitespace-nowrap text-slate-500 font-mono">
                                                {new Date(log.created_at).toLocaleString('id-ID', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    second: '2-digit',
                                                })}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold border ${formatActionBadge(
                                                        log.action
                                                    )}`}
                                                >
                                                    {log.action}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                {log.user ? (
                                                    <div>
                                                        <span className="font-semibold text-slate-900 block">
                                                            {log.user.name}
                                                        </span>
                                                        <span className="text-[11px] text-slate-500">
                                                            {log.user.email}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">
                                                        Sistem / Tamu
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-slate-500">
                                                <span className="font-mono text-[11px] block">
                                                    {log.ip_address || '127.0.0.1'}
                                                </span>
                                                <span className="text-[10px] text-slate-400 truncate block max-w-xs">
                                                    {log.user_agent}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                {log.changes_json ? (
                                                    <pre className="max-w-xs overflow-x-auto rounded bg-slate-50 p-1.5 font-mono text-[10px] text-slate-700 border border-slate-200">
                                                        {JSON.stringify(log.changes_json, null, 2)}
                                                    </pre>
                                                ) : (
                                                    <span className="text-slate-400 text-xs">
                                                        Tidak ada payload
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-8 text-center text-slate-400">
                                            Belum ada catatan aktivitas yang cocok dengan filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs?.links && logs.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-slate-200 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Menampilkan {logs.from || 0} - {logs.to || 0} dari {logs.total} log
                            </span>
                            <div className="flex gap-1">
                                {logs.links.map((link, idx) => (
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
