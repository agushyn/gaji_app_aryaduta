<?php

namespace Database\Seeders;

use App\Models\PayComponent;
use Illuminate\Database\Seeder;

class PayComponentSeeder extends Seeder
{
    /**
     * Seed initial standard pay components.
     */
    public function run(): void
    {
        $components = [
            // Pendapatan (Earnings)
            [
                'code' => 'BASIC_SALARY',
                'name' => 'Gaji Pokok',
                'type' => 'earning',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'MEAL_ALLOWANCE',
                'name' => 'Uang Makan',
                'type' => 'earning',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'TRANSPORT_ALLOWANCE',
                'name' => 'Uang Transport',
                'type' => 'earning',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'OVERTIME',
                'name' => 'Uang Lembur',
                'type' => 'earning',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => 'FUNCTIONAL_ALLOWANCE',
                'name' => 'Tunjangan Fungsional',
                'type' => 'earning',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'POSITION_ALLOWANCE',
                'name' => 'Tunjangan Jabatan',
                'type' => 'earning',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'code' => 'FAMILY_ALLOWANCE',
                'name' => 'Tunjangan Keluarga',
                'type' => 'earning',
                'sort_order' => 70,
                'is_active' => true,
            ],
            [
                'code' => 'ATTENDANCE_INCENTIVE',
                'name' => 'Kehadiran',
                'type' => 'earning',
                'sort_order' => 80,
                'is_active' => true,
            ],
            [
                'code' => 'COMMUNICATION_ALLOWANCE',
                'name' => 'Tunjangan Pulsa',
                'type' => 'earning',
                'sort_order' => 90,
                'is_active' => true,
            ],
            [
                'code' => 'HEALTH_ALLOWANCE',
                'name' => 'Biaya Kesehatan',
                'type' => 'earning',
                'sort_order' => 100,
                'is_active' => true,
            ],
            [
                'code' => 'OFFICIAL_DUTY',
                'name' => 'Uang Dinas (PJD Luar Kota)',
                'type' => 'earning',
                'sort_order' => 110,
                'is_active' => true,
            ],

            // Potongan (Deductions)
            [
                'code' => 'BPJS_KES',
                'name' => 'BPJS Kesehatan',
                'type' => 'deduction',
                'sort_order' => 200,
                'is_active' => true,
            ],
            [
                'code' => 'ATTENDANCE_CUT_SIC',
                'name' => 'Potongan Kehadiran (S/I/C)',
                'type' => 'deduction',
                'sort_order' => 210,
                'is_active' => true,
            ],
            [
                'code' => 'ALPHA_CUT',
                'name' => 'Potongan Alpa',
                'type' => 'deduction',
                'sort_order' => 220,
                'is_active' => true,
            ],
            [
                'code' => 'BPJS_TK',
                'name' => 'Iuran BPJS Ketenagakerjaan',
                'type' => 'deduction',
                'sort_order' => 230,
                'is_active' => true,
            ],
            [
                'code' => 'KOPERASI_MANDATORY',
                'name' => 'Iuran Koperasi',
                'type' => 'deduction',
                'sort_order' => 240,
                'is_active' => true,
            ],
            [
                'code' => 'KOPERASI_LOAN',
                'name' => 'Pinjaman Koperasi',
                'type' => 'deduction',
                'sort_order' => 250,
                'is_active' => true,
            ],
            [
                'code' => 'LOCAL_LOAN',
                'name' => 'Pinjaman Lokal',
                'type' => 'deduction',
                'sort_order' => 260,
                'is_active' => true,
            ],
            [
                'code' => 'VEHICLE_INSTALLMENT',
                'name' => 'Angsuran Kendaraan',
                'type' => 'deduction',
                'sort_order' => 270,
                'is_active' => true,
            ],
            [
                'code' => 'OFF_DAYS_CUT',
                'name' => 'Potongan Jumlah Off',
                'type' => 'deduction',
                'sort_order' => 280,
                'is_active' => true,
            ],
        ];

        foreach ($components as $item) {
            PayComponent::firstOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
