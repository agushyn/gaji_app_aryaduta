<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Seed initial employee master records.
     */
    public function run(): void
    {
        $employees = [
            [
                'employee_number' => 'EMP-2024-001',
                'full_name' => 'Budi Santoso',
                'department' => 'Keuangan',
                'position' => 'Senior Finance Officer',
                'email' => 'budi.santoso@company.co.id',
                'phone' => '6281234567801',
                'hire_date' => '2024-01-10',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-002',
                'full_name' => 'Siti Aminah',
                'department' => 'SDM & Umum',
                'position' => 'HR Generalist',
                'email' => 'siti.aminah@company.co.id',
                'phone' => '6281234567802',
                'hire_date' => '2024-02-01',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-003',
                'full_name' => 'Ahmad Fauzi',
                'department' => 'Teknologi Informasi',
                'position' => 'Fullstack Developer',
                'email' => 'ahmad.fauzi@company.co.id',
                'phone' => '6281234567803',
                'hire_date' => '2024-03-15',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-004',
                'full_name' => 'Dewi Lestari',
                'department' => 'Pemasaran',
                'position' => 'Digital Marketing Lead',
                'email' => 'dewi.lestari@company.co.id',
                'phone' => '6281234567804',
                'hire_date' => '2024-04-20',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-005',
                'full_name' => 'Rendra Pratama',
                'department' => 'Operasional',
                'position' => 'Operations Supervisor',
                'email' => 'rendra.pratama@company.co.id',
                'phone' => '6281234567805',
                'hire_date' => '2024-05-12',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-006',
                'full_name' => 'Nurul Hidayah',
                'department' => 'Keuangan',
                'position' => 'Payroll & Tax Specialist',
                'email' => 'nurul.hidayah@company.co.id',
                'phone' => '6281234567806',
                'hire_date' => '2024-06-01',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-007',
                'full_name' => 'Hendro Prasetyo',
                'department' => 'Teknologi Informasi',
                'position' => 'DevOps Engineer',
                'email' => 'hendro.prasetyo@company.co.id',
                'phone' => '6281234567807',
                'hire_date' => '2024-07-15',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-008',
                'full_name' => 'Mega Utami',
                'department' => 'SDM & Umum',
                'position' => 'Recruitment Specialist',
                'email' => 'mega.utami@company.co.id',
                'phone' => '6281234567808',
                'hire_date' => '2024-08-01',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-009',
                'full_name' => 'Bambang Kusuma',
                'department' => 'Operasional',
                'position' => 'Warehouse Coordinator',
                'email' => 'bambang.kusuma@company.co.id',
                'phone' => '6281234567809',
                'hire_date' => '2024-09-01',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2024-010',
                'full_name' => 'Tri Wahyuni',
                'department' => 'Pemasaran',
                'position' => 'Content Creator',
                'email' => 'tri.wahyuni@company.co.id',
                'phone' => '6281234567810',
                'hire_date' => '2024-10-10',
                'employment_status' => 'active',
            ],
            [
                'employee_number' => 'EMP-2023-011',
                'full_name' => 'Agus Priyono',
                'department' => 'Operasional',
                'position' => 'Staff Lapangan',
                'email' => 'agus.priyono@company.co.id',
                'phone' => '6281234567811',
                'hire_date' => '2023-05-10',
                'employment_status' => 'inactive',
            ],
            [
                'employee_number' => 'EMP-2023-012',
                'full_name' => 'Dian Sastrodimedjo',
                'department' => 'Keuangan',
                'position' => 'Junior Auditor',
                'email' => 'dian.sastro@company.co.id',
                'phone' => '6281234567812',
                'hire_date' => '2023-08-15',
                'employment_status' => 'inactive',
            ],
        ];

        foreach ($employees as $data) {
            Employee::firstOrCreate(
                ['employee_number' => $data['employee_number']],
                $data
            );
        }
    }
}
