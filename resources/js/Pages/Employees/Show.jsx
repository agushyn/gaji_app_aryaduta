import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Banknote,
    Briefcase,
    Building2,
    Calendar,
    Clock,
    Edit3,
    ExternalLink,
    FolderTree,
    History,
    Mail,
    Phone,
    Shield,
    Trash2,
    User,
    UserCheck,
    UserX,
} from 'lucide-react';

export default function EmployeesShow({ employee, auditLogs = [] }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    const handleToggleStatus = () => {
        const actionText = employee.employment_status === 'active' ? 'menonaktifkan' : 'mengaktifkan';
        if (confirm(`Apakah Anda yakin ingin ${actionText} karyawan ${employee.full_name}?`)) {
            router.patch(route('employees.toggle-status', employee.id));
        }
    };

    const handleDelete = () => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus data karyawan ${employee.full_name}? Data dengan riwayat slip gaji tidak dapat dihapus.`
            )
        ) {
            router.delete(route('employees.destroy', employee.id));
        }
    };

    const formatActionName = (action) => {
        const map = {
            'employee.created': 'Karyawan Didaftarkan',
            'employee.updated': 'Data Diperbarui',
            'employee.status_toggled': 'Status Diubah',
            'employee.deleted': 'Karyawan Dihapus',
        };
        return map[action] || action;
    };

    const getActionBadgeClass = (action) => {
        if (action.includes('created')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (action.includes('updated')) return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        if (action.includes('status')) return 'bg-amber-50 text-amber-700 border-amber-200';
        if (action.includes('deleted')) return 'bg-rose-50 text-rose-700 border-rose-200';
        return 'bg-slate-50 text-slate-700 border-slate-200';
    };

    return (
        <AuthenticatedLayout header={`Detail Karyawan: ${employee.full_name}`}>
            <Head title={`Karyawan - ${employee.full_name}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center justify-between">
                    <Link
                        href={route('employees.index')}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Daftar Karyawan</span>
                    </Link>

                    {can('employees.manage') && (
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('employees.edit', employee.id)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                            >
                                <Edit3 className="h-3.5 w-3.5 text-indigo-600" />
                                <span>Ubah Data</span>
                            </Link>

                            <button
                                type="button"
                                onClick={handleToggleStatus}
                                className={`inline-flex items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-semibold shadow-xs transition ${
                                    employee.employment_status === 'active'
                                        ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                        : 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
                                }`}
                            >
                                {employee.employment_status === 'active' ? (
                                    <>
                                        <UserX className="h-3.5 w-3.5" />
                                        <span>Nonaktifkan Karyawan</span>
                                    </>
                                ) : (
                                    <>
                                        <UserCheck className="h-3.5 w-3.5" />
                                        <span>Aktifkan Karyawan</span>
                                    </>
                                )}
                            </button>

                            <button
                                type="button"
                                onClick={handleDelete}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-600 shadow-xs hover:bg-rose-50 transition"
                                title="Hapus Karyawan"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
                    )}
                </div>

                {/* Main Profile Header Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-6">
                        <div className="flex items-start gap-4">
                            <div className="h-16 w-16 rounded-2xl bg-[#0B57D0] text-white font-bold text-xl flex items-center justify-center shadow-xs shrink-0">
                                {employee.full_name
                                    ?.split(' ')
                                    .slice(0, 2)
                                    .map((n) => n[0])
                                    .join('')
                                    .toUpperCase() || 'KR'}
                            </div>

                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                        {employee.full_name}
                                    </h1>
                                    <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-semibold text-indigo-700 border border-indigo-100">
                                        NIK: {employee.employee_number}
                                    </span>
                                </div>

                                <div className="mt-1 flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-500">
                                    {employee.office && (
                                        <span className="flex items-center gap-1 font-semibold text-indigo-700">
                                            <Building2 className="h-3.5 w-3.5 text-indigo-600" />
                                            {employee.office?.name ?? (typeof employee.office === 'string' ? employee.office : '-')}
                                        </span>
                                    )}
                                    <span className="flex items-center gap-1">
                                        <Briefcase className="h-3.5 w-3.5 text-slate-400" />
                                        {employee.position?.name ?? (typeof employee.position === 'string' ? employee.position : 'Staff')}
                                    </span>
                                    {employee.division && (
                                        <span className="flex items-center gap-1">
                                            <FolderTree className="h-3.5 w-3.5 text-slate-400" />
                                            {employee.division?.name ?? (typeof employee.division === 'string' ? employee.division : '-')}
                                        </span>
                                    )}
                                    <span className="flex items-center gap-1">
                                        <Calendar className="h-3.5 w-3.5 text-slate-400" />
                                        Mulai: {employee.hire_date
                                            ? new Date(employee.hire_date).toLocaleDateString('id-ID', {
                                                  day: '2-digit',
                                                  month: 'long',
                                                  year: 'numeric',
                                              })
                                            : 'Tidak tercatat'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            {employee.employment_status === 'active' ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    Karyawan Aktif
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 border border-rose-200">
                                    <span className="h-2 w-2 rounded-full bg-rose-500" />
                                    Karyawan Nonaktif
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Detailed Data Grid */}
                    <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Section 1: Kepegawaian */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4 space-y-3">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                <User className="h-4 w-4 text-indigo-600" />
                                Data Kepegawaian
                            </h2>

                            <dl className="grid grid-cols-1 gap-2 text-xs">
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Nomor Induk Karyawan</dt>
                                    <dd className="font-mono font-bold text-slate-800">
                                        {employee.employee_number}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Nama Lengkap</dt>
                                    <dd className="font-medium text-slate-800">{employee.full_name}</dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Kantor</dt>
                                    <dd className="font-semibold text-indigo-700">
                                        {employee.office?.name ?? (typeof employee.office === 'string' ? employee.office : '-')} {employee.office?.code ? `(${employee.office.code})` : ''}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Departemen</dt>
                                    <dd className="font-medium text-slate-800">
                                        {employee.department?.name ?? (typeof employee.department === 'string' ? employee.department : '-')}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Divisi</dt>
                                    <dd className="font-medium text-slate-800">
                                        {employee.division?.name ?? (typeof employee.division === 'string' ? employee.division : '-')}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Posisi / Jabatan</dt>
                                    <dd className="font-medium text-slate-800">
                                        {employee.position?.name ?? (typeof employee.position === 'string' ? employee.position : '-')}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Tanggal Mulai Bekerja</dt>
                                    <dd className="font-medium text-slate-800">
                                        {employee.hire_date
                                            ? new Date(employee.hire_date).toLocaleDateString('id-ID', {
                                                  day: '2-digit',
                                                  month: 'long',
                                                  year: 'numeric',
                                              })
                                            : '-'}
                                    </dd>
                                </div>
                                <div className="flex justify-between py-1.5">
                                    <dt className="text-slate-500">Status Keaktifan</dt>
                                    <dd className="font-semibold text-slate-800">
                                        {employee.employment_status === 'active'
                                            ? 'Aktif (Menerima Gaji)'
                                            : 'Nonaktif (Penangguhan)'}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {/* Section 2: Kontak & Notifikasi Slip */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4 space-y-3">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                <Mail className="h-4 w-4 text-indigo-600" />
                                Kontak & Saluran Distribusi
                            </h2>

                            <dl className="grid grid-cols-1 gap-2 text-xs">
                                <div className="py-2 border-b border-slate-100">
                                    <dt className="text-slate-500 text-[11px]">Email Resmi Karyawan</dt>
                                    <dd className="mt-1 font-medium text-slate-800">
                                        {employee.email ? (
                                            <a
                                                href={`mailto:${employee.email}`}
                                                className="text-indigo-600 hover:underline flex items-center gap-1 font-mono"
                                            >
                                                <Mail className="h-3.5 w-3.5 text-slate-400" />
                                                <span>{employee.email}</span>
                                            </a>
                                        ) : (
                                            <span className="text-slate-400 italic">Belum diisi</span>
                                        )}
                                    </dd>
                                </div>

                                <div className="py-2 border-b border-slate-100">
                                    <dt className="text-slate-500 text-[11px]">Nomor WhatsApp (Normalisasi E.164)</dt>
                                    <dd className="mt-1 flex items-center justify-between">
                                        {employee.phone ? (
                                            <>
                                                <div className="flex items-center gap-1.5">
                                                    <Phone className="h-3.5 w-3.5 text-emerald-600" />
                                                    <span className="font-mono font-bold text-slate-800">
                                                        +{employee.phone}
                                                    </span>
                                                </div>

                                                <a
                                                    href={`https://wa.me/${employee.phone}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700 transition"
                                                >
                                                    <span>Buka Chat WA</span>
                                                    <ExternalLink className="h-3 w-3" />
                                                </a>
                                            </>
                                        ) : (
                                            <span className="text-slate-400 italic">Belum diisi</span>
                                        )}
                                    </dd>
                                </div>

                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <dt className="text-slate-500">Terdaftar Pada</dt>
                                    <dd className="font-medium text-slate-700">
                                        {new Date(employee.created_at).toLocaleString('id-ID')}
                                    </dd>
                                </div>

                                <div className="flex justify-between py-1.5">
                                    <dt className="text-slate-500">Pembaruan Terakhir</dt>
                                    <dd className="font-medium text-slate-700">
                                        {new Date(employee.updated_at).toLocaleString('id-ID')}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {/* Section 3: Komponen Gaji Tetap & Tarif Harian */}
                    <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50/50 p-5 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200/60 pb-3">
                            <div className="flex items-center gap-2">
                                <Banknote className="h-4 w-4 text-emerald-600" />
                                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    Komponen Gaji Tetap & Tarif Tunjangan Master
                                </h2>
                            </div>
                            <span className="text-[11px] text-slate-500">
                                Digunakan otomatis saat Generate Payroll
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            {/* Tetap */}
                            <div className="rounded-lg border border-slate-200 bg-white p-3.5 space-y-2">
                                <h3 className="text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                                    Gaji Tetap (Bulanan)
                                </h3>
                                <div className="space-y-1.5 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Gaji Pokok:</span>
                                        <span className="font-mono font-semibold text-slate-900">
                                            Rp {Number(employee.gaji_pokok || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Tunj. Kehadiran:</span>
                                        <span className="font-mono font-semibold text-slate-900">
                                            Rp {Number(employee.tunjangan_kehadiran || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Tunj. Keluarga:</span>
                                        <span className="font-mono font-semibold text-slate-900">
                                            Rp {Number(employee.tunjangan_keluarga || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Tunj. Jabatan:</span>
                                        <span className="font-mono font-semibold text-slate-900">
                                            Rp {Number(employee.tunjangan_jabatan || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Tarif Harian */}
                            <div className="rounded-lg border border-indigo-100 bg-indigo-50/30 p-3.5 space-y-2">
                                <h3 className="text-[11px] font-bold text-indigo-900 uppercase tracking-wide">
                                    Tarif Tunjangan Harian
                                </h3>
                                <div className="space-y-1.5 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Uang Makan / Hari:</span>
                                        <span className="font-mono font-semibold text-indigo-900">
                                            Rp {Number(employee.uang_makan_per_hari || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Transportasi / Hari:</span>
                                        <span className="font-mono font-semibold text-indigo-900">
                                            Rp {Number(employee.uang_transportasi_per_hari || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <p className="pt-1 text-[11px] text-slate-500 italic">
                                        * Dikalikan jumlah hari kerja saat payroll digenerate.
                                    </p>
                                </div>
                            </div>

                            {/* Upah Lembur Default */}
                            <div className="rounded-lg border border-amber-100 bg-amber-50/30 p-3.5 space-y-2">
                                <h3 className="text-[11px] font-bold text-amber-900 uppercase tracking-wide">
                                    Upah Lembur Default
                                </h3>
                                <div className="space-y-1.5 text-xs">
                                    <div className="flex justify-between items-baseline">
                                        <span className="text-slate-600">Tarif per Jam:</span>
                                        <span className="font-mono font-bold text-slate-900">
                                            Rp {Number(employee.default_overtime_rate || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <p className="pt-1 text-[11px] text-slate-500 italic">
                                        Tarif lembur default karyawan per jam.
                                    </p>
                                </div>
                            </div>

                            {/* BPJS */}
                            <div className="rounded-lg border border-rose-100 bg-rose-50/20 p-3.5 space-y-2">
                                <h3 className="text-[11px] font-bold text-rose-900 uppercase tracking-wide">
                                    BPJS Ketenagakerjaan
                                </h3>
                                <div className="space-y-2 text-xs">
                                    <div className="flex justify-between items-center">
                                        <span className="text-slate-600">Status:</span>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                employee.bpjs_ketenagakerjaan_enabled
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {employee.bpjs_ketenagakerjaan_enabled ? 'Aktif Terpotong' : 'Tidak Aktif'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-600">Nominal Potongan:</span>
                                        <span className="font-mono font-semibold text-rose-700">
                                            Rp {Number(employee.bpjs_ketenagakerjaan_amount || 0).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Audit Trail Section */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div className="flex items-center gap-2">
                            <History className="h-4 w-4 text-indigo-600" />
                            <h2 className="text-sm font-bold text-slate-900">
                                Riwayat Perubahan & Jejak Audit
                            </h2>
                        </div>
                        <span className="text-xs text-slate-400">
                            Menampilkan 15 aktivitas terakhir
                        </span>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-2.5">Waktu</th>
                                    <th className="px-4 py-2.5">Aktor</th>
                                    <th className="px-4 py-2.5">Tindakan</th>
                                    <th className="px-4 py-2.5">Rincian Perubahan</th>
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
                                                <div className="text-[11px] text-slate-400 font-mono">
                                                    {log.user ? log.user.email : 'system@internal'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium ${getActionBadgeClass(
                                                        log.action
                                                    )}`}
                                                >
                                                    {formatActionName(log.action)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-[11px]">
                                                {log.new_values && Object.keys(log.new_values).length > 0 ? (
                                                    <div className="space-y-1">
                                                        {Object.entries(log.new_values).map(([key, val]) => (
                                                            <div key={key} className="flex gap-1.5">
                                                                <span className="font-semibold text-slate-700">
                                                                    {key}:
                                                                </span>
                                                                {log.old_values && log.old_values[key] !== undefined ? (
                                                                    <span>
                                                                        <span className="text-rose-600 line-through">
                                                                            {String(log.old_values[key] || '-')}
                                                                        </span>
                                                                        {' -> '}
                                                                        <span className="text-emerald-700 font-medium">
                                                                            {String(val || '-')}
                                                                        </span>
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-slate-800">{String(val || '-')}</span>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">
                                                        {log.action}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="4" className="px-4 py-6 text-center text-slate-400">
                                            Belum ada aktivitas tercatat untuk data karyawan ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
