<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use Illuminate\Database\Seeder;

class OrganizationMasterSeeder extends Seeder
{
    /**
     * Run the organization master seeds.
     */
    public function run(): void
    {
        // 1. Default Office
        $defaultOffice = Office::firstOrCreate(
            ['code' => 'BP'],
            [
                'name' => CompanySetting::get('company_name', 'CV. BINA PUSTAKA'),
                'address' => CompanySetting::get('company_address', 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.'),
                'phone' => '021-77889900',
                'email' => 'info@binapustaka.co.id',
                'is_active' => true,
            ]
        );

        // 2. Default Positions
        $positionsData = [
            ['name' => 'Direktur', 'code' => 'DIR', 'description' => 'Pimpinan Perusahaan'],
            ['name' => 'Manager', 'code' => 'MGR', 'description' => 'Manajer Divisi / Unit'],
            ['name' => 'Supervisor', 'code' => 'SPV', 'description' => 'Pengawas Operasional'],
            ['name' => 'Staff', 'code' => 'STF', 'description' => 'Staf Pelaksana'],
            ['name' => 'Administrasi', 'code' => 'ADM', 'description' => 'Staf Administrasi Umum'],
            ['name' => 'Finance Staff', 'code' => 'FIN-STF', 'description' => 'Staf Keuangan dan Akuntansi'],
            ['name' => 'Marketing Staff', 'code' => 'MKT-STF', 'description' => 'Staf Pemasaran dan Penjualan'],
            ['name' => 'Teknisi', 'code' => 'TKN', 'description' => 'Staf Pemeliharaan dan Teknis'],
            ['name' => 'Driver', 'code' => 'DRV', 'description' => 'Pengemudi Operasional'],
        ];

        $positionsMap = [];
        foreach ($positionsData as $pData) {
            $pos = Position::firstOrCreate(
                ['code' => $pData['code']],
                [
                    'name' => $pData['name'],
                    'description' => $pData['description'],
                    'is_active' => true,
                ]
            );
            $positionsMap[strtolower($pos->name)] = $pos;
        }

        // 3. Connect existing divisions without office to default office or seed standard divisions
        $divisionsData = [
            ['name' => 'Personalia', 'code' => 'BP-HRD', 'description' => 'Divisi Personalia & SDM'],
            ['name' => 'Finance', 'code' => 'BP-FIN', 'description' => 'Divisi Keuangan'],
            ['name' => 'Marketing', 'code' => 'BP-MKT', 'description' => 'Divisi Pemasaran'],
            ['name' => 'Gudang', 'code' => 'BP-GDG', 'description' => 'Divisi Pergudangan & Logistik'],
        ];

        foreach ($divisionsData as $divData) {
            Division::firstOrCreate(
                ['office_id' => $defaultOffice->id, 'name' => $divData['name']],
                [
                    'code' => $divData['code'],
                    'description' => $divData['description'],
                    'is_active' => true,
                ]
            );
        }

        Division::whereNull('office_id')->update(['office_id' => $defaultOffice->id]);

        // 4. Update existing employees without office_id or position_id
        $defaultStaff = $positionsMap['staff'] ?? Position::first();
        Employee::whereNull('office_id')->update(['office_id' => $defaultOffice->id]);

        $employeesWithoutPosition = Employee::whereNull('position_id')->get();
        foreach ($employeesWithoutPosition as $emp) {
            $matchedPos = null;
            if (! empty($emp->position)) {
                $posName = strtolower(trim((string) $emp->position));
                $matchedPos = $positionsMap[$posName] ?? null;
            }

            $emp->update([
                'position_id' => $matchedPos?->id ?? $defaultStaff?->id,
            ]);
        }
    }
}
