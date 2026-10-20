import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    Edit3,
    Eye,
    FolderTree,
    LayoutList,
    Mail,
    Maximize2,
    Minimize2,
    Phone,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    UserCheck,
    UserPlus,
    UserX,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';

export default function EmployeesIndex({ employees, offices = [], positions = [], departments = [], counts, filters }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [search, setSearch] = useState(filters?.search || '');
    const [officeId, setOfficeId] = useState(filters?.office_id || '');
    const [department, setDepartment] = useState(filters?.department || '');
    const [division, setDivision] = useState(filters?.division || '');
    const [positionId, setPositionId] = useState(filters?.position_id || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [viewMode, setViewMode] = useState(filters?.view_mode || 'table');

    // Accordion state for grouped view (default: all expanded)
    const [collapsedDepartments, setCollapsedDepartments] = useState({});

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*')) return true;
        return user.permissions?.includes(permission);
    };

    // Cascading divisions for the filter bar
    const selectedDepartmentObj = useMemo(() => {
        if (!department || department === 'all') return null;
        return departments?.find(
            (d) => String(d.id) === String(department) || d.name === department || d.code === department
        );
    }, [department, departments]);

    const filterDivisions = selectedDepartmentObj?.divisions || [];

    const handleDepartmentFilterChange = (e) => {
        const newDept = e.target.value;
        setDepartment(newDept);
        setDivision(''); // Reset division when department changes
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('employees.index'),
            { search, office_id: officeId, department, division, position_id: positionId, status, view_mode: viewMode },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setOfficeId('');
        setDepartment('');
        setDivision('');
        setPositionId('');
        setStatus('');
        router.get(
            route('employees.index'),
            { view_mode: viewMode },
            { preserveState: true, replace: true }
        );
    };

    const toggleViewMode = (mode) => {
        setViewMode(mode);
        router.get(
            route('employees.index'),
            { search, office_id: officeId, department, division, position_id: positionId, status, view_mode: mode },
            { preserveState: true, replace: true }
        );
    };

    const toggleDepartmentCollapse = (deptName) => {
        setCollapsedDepartments((prev) => ({
            ...prev,
            [deptName]: !prev[deptName],
        }));
    };

    const expandAllDepartments = () => {
        setCollapsedDepartments({});
    };

    const collapseAllDepartments = (groupNames) => {
        const all = {};
        groupNames.forEach((name) => {
            all[name] = true;
        });
        setCollapsedDepartments(all);
    };

    const handleToggleStatus = (employee) => {
        const actionText = employee.employment_status === 'active' ? 'menonaktifkan' : 'mengaktifkan';
        if (confirm(`Apakah Anda yakin ingin ${actionText} karyawan ${employee.full_name}?`)) {
            router.patch(route('employees.toggle-status', employee.id), {}, { preserveScroll: true });
        }
    };

    const handleDelete = (employee) => {
        if (
            confirm(
                `Apakah Anda yakin ingin menghapus data karyawan ${employee.full_name}? Tindakan ini tidak dapat dibatalkan.`
            )
        ) {
            router.delete(route('employees.destroy', employee.id));
        }
    };

    // Group employees by Department and Division for accordion view
    const groupedByDepartment = useMemo(() => {
        const groups = {};
        (employees?.data || []).forEach((emp) => {
            const deptName = emp.department?.name || (typeof emp.department === 'string' ? emp.department : '') || 'Tanpa Departemen';
            if (!groups[deptName]) {
                groups[deptName] = {
                    id: emp.department?.id || 'none',
                    name: deptName,
                    code: emp.department?.code || '',
                    divisions: {},
                    totalCount: 0,
                };
            }
            const divName = emp.division?.name || (typeof emp.division === 'string' ? emp.division : '') || 'Tanpa Divisi';
            if (!groups[deptName].divisions[divName]) {
                groups[deptName].divisions[divName] = {
                    id: emp.division?.id || 'none',
                    name: divName,
                    code: emp.division?.code || '',
                    employees: [],
                };
            }
            groups[deptName].divisions[divName].employees.push(emp);
            groups[deptName].totalCount++;
        });

        return Object.values(groups).map((grp) => ({
            ...grp,
            divisionsList: Object.values(grp.divisions),
        }));
    }, [employees?.data]);

    return (
        <AuthenticatedLayout header="Master Data Karyawan">
            <Head title="Master Karyawan" />

            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 text-[#0B57D0]">
                                <Users className="h-6 w-6" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                    Master Data Karyawan
                                </h1>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Kelola data personalia, relasi departemen, divisi, dan komponen gaji tetap.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* View Mode Toggle: Tabel vs Grouping */}
                        <div className="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-xs">
                            <button
                                type="button"
                                onClick={() => toggleViewMode('table')}
                                className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                    viewMode === 'table'
                                        ? 'bg-[#0A58CA] text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                                }`}
                                title="Tampilan Tabel Standar"
                            >
                                <LayoutList className="h-3.5 w-3.5" />
                                <span>Tabel Biasa</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => toggleViewMode('grouped')}
                                className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                    viewMode === 'grouped'
                                        ? 'bg-[#0A58CA] text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                                }`}
                                title="Tampilan Dikelompokkan per Departemen & Divisi"
                            >
                                <FolderTree className="h-3.5 w-3.5" />
                                <span>Grouping Departemen</span>
                            </button>
                        </div>

                        {can('employees.manage') && (
                            <Link
                                href={route('employees.create')}
                                className="inline-flex items-center gap-2 rounded-xl bg-[#0A58CA] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1A73E8] transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                            >
                                <UserPlus className="h-4 w-4" />
                                <span>Tambah Karyawan</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Counts Overview Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Total Karyawan
                                </p>
                                <p className="mt-1 text-2xl font-bold text-slate-900">
                                    {counts?.total || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-blue-50 p-2.5 text-[#0B57D0]">
                                <Users className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-emerald-600">
                                    Karyawan Aktif
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-700">
                                    {counts?.active || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                                <UserCheck className="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Karyawan Nonaktif
                                </p>
                                <p className="mt-1 text-2xl font-bold text-slate-700">
                                    {counts?.inactive || 0}
                                </p>
                            </div>
                            <div className="rounded-xl bg-slate-100 p-2.5 text-slate-600">
                                <UserX className="h-5 w-5" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Search & Cascading Filter Bar */}
                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[220px]">
                            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama, NIP, jabatan, email..."
                                className="w-full rounded-xl border border-slate-200 pl-10 pr-4 py-2 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] placeholder:text-slate-400"
                            />
                        </div>

                        {/* Kantor Filter */}
                        <div className="w-full sm:w-44">
                            <select
                                value={officeId}
                                onChange={(e) => setOfficeId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 py-2 px-3 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="">Semua Kantor</option>
                                {offices?.map((off) => (
                                    <option key={off.id} value={off.id}>
                                        {off.name} ({off.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Jabatan Filter */}
                        <div className="w-full sm:w-40">
                            <select
                                value={positionId}
                                onChange={(e) => setPositionId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 py-2 px-3 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="">Semua Jabatan</option>
                                {positions?.map((pos) => (
                                    <option key={pos.id} value={pos.id}>
                                        {pos.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Status Filter */}
                        <div className="w-full sm:w-36">
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 py-2 px-3 text-xs focus:border-[#0B57D0] focus:ring-1 focus:ring-[#0B57D0] text-slate-700 font-medium"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Hanya Aktif</option>
                                <option value="inactive">Hanya Nonaktif</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                className="rounded-xl bg-[#0A58CA] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1A73E8] transition"
                            >
                                Terapkan Filter
                            </button>

                            {(search || officeId || positionId || department || division || status) && (
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* VIEW 1: Regular Table Mode */}
                {viewMode === 'table' && (
                    <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead className="bg-slate-50/80 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th className="px-5 py-3.5">NIP</th>
                                        <th className="px-5 py-3.5">Nama Karyawan</th>
                                        <th className="px-5 py-3.5">Kantor</th>
                                        <th className="px-5 py-3.5">Divisi</th>
                                        <th className="px-5 py-3.5">Jabatan</th>
                                        <th className="px-5 py-3.5 text-right">Upah Lembur</th>
                                        <th className="px-5 py-3.5 text-center">Status</th>
                                        <th className="px-5 py-3.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white text-slate-600">
                                    {employees?.data?.length > 0 ? (
                                        employees.data.map((emp) => (
                                            <tr key={emp.id} className="hover:bg-slate-50/60 transition">
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    <span className="font-mono text-xs font-bold text-[#0B57D0] bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100">
                                                        {emp.employee_number}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4 font-semibold text-slate-900">
                                                    <Link
                                                        href={route('employees.show', emp.id)}
                                                        className="hover:text-[#0B57D0] transition"
                                                    >
                                                        {emp.full_name}
                                                    </Link>
                                                </td>

                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {emp.office ? (
                                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-semibold text-xs border border-indigo-100">
                                                            <Building2 className="h-3 w-3" />
                                                            <span>{emp.office?.name ?? (typeof emp.office === 'string' ? emp.office : '-')}</span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 italic text-[11px]">-</span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {emp.division ? (
                                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 text-slate-700 font-medium text-xs border border-slate-200">
                                                            <FolderTree className="h-3 w-3 text-slate-400" />
                                                            <span>{emp.division?.name ?? (typeof emp.division === 'string' ? emp.division : '-')}</span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 italic text-[11px]">-</span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 text-slate-700 font-medium whitespace-nowrap">
                                                    {emp.position?.name ?? (typeof emp.position === 'string' ? emp.position : '-')}
                                                </td>

                                                <td className="px-5 py-4 text-right whitespace-nowrap font-mono text-xs">
                                                    {Number(emp.default_overtime_rate || 0) > 0 ? (
                                                        <span className="font-semibold text-slate-900">
                                                            Rp {Number(emp.default_overtime_rate).toLocaleString('id-ID')}
                                                            <span className="text-[10px] text-slate-400 font-normal"> /jam</span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 italic">Rp0</span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 text-center whitespace-nowrap">
                                                    <span
                                                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                                            emp.employment_status === 'active'
                                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                                : 'bg-rose-50 text-rose-700 border border-rose-200'
                                                        }`}
                                                    >
                                                        {emp.employment_status === 'active' ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4 text-right whitespace-nowrap">
                                                    <div className="inline-flex items-center gap-1.5 justify-end">
                                                        <Link
                                                            href={route('employees.show', emp.id)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition"
                                                            title="Lihat Detail Profil"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>

                                                        {can('employees.manage') && (
                                                            <>
                                                                <Link
                                                                    href={route('employees.edit', emp.id)}
                                                                    className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition"
                                                                    title="Edit Karyawan"
                                                                >
                                                                    <Edit3 className="h-4 w-4" />
                                                                </Link>

                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleToggleStatus(emp)}
                                                                    className={`p-1.5 rounded-lg transition ${
                                                                        emp.employment_status === 'active'
                                                                            ? 'text-emerald-600 hover:bg-emerald-50'
                                                                            : 'text-slate-400 hover:bg-slate-100'
                                                                    }`}
                                                                    title={
                                                                        emp.employment_status === 'active'
                                                                            ? 'Nonaktifkan Karyawan'
                                                                            : 'Aktifkan Karyawan'
                                                                    }
                                                                >
                                                                    {emp.employment_status === 'active' ? (
                                                                        <ToggleRight className="h-5 w-5" />
                                                                    ) : (
                                                                        <ToggleLeft className="h-5 w-5" />
                                                                    )}
                                                                </button>

                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDelete(emp)}
                                                                    className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                                    title="Hapus Karyawan"
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
                                            <td colSpan={8} className="px-5 py-12 text-center text-slate-500">
                                                <Users className="h-8 w-8 mx-auto text-slate-300 mb-2" />
                                                <p className="font-semibold text-slate-700">Tidak ada data karyawan ditemukan</p>
                                                <p className="text-xs text-slate-400 mt-1">
                                                    Silakan sesuaikan kriteria pencarian atau tambahkan karyawan baru.
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination for regular table view */}
                        {employees?.links && employees.links.length > 3 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 bg-slate-50/50">
                                <p className="text-xs text-slate-500">
                                    Menampilkan <span className="font-semibold">{employees.from || 0}</span> sampai{' '}
                                    <span className="font-semibold">{employees.to || 0}</span> dari{' '}
                                    <span className="font-semibold">{employees.total || 0}</span> data
                                </p>
                                <div className="flex gap-1">
                                    {employees.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 text-xs rounded-lg transition font-medium ${
                                                link.active
                                                    ? 'bg-[#0A58CA] text-white'
                                                    : link.url
                                                    ? 'text-slate-600 hover:bg-slate-200'
                                                    : 'text-slate-300 cursor-not-allowed'
                                            }`}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* VIEW 2: Grouping Karyawan Berdasarkan Departemen (Accordion / Grouped Hierarchy) */}
                {viewMode === 'grouped' && (
                    <div className="space-y-4">
                        {/* Group View Controls */}
                        <div className="flex items-center justify-between px-1">
                            <p className="text-xs text-slate-500">
                                Menampilkan hierarki struktur organisasi:{' '}
                                <span className="font-semibold text-slate-700">Departemen</span> &gt;{' '}
                                <span className="font-semibold text-slate-700">Divisi</span> &gt;{' '}
                                <span className="font-semibold text-slate-700">Karyawan</span>
                            </p>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={expandAllDepartments}
                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-[#0B57D0] hover:underline"
                                >
                                    <Maximize2 className="h-3 w-3" />
                                    <span>Buka Semua</span>
                                </button>
                                <span className="text-slate-300">|</span>
                                <button
                                    type="button"
                                    onClick={() => collapseAllDepartments(groupedByDepartment.map((g) => g.name))}
                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 hover:underline"
                                >
                                    <Minimize2 className="h-3 w-3" />
                                    <span>Tutup Semua</span>
                                </button>
                            </div>
                        </div>

                        {groupedByDepartment.length > 0 ? (
                            groupedByDepartment.map((group) => {
                                const isCollapsed = Boolean(collapsedDepartments[group.name]);

                                return (
                                    <div
                                        key={group.name}
                                        className="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden transition"
                                    >
                                        {/* Department Header Accordion Bar */}
                                        <button
                                            type="button"
                                            onClick={() => toggleDepartmentCollapse(group.name)}
                                            className="w-full flex items-center justify-between px-5 py-4 bg-slate-50/90 hover:bg-blue-50/40 border-b border-slate-200/70 text-left transition"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="p-1 rounded-md bg-white border border-slate-200 text-slate-500">
                                                    {isCollapsed ? (
                                                        <ChevronRight className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4 text-[#0B57D0]" />
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Building2 className="h-4 w-4 text-[#0B57D0]" />
                                                    <span className="font-bold text-sm text-slate-900 tracking-tight">
                                                        {group.name}
                                                    </span>
                                                    {group.code && (
                                                        <span className="px-2 py-0.5 rounded-md bg-blue-100 text-[#0B57D0] text-[11px] font-bold font-mono">
                                                            {group.code}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <span className="px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700 text-xs font-semibold shadow-2xs">
                                                    {group.totalCount} Karyawan
                                                </span>
                                            </div>
                                        </button>

                                        {/* Divisions and Employees Inside Department */}
                                        {!isCollapsed && (
                                            <div className="p-5 space-y-6">
                                                {group.divisionsList.map((divGroup) => (
                                                    <div
                                                        key={divGroup.name}
                                                        className="rounded-xl border border-slate-200 bg-slate-50/40 p-4"
                                                    >
                                                        {/* Division Sub-header */}
                                                        <div className="flex items-center justify-between pb-3 border-b border-slate-200">
                                                            <div className="flex items-center gap-2">
                                                                <FolderTree className="h-4 w-4 text-slate-600" />
                                                                <h4 className="font-bold text-xs uppercase tracking-wider text-slate-800">
                                                                    {divGroup.name}
                                                                </h4>
                                                                {divGroup.code && (
                                                                    <span className="text-[10px] font-mono text-slate-500">
                                                                        ({divGroup.code})
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <span className="text-xs text-slate-500 font-medium">
                                                                {divGroup.employees.length} personil
                                                            </span>
                                                        </div>

                                                        {/* Employee Mini Table */}
                                                        <div className="mt-3 divide-y divide-slate-100">
                                                            {divGroup.employees.map((emp) => (
                                                                <div
                                                                    key={emp.id}
                                                                    className="flex flex-col sm:flex-row sm:items-center justify-between py-2.5 hover:bg-white rounded-lg px-2 transition gap-2"
                                                                >
                                                                    <div className="flex items-center gap-3 min-w-0">
                                                                        <span className="font-mono text-xs font-bold text-[#0B57D0] bg-blue-50 px-2 py-0.5 rounded border border-blue-100 shrink-0">
                                                                            {emp.employee_number}
                                                                        </span>
                                                                        <div className="min-w-0">
                                                                            <Link
                                                                                href={route('employees.show', emp.id)}
                                                                                className="font-bold text-xs text-slate-900 hover:text-[#0B57D0] truncate block"
                                                                            >
                                                                                {emp.full_name}
                                                                            </Link>
                                                                            <span className="text-[11px] text-slate-500 block truncate">
                                                                                {emp.position?.name ?? (typeof emp.position === 'string' ? emp.position : 'Staff')}
                                                                                {Number(emp.default_overtime_rate || 0) > 0 ? (
                                                                                    <span className="font-mono text-slate-600"> • Lembur: Rp {Number(emp.default_overtime_rate).toLocaleString('id-ID')}/jam</span>
                                                                                ) : null}
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    <div className="flex items-center gap-4 shrink-0 justify-between sm:justify-end">
                                                                        {emp.phone && (
                                                                            <span className="font-mono text-[11px] text-emerald-700 flex items-center gap-1">
                                                                                <Phone className="h-3 w-3 text-emerald-500" />
                                                                                <span>+{emp.phone}</span>
                                                                            </span>
                                                                        )}

                                                                        <span
                                                                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                                                                                emp.employment_status === 'active'
                                                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                                                    : 'bg-rose-50 text-rose-700 border border-rose-200'
                                                                            }`}
                                                                        >
                                                                            {emp.employment_status === 'active' ? 'Aktif' : 'Nonaktif'}
                                                                        </span>

                                                                        <div className="inline-flex items-center gap-1">
                                                                            <Link
                                                                                href={route('employees.show', emp.id)}
                                                                                className="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                                                                                title="Lihat Detail"
                                                                            >
                                                                                <Eye className="h-3.5 w-3.5" />
                                                                            </Link>
                                                                            {can('employees.manage') && (
                                                                                <Link
                                                                                    href={route('employees.edit', emp.id)}
                                                                                    className="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                                                                                    title="Edit"
                                                                                >
                                                                                    <Edit3 className="h-3.5 w-3.5" />
                                                                                </Link>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        ) : (
                            <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                                <Building2 className="h-8 w-8 mx-auto text-slate-300 mb-2" />
                                <p className="font-semibold text-slate-700">Tidak ada data karyawan untuk dikelompokkan</p>
                                <p className="text-xs text-slate-400 mt-1">
                                    Silakan sesuaikan filter pencarian atau tambahkan karyawan.
                                </p>
                            </div>
                        )}

                        {/* Pagination for grouped view */}
                        {employees?.links && employees.links.length > 3 && (
                            <div className="flex items-center justify-between border-t border-slate-100 pt-3">
                                <p className="text-xs text-slate-500">
                                    Menampilkan <span className="font-semibold">{employees.from || 0}</span> sampai{' '}
                                    <span className="font-semibold">{employees.to || 0}</span> dari{' '}
                                    <span className="font-semibold">{employees.total || 0}</span> data
                                </p>
                                <div className="flex gap-1">
                                    {employees.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 text-xs rounded-lg transition font-medium ${
                                                link.active
                                                    ? 'bg-[#0A58CA] text-white'
                                                    : link.url
                                                    ? 'text-slate-600 hover:bg-slate-200'
                                                    : 'text-slate-300 cursor-not-allowed'
                                            }`}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
