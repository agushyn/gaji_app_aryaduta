import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Briefcase,
    Building2,
    Calculator,
    Calendar,
    CalendarDays,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    FolderTree,
    Home,
    Layers,
    LogOut,
    Mail,
    Menu,
    Receipt,
    Send,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

export default function AuthenticatedLayout({ title, header, children, fullWidth = false }) {
    const { auth, flash, appName } = usePage().props;
    const user = auth.user;

    // Mobile drawer state
    const [mobileOpen, setMobileOpen] = useState(false);

    // Desktop collapsible sidebar state with safe localStorage persistence
    const [collapsed, setCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            try {
                return localStorage.getItem('app_sidebar_collapsed') === 'true';
            } catch (e) {
                return false;
            }
        }
        return false;
    });

    const [userDropdownOpen, setUserDropdownOpen] = useState(false);
    const [flashVisible, setFlashVisible] = useState(true);

    const toggleCollapse = () => {
        setCollapsed((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('app_sidebar_collapsed', String(next));
            } catch (e) {
                // Ignore storage errors
            }
            return next;
        });
    };

    useEffect(() => {
        setFlashVisible(true);
    }, [flash]);

    // Close mobile drawer and dropdown on ESC
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                setMobileOpen(false);
                setUserDropdownOpen(false);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const can = (permission) => {
        if (!user) return false;
        if (user.permissions?.includes('*') || user.roles?.includes('super_admin')) return true;
        return user.permissions?.includes(permission);
    };

    const primaryRole = user?.roles?.[0] || 'user';
    const primaryRoleLabel = user?.role_labels?.[0] || 'User';

    const getRoleBadgeColor = (role) => {
        switch (role) {
            case 'super_admin':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'personalia':
                return 'bg-emerald-100 text-emerald-800 border-emerald-200';
            case 'approver':
                return 'bg-amber-100 text-amber-800 border-amber-200';
            case 'auditor':
                return 'bg-cyan-100 text-cyan-800 border-cyan-200';
            default:
                return 'bg-slate-100 text-slate-700 border-slate-200';
        }
    };

    // Navigation configuration
    const navGroups = [
        {
            title: 'Menu Utama',
            items: [
                {
                    label: 'Dashboard',
                    route: 'dashboard',
                    icon: Home,
                    active: route().current('dashboard'),
                },
            ],
        },
        {
            title: 'Data Master',
            visible: can('offices.view') || can('divisions.view') || can('positions.view') || can('employees.view') || can('departments.view'),
            items: [
                {
                    label: 'Master Kantor',
                    route: 'master.offices.index',
                    icon: Building2,
                    active: route().current('master.offices.*'),
                    visible: can('offices.view'),
                },
                {
                    label: 'Master Divisi',
                    route: 'master.divisions.index',
                    icon: FolderTree,
                    active: route().current('master.divisions.*'),
                    visible: can('divisions.view'),
                },
                {
                    label: 'Master Jabatan',
                    route: 'master.positions.index',
                    icon: Briefcase,
                    active: route().current('master.positions.*'),
                    visible: can('positions.view'),
                },
                {
                    label: 'Master Karyawan',
                    route: 'employees.index',
                    icon: UserCheck,
                    active: route().current('employees.*'),
                    visible: can('employees.view'),
                },
                {
                    label: 'Master Departemen',
                    route: 'master.departments.index',
                    icon: Layers,
                    active: route().current('master.departments.*'),
                    visible: can('departments.view'),
                },
            ],
        },
        {
            title: 'Operasional Penggajian',
            visible: can('payroll-periods.view') || can('pay-components.view') || can('payslips.view') || can('approvals.view') || can('deliveries.view') || can('reports.view'),
            items: [
                {
                    label: 'Periode Gaji',
                    route: 'payroll-periods.index',
                    icon: Calendar,
                    active: route().current('payroll-periods.*'),
                    visible: can('payroll-periods.view'),
                },
                {
                    label: 'Komponen Gaji',
                    route: 'pay-components.index',
                    icon: Layers,
                    active: route().current('pay-components.*'),
                    visible: can('pay-components.view'),
                },
                {
                    label: 'Data Slip Gaji',
                    route: 'payslips.index',
                    icon: Receipt,
                    active: route().current('payslips.*'),
                    visible: can('payslips.view'),
                },
                {
                    label: 'Persetujuan Gaji',
                    route: 'approvals.index',
                    icon: CheckCircle,
                    active: route().current('approvals.*'),
                    visible: can('approvals.view'),
                },
                {
                    label: 'Payroll Security',
                    route: 'payroll-security.index',
                    icon: ShieldCheck,
                    active: route().current('payroll-security.*'),
                    visible: can('payroll.security.view') || can('payslips.view'),
                },
                {
                    label: 'Pelacakan Pengiriman',
                    route: 'deliveries.index',
                    icon: Send,
                    active: route().current('deliveries.index'),
                    visible: can('deliveries.view'),
                },
                {
                    label: 'Pengiriman Massal',
                    route: 'deliveries.bulk.create',
                    icon: Users,
                    active: route().current('deliveries.bulk.*'),
                    visible: can('deliveries.send'),
                },
                {
                    label: 'Laporan Pengiriman',
                    route: 'reports.deliveries.index',
                    icon: FileSpreadsheet,
                    active: route().current('reports.*'),
                    visible: can('reports.view'),
                },
            ],
        },
        {
            title: 'Konfigurasi Payroll',
            visible: can('payroll.configuration.view') || can('payroll.holiday.view'),
            items: [
                {
                    label: 'Payroll Calculation',
                    route: 'payroll-configurations.index',
                    icon: Calculator,
                    active: route().current('payroll-configurations.*'),
                    visible: can('payroll.configuration.view'),
                },
                {
                    label: 'Kalender Libur',
                    route: 'holiday-calendars.index',
                    icon: CalendarDays,
                    active: route().current('holiday-calendars.*'),
                    visible: can('payroll.holiday.view'),
                },
            ],
        },
        {
            title: 'Administrasi & Keamanan',
            visible: can('users.view') || can('roles.view') || can('settings.view') || can('audit-logs.view'),
            items: [
                {
                    label: 'Pengguna Internal',
                    route: 'users.index',
                    icon: Users,
                    active: route().current('users.*'),
                    visible: can('users.view'),
                },
                {
                    label: 'Peran & Hak Akses',
                    route: 'roles.index',
                    icon: Shield,
                    active: route().current('roles.*'),
                    visible: can('roles.view'),
                },
                {
                    label: 'Pengaturan Email SMTP',
                    route: 'settings.email.edit',
                    icon: Mail,
                    active: route().current('settings.email.*'),
                    visible: can('settings.view'),
                },
                {
                    label: 'Pengaturan WhatsApp',
                    route: 'settings.whatsapp.edit',
                    icon: Smartphone,
                    active: route().current('settings.whatsapp.*'),
                    visible: can('settings.view'),
                },
                {
                    label: 'Audit Trail Aktivitas',
                    route: 'audit-logs.index',
                    icon: Activity,
                    active: route().current('audit-logs.*'),
                    visible: can('audit-logs.view'),
                },
            ],
        },
    ];

    // Helper renderer for nav items
    const renderNavItem = (item, isCollapsedMode = false) => {
        if (item.visible === false) return null;
        const Icon = item.icon;

        return (
            <div key={item.label} className="relative group">
                <Link
                    href={route(item.route)}
                    onClick={() => setMobileOpen(false)}
                    className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-200 ${
                        isCollapsedMode ? 'justify-center px-2' : ''
                    } ${
                        item.active
                            ? 'bg-[#1A73E8] text-white shadow-sm ring-1 ring-white/20'
                            : 'text-blue-100/90 hover:bg-white/10 hover:text-white'
                    }`}
                    title={isCollapsedMode ? item.label : undefined}
                >
                    <Icon className="h-4 w-4 shrink-0" />
                    {!isCollapsedMode && <span className="truncate">{item.label}</span>}
                </Link>

                {/* Floating Tooltip in Collapsed Mode */}
                {isCollapsedMode && (
                    <div className="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 z-50 hidden group-hover:flex group-focus-within:flex items-center">
                        <div className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white shadow-xl whitespace-nowrap border border-slate-700">
                            {item.label}
                        </div>
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-[#F4F7FB] text-[#1F2937] flex antialiased overflow-x-clip">
            {/* Mobile Drawer Backdrop */}
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden transition-opacity"
                    onClick={() => setMobileOpen(false)}
                    aria-hidden="true"
                />
            )}

            {/* Mobile Off-canvas Drawer */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-72 bg-[#0A58CA] text-white flex flex-col transition-transform duration-300 ease-in-out lg:hidden shadow-2xl ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
                aria-label="Sidebar Mobile"
            >
                {/* Brand Header */}
                <div className="flex h-16 items-center justify-between border-b border-white/10 px-5">
                    <Link href={route('dashboard')} className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-white/15 flex items-center justify-center p-1.5 border border-white/20 shadow-xs">
                            <ApplicationLogo className="h-full w-full text-white" />
                        </div>
                        <div>
                            <span className="text-sm font-bold text-white tracking-tight block leading-tight">
                                {appName || 'Kirim Slip Gaji'}
                            </span>
                            <span className="text-[11px] text-blue-200 font-normal block">
                                Personalia & Payroll
                            </span>
                        </div>
                    </Link>
                    <button
                        type="button"
                        onClick={() => setMobileOpen(false)}
                        className="rounded-lg p-1.5 text-blue-200 hover:bg-white/10 hover:text-white"
                        aria-label="Tutup menu sidebar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Navigation Links */}
                <nav className="flex-1 min-h-0 overflow-y-auto px-3.5 py-4 space-y-6">
                    {navGroups.map((group) => {
                        if (group.visible === false) return null;
                        const visibleItems = group.items.filter((i) => i.visible !== false);
                        if (visibleItems.length === 0) return null;

                        return (
                            <div key={group.title} className="space-y-1.5">
                                <p className="px-3 text-[10px] font-bold uppercase tracking-wider text-blue-200/70">
                                    {group.title}
                                </p>
                                <div className="space-y-1">
                                    {visibleItems.map((item) => renderNavItem(item, false))}
                                </div>
                            </div>
                        );
                    })}
                </nav>

                {/* Footer User & Logout */}
                <div className="shrink-0 border-t border-white/10 p-3.5 bg-black/10">
                    <div className="flex items-center gap-3 px-2 py-1.5 mb-2">
                        <div className="h-9 w-9 rounded-xl bg-white/20 text-white font-bold flex items-center justify-center text-sm border border-white/20">
                            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-bold text-white truncate">{user?.name}</p>
                            <p className="text-[11px] text-blue-200 truncate">{primaryRoleLabel}</p>
                        </div>
                    </div>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/20 hover:text-white transition"
                    >
                        <LogOut className="h-4 w-4" />
                        <span>Keluar Aplikasi</span>
                    </Link>
                </div>
            </aside>

            {/* Desktop Sticky Collapsible Sidebar */}
            <aside
                className={`hidden lg:flex flex-col bg-[#0A58CA] text-white shrink-0 sticky top-0 h-screen z-30 transition-all duration-300 ease-in-out border-r border-blue-900/40 shadow-sm relative ${
                    collapsed ? 'w-20' : 'w-64'
                }`}
                aria-label="Sidebar Desktop"
            >
                {/* Brand Header */}
                <div className="shrink-0 flex h-16 items-center border-b border-white/10 px-4 justify-between relative">
                    <Link
                        href={route('dashboard')}
                        className={`flex items-center gap-3 overflow-hidden transition-all duration-200 ${
                            collapsed ? 'justify-center w-full' : ''
                        }`}
                        title={collapsed ? (appName || 'Kirim Slip Gaji') : undefined}
                    >
                        <div className="h-9 w-9 rounded-xl bg-white/15 flex items-center justify-center p-1.5 border border-white/20 shadow-xs shrink-0">
                            <ApplicationLogo className="h-full w-full text-white" />
                        </div>
                        {!collapsed && (
                            <div className="min-w-0 truncate">
                                <span className="text-sm font-bold text-white tracking-tight block leading-tight truncate">
                                    {appName || 'Kirim Slip Gaji'}
                                </span>
                                <span className="text-[11px] text-blue-200 font-normal block truncate">
                                    Personalia & Payroll
                                </span>
                            </div>
                        )}
                    </Link>

                    {/* Circular Sidebar Toggle Button on Vertical Divider */}
                    <button
                        type="button"
                        onClick={toggleCollapse}
                        className="absolute left-full top-1/2 -translate-x-1/2 -translate-y-1/2 z-40 flex h-6 w-6 items-center justify-center rounded-full bg-[#0A58CA] text-white shadow-md border-2 border-white hover:bg-[#084298] hover:scale-110 active:scale-95 transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#0A58CA] focus:ring-offset-2"
                        title={collapsed ? 'Perluas Sidebar' : 'Kecilkan Sidebar'}
                        aria-label={collapsed ? 'Perluas Sidebar' : 'Kecilkan Sidebar'}
                    >
                        {collapsed ? (
                            <ChevronRight className="h-3.5 w-3.5 text-white stroke-[2.5]" />
                        ) : (
                            <ChevronLeft className="h-3.5 w-3.5 text-white stroke-[2.5]" />
                        )}
                    </button>
                </div>

                {/* Navigation Links */}
                <nav className="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-5">
                    {navGroups.map((group) => {
                        if (group.visible === false) return null;
                        const visibleItems = group.items.filter((i) => i.visible !== false);
                        if (visibleItems.length === 0) return null;

                        return (
                            <div key={group.title} className="space-y-1.5">
                                {!collapsed && (
                                    <p className="px-3 text-[10px] font-bold uppercase tracking-wider text-blue-200/70">
                                        {group.title}
                                    </p>
                                )}
                                {collapsed && (
                                    <div className="mx-auto w-6 border-t border-white/15 my-2" />
                                )}
                                <div className="space-y-1">
                                    {visibleItems.map((item) => renderNavItem(item, collapsed))}
                                </div>
                            </div>
                        );
                    })}
                </nav>

                {/* Footer User Info & Logout */}
                <div className="shrink-0 border-t border-white/10 p-3 bg-black/10">
                    {!collapsed ? (
                        <div className="space-y-2">
                            <div className="flex items-center gap-3 px-2 py-1">
                                <div className="h-8 w-8 rounded-xl bg-white/20 text-white font-bold flex items-center justify-center text-xs border border-white/20 shrink-0">
                                    {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-bold text-white truncate">{user?.name}</p>
                                    <p className="text-[10px] text-blue-200 truncate">{primaryRoleLabel}</p>
                                </div>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-200 hover:bg-rose-500/20 hover:text-white transition"
                            >
                                <LogOut className="h-3.5 w-3.5" />
                                <span>Keluar Aplikasi</span>
                            </Link>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-2">
                            <div
                                className="h-9 w-9 rounded-xl bg-white/20 text-white font-bold flex items-center justify-center text-xs border border-white/20 cursor-pointer"
                                title={`${user?.name} (${primaryRoleLabel})`}
                            >
                                {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="h-9 w-9 flex items-center justify-center rounded-xl text-rose-200 hover:bg-rose-500/20 hover:text-white transition"
                                title="Keluar Aplikasi"
                                aria-label="Keluar Aplikasi"
                            >
                                <LogOut className="h-4 w-4" />
                            </Link>
                        </div>
                    )}
                </div>
            </aside>

            {/* Main Application Area */}
            <div className="flex flex-1 flex-col min-w-0 overflow-x-hidden">
                {/* Sticky Topbar */}
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 backdrop-blur-md px-4 sm:px-6 lg:px-8 shadow-xs">
                    <div className="flex items-center gap-3">
                        {/* Mobile Drawer Trigger (Mobile only) */}
                        <button
                            type="button"
                            onClick={() => setMobileOpen(true)}
                            className="rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:hidden focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                            aria-label="Buka menu navigasi"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                        {typeof header === 'string' || (!header && typeof title === 'string') ? (
                            <h1 className="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
                                {header || title || 'Portal Personalia'}
                            </h1>
                        ) : (
                            <div className="flex items-center min-w-0">
                                {header || title || (
                                    <h1 className="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
                                        Portal Personalia
                                    </h1>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Top Right User Profile Dropdown */}
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                                className="flex items-center gap-2 rounded-xl p-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#0B57D0]"
                                aria-expanded={userDropdownOpen}
                            >
                                <div className="h-8 w-8 rounded-full bg-blue-100 text-[#0B57D0] flex items-center justify-center font-bold text-xs border border-blue-200">
                                    {user?.name?.charAt(0)?.toUpperCase()}
                                </div>
                                <span className="hidden sm:inline-block max-w-[140px] truncate text-slate-800 font-semibold text-xs">
                                    {user?.name}
                                </span>
                                <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                            </button>

                            {/* Dropdown Menu */}
                            {userDropdownOpen && (
                                <div className="absolute right-0 mt-2 w-60 rounded-2xl border border-slate-200 bg-white py-2 shadow-xl ring-1 ring-black/5 z-50 animate-in fade-in zoom-in-95 duration-100">
                                    <div className="px-4 py-2.5 border-b border-slate-100">
                                        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                            Profil Terotentikasi
                                        </p>
                                        <p className="text-xs font-semibold text-slate-900 truncate mt-0.5">
                                            {user?.name}
                                        </p>
                                        <p className="text-[11px] text-slate-500 truncate">
                                            {user?.email}
                                        </p>
                                        <span
                                            className={`inline-block mt-2 text-[10px] font-bold px-2 py-0.5 rounded-full border ${getRoleBadgeColor(
                                                primaryRole
                                            )}`}
                                        >
                                            {primaryRoleLabel}
                                        </span>
                                    </div>
                                    <Link
                                        href={route('profile.edit')}
                                        onClick={() => setUserDropdownOpen(false)}
                                        className="flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 font-medium transition"
                                    >
                                        <UserCheck className="h-4 w-4 text-slate-400" />
                                        <span>Pengaturan Profil</span>
                                    </Link>
                                    <div className="border-t border-slate-100 my-1" />
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        onClick={() => setUserDropdownOpen(false)}
                                        className="flex w-full items-center gap-2.5 px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 font-semibold transition"
                                    >
                                        <LogOut className="h-4 w-4 text-rose-500" />
                                        <span>Keluar</span>
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page Main Content */}
                <main
                    className={`flex-1 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 w-full mx-auto ${
                        fullWidth ? 'max-w-full' : 'max-w-7xl'
                    }`}
                >
                    {/* Flash Messages */}
                    {flashVisible && flash?.success && (
                        <div className="mb-6 flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-xs sm:text-sm text-emerald-900 shadow-xs">
                            <div className="flex items-center gap-2.5">
                                <CheckCircle className="h-4 w-4 text-emerald-600 shrink-0" />
                                <span>{flash.success}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setFlashVisible(false)}
                                className="rounded-lg p-1 text-emerald-700 hover:bg-emerald-100 transition"
                                aria-label="Tutup notifikasi"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    )}

                    {flashVisible && flash?.error && (
                        <div className="mb-6 flex items-center justify-between rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-xs sm:text-sm text-rose-900 shadow-xs">
                            <div className="flex items-center gap-2.5">
                                <ShieldAlert className="h-4 w-4 text-rose-600 shrink-0" />
                                <span>{flash.error}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setFlashVisible(false)}
                                className="rounded-lg p-1 text-rose-700 hover:bg-rose-100 transition"
                                aria-label="Tutup notifikasi"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    )}

                    {children}
                </main>
            </div>
        </div>
    );
}
