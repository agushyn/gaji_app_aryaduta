<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Roles
        $rolesData = [
            [
                'name' => 'super_admin',
                'label' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh konfigurasi sistem, user, role, audit log, dan operasional.',
            ],
            [
                'name' => 'personalia',
                'label' => 'Personalia',
                'description' => 'Mengelola karyawan, periode gaji, input slip, finalisasi, dan pengiriman.',
            ],
            [
                'name' => 'approver',
                'label' => 'Approver / Atasan',
                'description' => 'Meninjau dan menyetujui atau meminta revisi pengajuan slip gaji dan periode.',
            ],
            [
                'name' => 'auditor',
                'label' => 'Auditor',
                'description' => 'Hak akses baca untuk audit kepatuhan, histori persetujuan, dan log pengiriman.',
            ],
        ];

        $roles = [];
        foreach ($rolesData as $roleData) {
            $roles[$roleData['name']] = Role::updateOrCreate(
                ['name' => $roleData['name']],
                ['label' => $roleData['label'], 'description' => $roleData['description']]
            );
        }

        // 2. Permissions
        $permissionsData = [
            // Users
            ['name' => 'users.view', 'label' => 'Melihat Pengguna', 'group' => 'users'],
            ['name' => 'users.create', 'label' => 'Membuat Pengguna', 'group' => 'users'],
            ['name' => 'users.edit', 'label' => 'Mengedit Pengguna', 'group' => 'users'],
            ['name' => 'users.toggle-status', 'label' => 'Mengaktifkan / Menonaktifkan Pengguna', 'group' => 'users'],
            ['name' => 'users.delete', 'label' => 'Menghapus Pengguna', 'group' => 'users'],

            // Roles
            ['name' => 'roles.view', 'label' => 'Melihat Role & Permission', 'group' => 'roles'],
            ['name' => 'roles.manage', 'label' => 'Mengelola Role & Permission', 'group' => 'roles'],

            // Offices
            ['name' => 'offices.view', 'label' => 'Melihat Kantor', 'group' => 'offices'],
            ['name' => 'offices.manage', 'label' => 'Mengelola Kantor', 'group' => 'offices'],

            // Departments
            ['name' => 'departments.view', 'label' => 'Melihat Departemen', 'group' => 'departments'],
            ['name' => 'departments.manage', 'label' => 'Mengelola Departemen', 'group' => 'departments'],

            // Divisions
            ['name' => 'divisions.view', 'label' => 'Melihat Divisi', 'group' => 'divisions'],
            ['name' => 'divisions.manage', 'label' => 'Mengelola Divisi', 'group' => 'divisions'],

            // Positions
            ['name' => 'positions.view', 'label' => 'Melihat Jabatan', 'group' => 'positions'],
            ['name' => 'positions.manage', 'label' => 'Mengelola Jabatan', 'group' => 'positions'],

            // Employees
            ['name' => 'employees.view', 'label' => 'Melihat Karyawan', 'group' => 'employees'],
            ['name' => 'employees.manage', 'label' => 'Mengelola Karyawan', 'group' => 'employees'],

            // Payroll Periods
            ['name' => 'payroll-periods.view', 'label' => 'Melihat Periode Gaji', 'group' => 'payroll-periods'],
            ['name' => 'payroll-periods.manage', 'label' => 'Mengelola Periode Gaji', 'group' => 'payroll-periods'],

            // Pay Components
            ['name' => 'pay-components.view', 'label' => 'Melihat Master Komponen Gaji', 'group' => 'pay-components'],
            ['name' => 'pay-components.manage', 'label' => 'Mengelola Master Komponen Gaji', 'group' => 'pay-components'],

            // Payslips
            ['name' => 'payslips.view', 'label' => 'Melihat Slip Gaji', 'group' => 'payslips'],
            ['name' => 'payslips.manage', 'label' => 'Menginput & Mengedit Slip Gaji', 'group' => 'payslips'],
            ['name' => 'payslips.financial-view', 'label' => 'Melihat Rincian Nominal Finansial', 'group' => 'payslips'],
            ['name' => 'payslips.finalize', 'label' => 'Memfinalisasi Slip Gaji', 'group' => 'payslips'],

            // Approvals
            ['name' => 'approvals.view', 'label' => 'Melihat Daftar Approval', 'group' => 'approvals'],
            ['name' => 'approvals.decide', 'label' => 'Menyetujui atau Menolak Pengajuan', 'group' => 'approvals'],

            // Deliveries
            ['name' => 'deliveries.view', 'label' => 'Melihat Status Pengiriman', 'group' => 'deliveries'],
            ['name' => 'deliveries.send', 'label' => 'Mengirim Slip Gaji (Email/WA)', 'group' => 'deliveries'],
            ['name' => 'deliveries.retry', 'label' => 'Retry Pengiriman Gagal', 'group' => 'deliveries'],

            // Reports
            ['name' => 'reports.view', 'label' => 'Melihat Laporan Pengiriman & Status', 'group' => 'reports'],
            ['name' => 'reports.export', 'label' => 'Mengekspor Laporan Pengiriman', 'group' => 'reports'],

            // Audit Logs
            ['name' => 'audit-logs.view', 'label' => 'Melihat Audit Log', 'group' => 'audit-logs'],

            // Settings
            ['name' => 'settings.view', 'label' => 'Melihat Pengaturan Sistem', 'group' => 'settings'],
            ['name' => 'settings.manage', 'label' => 'Mengelola Pengaturan Sistem', 'group' => 'settings'],

            // Payroll Calculation Configuration & Holiday Calendar
            ['name' => 'payroll.configuration.view', 'label' => 'Melihat Konfigurasi Perhitungan Payroll', 'group' => 'payroll-configuration'],
            ['name' => 'payroll.configuration.create', 'label' => 'Membuat Konfigurasi Perhitungan Payroll', 'group' => 'payroll-configuration'],
            ['name' => 'payroll.configuration.update', 'label' => 'Mengubah Konfigurasi Perhitungan Payroll', 'group' => 'payroll-configuration'],
            ['name' => 'payroll.configuration.delete', 'label' => 'Menghapus Konfigurasi Perhitungan Payroll', 'group' => 'payroll-configuration'],
            ['name' => 'payroll.holiday.view', 'label' => 'Melihat Kalender Libur Payroll', 'group' => 'payroll-holiday'],
            ['name' => 'payroll.holiday.sync', 'label' => 'Sinkronisasi Kalender Libur Payroll', 'group' => 'payroll-holiday'],
            ['name' => 'payroll.holiday.update', 'label' => 'Mengubah Kalender Libur Payroll', 'group' => 'payroll-holiday'],

            // Security Payroll Module
            ['name' => 'payroll.security.view', 'label' => 'Melihat Payroll & Rekap Security', 'group' => 'payroll-security'],
            ['name' => 'payroll.security.manage', 'label' => 'Mengelola Payroll & Rekap Kehadiran Security', 'group' => 'payroll-security'],
            ['name' => 'payroll.security.config', 'label' => 'Mengubah Konfigurasi Rumus Lembur Security', 'group' => 'payroll-security'],
        ];

        $allPermissionIds = [];
        $permissionModelMap = [];
        foreach ($permissionsData as $pData) {
            $perm = Permission::updateOrCreate(
                ['name' => $pData['name']],
                ['label' => $pData['label'], 'group' => $pData['group']]
            );
            $allPermissionIds[] = $perm->id;
            $permissionModelMap[$pData['name']] = $perm->id;
        }

        // 3. Assign Permissions to Roles
        // Super Admin gets all permissions
        $roles['super_admin']->permissions()->sync($allPermissionIds);

        // Personalia
        $personaliaPermNames = [
            'offices.view', 'offices.manage',
            'departments.view', 'departments.manage',
            'divisions.view', 'divisions.manage',
            'positions.view', 'positions.manage',
            'employees.view', 'employees.manage',
            'payroll-periods.view', 'payroll-periods.manage',
            'pay-components.view', 'pay-components.manage',
            'payslips.view', 'payslips.manage', 'payslips.financial-view', 'payslips.finalize',
            'approvals.view',
            'deliveries.view', 'deliveries.send', 'deliveries.retry',
            'reports.view', 'reports.export',
            'payroll.configuration.view', 'payroll.configuration.create', 'payroll.configuration.update',
            'payroll.holiday.view', 'payroll.holiday.sync', 'payroll.holiday.update',
            'payroll.security.view', 'payroll.security.manage', 'payroll.security.config',
        ];
        $roles['personalia']->permissions()->sync(
            array_intersect_key($permissionModelMap, array_flip($personaliaPermNames))
        );

        // Approver
        $approverPermNames = [
            'payroll-periods.view',
            'payslips.view', 'payslips.financial-view',
            'approvals.view', 'approvals.decide',
            'reports.view',
            'payroll.configuration.view',
            'payroll.holiday.view',
            'payroll.security.view',
        ];
        $roles['approver']->permissions()->sync(
            array_intersect_key($permissionModelMap, array_flip($approverPermNames))
        );

        // Auditor
        $auditorPermNames = [
            'roles.view',
            'offices.view',
            'departments.view',
            'divisions.view',
            'positions.view',
            'employees.view',
            'payroll-periods.view',
            'pay-components.view',
            'payslips.view',
            'approvals.view',
            'deliveries.view',
            'reports.view', 'reports.export',
            'audit-logs.view',
            'payroll.configuration.view',
            'payroll.holiday.view',
            'payroll.security.view',
        ];
        $roles['auditor']->permissions()->sync(
            array_intersect_key($permissionModelMap, array_flip($auditorPermNames))
        );

        // 4. Seed Internal Staff Users (No employee portal users)
        $defaultUsers = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'role' => 'super_admin',
            ],
            [
                'name' => 'Staf Personalia',
                'email' => 'personalia@example.com',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'role' => 'personalia',
            ],
            [
                'name' => 'Manajer HR / Approver',
                'email' => 'approver@example.com',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'role' => 'approver',
            ],
            [
                'name' => 'Internal Auditor',
                'email' => 'auditor@example.com',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'role' => 'auditor',
            ],
            [
                'name' => 'Staf Nonaktif (Uji)',
                'email' => 'inactive@example.com',
                'password' => Hash::make('Password123!'),
                'is_active' => false,
                'role' => 'personalia',
            ],
        ];

        foreach ($defaultUsers as $uData) {
            $roleName = $uData['role'];
            unset($uData['role']);

            $user = User::updateOrCreate(
                ['email' => $uData['email']],
                $uData
            );

            if (isset($roles[$roleName])) {
                $user->roles()->syncWithoutDetaching([$roles[$roleName]->id]);
            }
        }
    }
}
