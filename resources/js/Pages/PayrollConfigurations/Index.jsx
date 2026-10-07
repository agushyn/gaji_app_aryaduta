import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    Check,
    CheckCircle2,
    Copy,
    Edit3,
    Eye,
    Plus,
    Search,
    Sliders,
    Sparkles,
    ToggleLeft,
    ToggleRight,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

export default function PayrollConfigurationsIndex({ configs, counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [dayType, setDayType] = useState(filters?.day_type || '');
    const [status, setStatus] = useState(filters?.status || '');

    // Preview modal state
    const [previewItem, setPreviewItem] = useState(null);

    // Safe formatter to prevent 'Objects are not valid as a React child'
    const formatFormulaPreviewText = (preview) => {
        if (!preview) return '-';
        if (typeof preview === 'string') return preview;
        if (typeof preview === 'object') {
            if (preview.text) return String(preview.text);
            if (preview.formatted_text) return String(preview.formatted_text);
            if (preview.formula_text) return String(preview.formula_text);
            if (preview.regular_overtime || preview.holiday_overtime) {
                const parts = [];
                if (preview.regular_overtime) {
                    const reg = preview.regular_overtime;
                    parts.push(typeof reg === 'string' ? reg : (reg.text || JSON.stringify(reg, null, 2)));
                }
                if (preview.holiday_overtime) {
                    const hol = preview.holiday_overtime;
                    parts.push(typeof hol === 'string' ? hol : (hol.text || JSON.stringify(hol, null, 2)));
                }
                return parts.join('\n\n---\n\n');
            }
            return JSON.stringify(preview, null, 2);
        }
        return String(preview);
    };
    const [deleteItem, setDeleteItem] = useState(null);

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*') || user.roles?.includes('super_admin')) return true;
        return user.permissions?.includes(permission);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('payroll-configurations.index'),
            { search, day_type: dayType, status },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setDayType('');
        setStatus('');
        router.get(route('payroll-configurations.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (config) => {
        router.patch(
            route('payroll-configurations.toggle-status', config.id),
            {},
            { preserveScroll: true }
        );
    };

    const handleDuplicate = (config) => {
        if (confirm(`Duplikasi konfigurasi '${config.name}'?`)) {
            router.post(
                route('payroll-configurations.duplicate', config.id),
                {},
                { preserveScroll: true }
            );
        }
    };

    const confirmDelete = (config) => {
        setDeleteItem(config);
    };

    const handleDelete = () => {
        if (!deleteItem) return;
        router.delete(
            route('payroll-configurations.destroy', deleteItem.id),
            {
                preserveScroll: true,
                onSuccess: () => setDeleteItem(null),
            }
        );
    };

    return (
        <AuthenticatedLayout
            title="Konfigurasi Perhitungan Payroll"
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800">
                            Konfigurasi Perhitungan Payroll
                        </h2>
                        <p className="text-xs text-slate-500 mt-1">
                            Atur logic perhitungan lembur, formula fleksibel, dan versi formula per jenis hari.
                        </p>
                    </div>
                    {can('payroll.configuration.create') && (
                        <Link
                            href={route('payroll-configurations.create')}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                        >
                            <Plus className="w-4 h-4 mr-1.5" />
                            Tambah Konfigurasi
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Konfigurasi Perhitungan Payroll" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <div className="text-xs text-slate-500 font-medium">Total Aturan</div>
                        <div className="text-2xl font-bold text-slate-800 mt-1">{counts.total}</div>
                    </div>
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <div className="text-xs text-emerald-600 font-medium">Aturan Aktif</div>
                        <div className="text-2xl font-bold text-emerald-700 mt-1">{counts.active}</div>
                    </div>
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <div className="text-xs text-indigo-600 font-medium">Hari Kerja</div>
                        <div className="text-2xl font-bold text-indigo-700 mt-1">{counts.working_day}</div>
                    </div>
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <div className="text-xs text-amber-600 font-medium">Hari Libur</div>
                        <div className="text-2xl font-bold text-amber-700 mt-1">{counts.holiday}</div>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Cari nama konfigurasi atau kode..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <select
                            value={dayType}
                            onChange={(e) => setDayType(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Jenis Hari</option>
                            <option value="working_day">Hari Kerja</option>
                            <option value="holiday">Hari Libur</option>
                            <option value="national_holiday">Libur Nasional</option>
                            <option value="joint_leave">Cuti Bersama</option>
                            <option value="weekend">Weekend</option>
                        </select>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={handleReset}
                                className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                {/* Table of Configurations */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-3.5">Nama Configuration</th>
                                    <th className="px-4 py-3.5">Komponen</th>
                                    <th className="px-4 py-3.5">Jenis Hari</th>
                                    <th className="px-4 py-3.5">Versi</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5">Updated At</th>
                                    <th className="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {configs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-8 text-center text-slate-400">
                                            Belum ada konfigurasi perhitungan payroll.
                                        </td>
                                    </tr>
                                ) : (
                                    configs.data.map((cfg) => (
                                        <tr key={cfg.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-3 font-medium text-slate-800">
                                                <div className="font-semibold text-slate-900">{cfg.name}</div>
                                                <div className="text-[11px] text-slate-400 font-mono">{cfg.code}</div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {cfg.pay_component ? (
                                                    <span className="font-medium text-slate-800">
                                                        {cfg.pay_component.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 italic">Semua</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                                        cfg.day_type === 'working_day'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-amber-100 text-amber-800'
                                                    }`}
                                                >
                                                    {cfg.day_type_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-md font-mono text-[11px] font-semibold">
                                                    v{cfg.current_version}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {cfg.is_active ? (
                                                    <span className="inline-flex items-center text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                                        <Check className="w-3 h-3 mr-1" />
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center text-[11px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-500 text-[11px]">
                                                {cfg.updated_at}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center space-x-1.5">
                                                    {/* View Formula Preview */}
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewItem(cfg)}
                                                        title="Lihat Formula"
                                                        className="p-1 text-slate-400 hover:text-indigo-600 rounded transition"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </button>

                                                    {/* Edit */}
                                                    {can('payroll.configuration.update') && (
                                                        <Link
                                                            href={route('payroll-configurations.edit', cfg.id)}
                                                            title="Edit Konfigurasi"
                                                            className="p-1 text-slate-400 hover:text-blue-600 rounded transition"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </Link>
                                                    )}

                                                    {/* Duplicate */}
                                                    {can('payroll.configuration.create') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDuplicate(cfg)}
                                                            title="Duplikat Konfigurasi"
                                                            className="p-1 text-slate-400 hover:text-emerald-600 rounded transition"
                                                        >
                                                            <Copy className="w-4 h-4" />
                                                        </button>
                                                    )}

                                                    {/* Activate / Deactivate Toggle */}
                                                    {can('payroll.configuration.update') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleToggleStatus(cfg)}
                                                            title={cfg.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                            className="p-1 text-slate-400 hover:text-amber-600 rounded transition"
                                                        >
                                                            {cfg.is_active ? (
                                                                <ToggleRight className="w-5 h-5 text-emerald-600" />
                                                            ) : (
                                                                <ToggleLeft className="w-5 h-5 text-slate-400" />
                                                            )}
                                                        </button>
                                                    )}

                                                    {/* Delete */}
                                                    {can('payroll.configuration.delete') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => confirmDelete(cfg)}
                                                            title={
                                                                cfg.is_used_in_finalized_payslips
                                                                    ? 'Tidak dapat dihapus karena telah digunakan pada slip gaji finalized'
                                                                    : 'Hapus Konfigurasi'
                                                            }
                                                            className={`p-1 rounded transition ${
                                                                cfg.is_used_in_finalized_payslips
                                                                    ? 'text-slate-300 cursor-not-allowed'
                                                                    : 'text-slate-400 hover:text-rose-600'
                                                            }`}
                                                            disabled={cfg.is_used_in_finalized_payslips}
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {configs.links && configs.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>Menampilkan {configs.data.length} dari {configs.total} data</div>
                            <div className="flex space-x-1">
                                {configs.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`px-2.5 py-1 rounded text-xs ${
                                            link.active
                                                ? 'bg-indigo-600 text-white font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100'
                                        } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Formula Preview Modal */}
            <Modal show={!!previewItem} onClose={() => setPreviewItem(null)}>
                <div className="p-6 space-y-4 max-w-2xl mx-auto">
                    <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div className="flex items-center gap-2">
                            <Sparkles className="h-5 w-5 text-indigo-600" />
                            <h3 className="font-bold text-slate-800 text-base">
                                Preview Formula: {previewItem?.name}
                            </h3>
                        </div>
                        <span className="font-mono text-xs bg-indigo-100 text-indigo-700 px-2.5 py-0.5 rounded-full font-semibold">
                            Versi {previewItem?.current_version}
                        </span>
                    </div>

                    {/* Ketentuan Potongan Jam Lembur (Threshold Box) */}
                    <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-blue-900">
                                {previewItem?.day_type === 'holiday' || previewItem?.day_type === 'national_holiday' || previewItem?.day_type === 'joint_leave'
                                    ? 'Ketentuan Lembur Hari Libur'
                                    : 'Ketentuan Lembur Hari Biasa'}
                            </span>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-blue-600 text-white">
                                {previewItem?.day_type === 'holiday' || previewItem?.day_type === 'national_holiday' || previewItem?.day_type === 'joint_leave'
                                    ? 'Threshold ≥ 4.0 jam (≥ 240 mnt)'
                                    : 'Threshold ≥ 3.0 jam (≥ 180 mnt)'}
                            </span>
                        </div>
                        <p className="text-xs text-blue-900 font-medium">
                            {previewItem?.day_type === 'holiday' || previewItem?.day_type === 'national_holiday' || previewItem?.day_type === 'joint_leave'
                                ? 'Potongan istirahat mulai berlaku pada lembur ≥ 4 jam (≥ 240 menit). Besar potongan: Jumlah Hari Lembur × 1.0 jam.'
                                : 'Potongan istirahat mulai berlaku pada lembur ≥ 3 jam (≥ 180 menit). Besar potongan: Jumlah Hari Lembur × 0.5 jam.'}
                        </p>

                        {/* Contoh Pengujian Threshold */}
                        <div className="mt-2 pt-2 border-t border-blue-200/60">
                            <div className="text-[11px] font-semibold text-blue-800 mb-1.5">
                                Contoh Pengujian Durasi Lembur:
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                {previewItem?.day_type === 'holiday' || previewItem?.day_type === 'national_holiday' || previewItem?.day_type === 'joint_leave' ? (
                                    <>
                                        <div className="bg-white rounded-lg p-2 border border-blue-200 shadow-xs">
                                            <div className="font-mono font-bold text-slate-800">3:59</div>
                                            <div className="text-[11px] text-slate-500 font-medium mt-0.5">Tidak dipotong</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">4:00</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">4:01</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">5:00</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div className="bg-white rounded-lg p-2 border border-blue-200 shadow-xs">
                                            <div className="font-mono font-bold text-slate-800">2:59</div>
                                            <div className="text-[11px] text-slate-500 font-medium mt-0.5">Tidak dipotong</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">3:00</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">3:01</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                        <div className="bg-amber-50 rounded-lg p-2 border border-amber-200 shadow-xs">
                                            <div className="font-mono font-bold text-amber-900">4:00</div>
                                            <div className="text-[11px] text-amber-700 font-bold mt-0.5">Dipotong (YA)</div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Dark Code Box for Full Formula Preview Text */}
                    <div className="bg-slate-900 text-indigo-100 p-4 rounded-xl text-xs font-mono space-y-2 leading-relaxed">
                        <div className="text-slate-400 text-[11px] uppercase tracking-wider font-sans">
                            Rumus & Evaluasi Engine:
                        </div>
                        <div className="text-white font-medium whitespace-pre-wrap">
                            {formatFormulaPreviewText(previewItem?.formula_preview)}
                        </div>
                    </div>

                    <div className="text-xs text-slate-500 bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1">
                        <div><strong>Komponen Terkait:</strong> {previewItem?.pay_component?.name || '-'}</div>
                        <div><strong>Jenis Hari:</strong> {previewItem?.day_type_label || '-'}</div>
                        <div><strong>Tarif Dasar:</strong> Diambil dari default_overtime_rate Master Karyawan</div>
                    </div>

                    <div className="flex justify-end pt-2">
                        <SecondaryButton onClick={() => setPreviewItem(null)}>Tutup</SecondaryButton>
                    </div>
                </div>
            </Modal>

            {/* Delete Confirmation Modal */}
            <Modal show={!!deleteItem} onClose={() => setDeleteItem(null)}>
                <div className="p-6 space-y-4">
                    <div className="flex items-center space-x-3 text-rose-600">
                        <AlertCircle className="w-6 h-6 flex-shrink-0" />
                        <h3 className="font-bold text-slate-800 text-base">Hapus Konfigurasi Perhitungan</h3>
                    </div>
                    <p className="text-xs text-slate-600">
                        Apakah Anda yakin ingin menghapus konfigurasi <strong>{deleteItem?.name}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div className="flex justify-end space-x-2 pt-2">
                        <SecondaryButton onClick={() => setDeleteItem(null)}>Batal</SecondaryButton>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition"
                        >
                            Hapus Konfigurasi
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
