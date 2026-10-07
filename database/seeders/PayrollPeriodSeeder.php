<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayrollPeriodSeeder extends Seeder
{
    /**
     * Seed initial payroll period.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (! $admin) {
            return;
        }

        PayrollPeriod::firstOrCreate(
            ['period_code' => '2026-10'],
            [
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'status' => 'draft',
                'created_by' => $admin->id,
                'approval_comment' => 'Periode penggajian reguler bulan Oktober 2026.',
            ]
        );
    }
}
