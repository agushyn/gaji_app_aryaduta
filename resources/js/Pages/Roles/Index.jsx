import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Check, Shield, ShieldAlert, Users } from 'lucide-react';

export default function RolesIndex({ roles, groupedPermissions }) {
    const getRoleColor = (roleName) => {
        switch (roleName) {
            case 'super_admin':
                return {
                    badge: 'bg-purple-50 text-purple-700 border-purple-200',
                    bar: 'bg-purple-600',
                };
            case 'personalia':
                return {
                    badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    bar: 'bg-emerald-600',
                };
            case 'approver':
                return {
                    badge: 'bg-amber-50 text-amber-700 border-amber-200',
                    bar: 'bg-amber-600',
                };
            case 'auditor':
                return {
                    badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    bar: 'bg-cyan-600',
                };
            default:
                return {
                    badge: 'bg-slate-50 text-slate-700 border-slate-200',
                    bar: 'bg-slate-600',
                };
        }
    };

    const getGroupLabel = (group) => {
        const labels = {
            users: 'Manajemen Pengguna',
            roles: 'Peran & Izin',
            employees: 'Master Karyawan',
            'payroll-periods': 'Periode Gaji',
            'pay-components': 'Komponen Gaji',
            payslips: 'Slip Gaji',
            approvals: 'Workflow Persetujuan',
            deliveries: 'Pengiriman Slip',
            'audit-logs': 'Audit Trail',
            settings: 'Pengaturan Sistem',
        };
        return labels[group] || group;
    };

    return (
        <AuthenticatedLayout header="Peran & Hak Akses (RBAC)">
            <Head title="Peran & Hak Akses" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                        Matriks Peran & Hak Akses Sistem
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Fondasi RBAC dikelola server-side dengan pemisahan tugas (segregation of duties) sesuai PRD.
                    </p>
                </div>

                {/* Segregation of Duties Notice */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="flex items-start gap-3">
                        <div className="rounded-xl bg-blue-50 p-2 text-[#0B57D0] shrink-0">
                            <Shield className="h-5 w-5" />
                        </div>
                        <div className="text-xs text-slate-600">
                            <span className="font-bold text-slate-900 block text-sm">
                                Prinsip Pemisahan Tugas (Segregation of Duties)
                            </span>
                            Pembuat draft slip gaji (Personalia) tidak menjadi pihak penyetujui (Approver). Auditor hanya memiliki hak akses baca untuk pemeriksaan kepatuhan tanpa wewenang mengubah data atau memulai pengiriman.
                        </div>
                    </div>
                </div>

                {/* Roles Cards */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {roles?.map((role) => {
                        const style = getRoleColor(role.name);
                        const assignedPermIds = new Set(role.permissions?.map((p) => p.id));
                        const isSuperAdmin = role.name === 'super_admin';

                        return (
                            <div
                                key={role.id}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs flex flex-col"
                            >
                                <div className={`h-1.5 w-full ${style.bar}`} />
                                <div className="p-5 flex-1 flex flex-col">
                                    <div className="flex items-center justify-between">
                                        <span
                                            className={`rounded-md px-2.5 py-1 text-xs font-bold border ${style.badge}`}
                                        >
                                            {role.label}
                                        </span>
                                        <span className="text-[11px] text-slate-400 font-mono">
                                            {role.users?.length || 0} akun pengguna
                                        </span>
                                    </div>

                                    <p className="mt-3 text-xs text-slate-600 min-h-[40px]">
                                        {role.description}
                                    </p>

                                    <div className="mt-4 pt-4 border-t border-slate-100 flex-1">
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                                            Izin yang Diberikan {isSuperAdmin ? '(Seluruh Hak Akses)' : ''}
                                        </h3>

                                        <div className="space-y-4 max-h-72 overflow-y-auto pr-1">
                                            {Object.entries(groupedPermissions || {}).map(([group, perms]) => {
                                                const hasAnyInGroup = isSuperAdmin || perms.some((p) => assignedPermIds.has(p.id));

                                                if (!hasAnyInGroup && !isSuperAdmin) return null;

                                                return (
                                                    <div key={group} className="text-xs">
                                                        <span className="font-semibold text-slate-800 block text-[11px] mb-1">
                                                            {getGroupLabel(group)}
                                                        </span>
                                                        <ul className="space-y-1">
                                                            {perms.map((p) => {
                                                                const isAssigned = isSuperAdmin || assignedPermIds.has(p.id);
                                                                if (!isAssigned) return null;

                                                                return (
                                                                    <li
                                                                        key={p.id}
                                                                        className="flex items-center gap-2 text-slate-600 text-[11px]"
                                                                    >
                                                                        <Check className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                                                                        <span>{p.label}</span>
                                                                    </li>
                                                                );
                                                            })}
                                                        </ul>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
