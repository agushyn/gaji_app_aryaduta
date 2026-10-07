<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DepartmentAndDivisionSeeder extends Seeder
{
    /**
     * Run the database seeds for default Departments and Divisions.
     */
    public function run(): void
    {
        $structure = [
            [
                'name' => 'Human Resources',
                'code' => 'HRD',
                'description' => 'Pengelolaan SDM, rekrutmen, kompensasi, dan kepatuhan ketenagakerjaan.',
                'divisions' => [
                    ['name' => 'People Operations', 'code' => 'HRD-OPS', 'description' => 'Operasional harian dan administrasi HR.'],
                    ['name' => 'Talent Acquisition', 'code' => 'HRD-TA', 'description' => 'Perekrutan dan seleksi talenta baru.'],
                    ['name' => 'Compensation & Benefits', 'code' => 'HRD-CB', 'description' => 'Penggajian, asuransi, dan fasilitas karyawan.'],
                ],
            ],
            [
                'name' => 'Finance & Accounting',
                'code' => 'FIN',
                'description' => 'Pengelolaan keuangan, akuntansi, anggaran, dan perpajakan perusahaan.',
                'divisions' => [
                    ['name' => 'Accounting', 'code' => 'FIN-ACC', 'description' => 'Pencatatan akuntansi dan pembukuan jurnal.'],
                    ['name' => 'Treasury', 'code' => 'FIN-TRS', 'description' => 'Manajemen kas, likuiditas, dan rekonsiliasi perbankan.'],
                    ['name' => 'Tax & Audit', 'code' => 'FIN-TAX', 'description' => 'Kepatuhan perpajakan dan pelaporan audit.'],
                ],
            ],
            [
                'name' => 'Marketing',
                'code' => 'MKT',
                'description' => 'Pemasaran produk, ekspansi pasar, komunikasi brand, dan penjualan.',
                'divisions' => [
                    ['name' => 'Digital Marketing', 'code' => 'MKT-DIG', 'description' => 'Pemasaran daring, SEO, dan media sosial.'],
                    ['name' => 'Sales Marketing', 'code' => 'MKT-SLS', 'description' => 'Penjualan langsung dan hubungan klien.'],
                    ['name' => 'Marketing Communication', 'code' => 'MKT-COM', 'description' => 'Hubungan masyarakat, periklanan, dan branding.'],
                ],
            ],
            [
                'name' => 'General Affairs',
                'code' => 'GA',
                'description' => 'Pengelolaan fasilitas kantor, logistik, pengadaan, dan perizinan umum.',
                'divisions' => [
                    ['name' => 'Facility Management', 'code' => 'GA-FAC', 'description' => 'Pemeliharaan gedung dan utilitas operasional.'],
                    ['name' => 'Office Administration', 'code' => 'GA-ADM', 'description' => 'Administrasi perlengkapan kantor dan surat-menyurat.'],
                    ['name' => 'Procurement', 'code' => 'GA-PRO', 'description' => 'Pengadaan barang, logistik, dan inventaris.'],
                ],
            ],
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'Pengembangan perangkat lunak, infrastruktur server, dan dukungan teknis.',
                'divisions' => [
                    ['name' => 'Software Engineering', 'code' => 'IT-ENG', 'description' => 'Pengembangan dan pemeliharaan aplikasi.'],
                    ['name' => 'Infrastructure & Security', 'code' => 'IT-INF', 'description' => 'Manajemen server, jaringan, dan keamanan siber.'],
                    ['name' => 'Technical Support', 'code' => 'IT-SUP', 'description' => 'Layanan bantuan teknis dan workstation pengguna.'],
                ],
            ],
        ];

        foreach ($structure as $deptData) {
            $divisions = $deptData['divisions'];
            unset($deptData['divisions']);

            $department = Department::updateOrCreate(
                ['code' => $deptData['code']],
                [
                    'name' => $deptData['name'],
                    'description' => $deptData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($divisions as $divData) {
                Division::updateOrCreate(
                    ['code' => $divData['code']],
                    [
                        'department_id' => $department->id,
                        'name' => $divData['name'],
                        'description' => $divData['description'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // Backfill existing employees where department_id is null
        $departments = Department::with('divisions')->get();
        $defaultDept = $departments->firstWhere('code', 'HRD') ?? $departments->first();
        $defaultDiv = $defaultDept?->divisions->first();

        $employees = Employee::whereNull('department_id')->get();
        foreach ($employees as $employee) {
            $matchedDept = null;
            if (! empty($employee->department)) {
                $matchedDept = $departments->first(function ($d) use ($employee) {
                    return stripos($d->name, $employee->department) !== false
                        || stripos($employee->department, $d->name) !== false
                        || strcasecmp($d->code, $employee->department) === 0;
                });
            }

            $targetDept = $matchedDept ?? $defaultDept;
            $targetDiv = $targetDept?->divisions->first() ?? $defaultDiv;

            if ($targetDept && $targetDiv) {
                $employee->update([
                    'department_id' => $targetDept->id,
                    'division_id' => $targetDiv->id,
                    'department' => $targetDept->name,
                ]);
            }
        }
    }
}
