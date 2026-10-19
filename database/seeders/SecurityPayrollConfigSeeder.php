<?php

namespace Database\Seeders;

use App\Models\SecurityPayrollConfig;
use Illuminate\Database\Seeder;

class SecurityPayrollConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SecurityPayrollConfig::firstOrCreate(
            ['code' => 'SECURITY_DEFAULT'],
            [
                'name' => 'Konfigurasi Lembur Security Default',
                'hourly_rate' => 14000.00,
                'first_hours_limit' => 5.00,
                'first_hours_multiplier' => 1.50,
                'subsequent_hours_multiplier' => 2.00,
                'break_deduction_rule' => 'fixed',
                'break_deduction_default' => 0.00,
                'max_break_deduction' => 20.00,
                'weekday_multiplier' => 1.00,
                'holiday_multiplier' => 1.00,
                'round_rule' => 'round',
                'version' => 1,
                'is_active' => true,
            ]
        );
    }
}
