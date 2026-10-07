<?php

namespace Database\Seeders;

use App\Models\PayComponent;
use App\Models\PayrollCalculationConfig;
use App\Models\PayrollCalculationConfigVersion;
use App\Models\User;
use App\Services\OvertimeCalculationService;
use Illuminate\Database\Seeder;

class PayrollCalculationConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $overtimeComponent = PayComponent::where('code', 'OVERTIME')->first();
        if (! $overtimeComponent) {
            $overtimeComponent = PayComponent::create([
                'code' => 'OVERTIME',
                'name' => 'Uang Lembur',
                'type' => 'earning',
                'sort_order' => 40,
                'is_active' => true,
            ]);
        }

        $adminUser = User::first();
        $adminId = $adminUser ? $adminUser->id : null;

        $overtimeService = app(OvertimeCalculationService::class);

        // 1. Lembur Hari Biasa
        $normalConfig = PayrollCalculationConfig::updateOrCreate(
            [
                'code' => 'OVERTIME_WORKING_DAY',
            ],
            [
                'name' => 'Lembur Hari Biasa',
                'component_id' => $overtimeComponent->id,
                'day_type' => 'working_day',
                'is_active' => true,
                'current_version' => 1,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]
        );

        PayrollCalculationConfigVersion::updateOrCreate(
            [
                'config_id' => $normalConfig->id,
                'version' => 1,
            ],
            [
                'formula' => $overtimeService->getDefaultWorkingDayFormula(),
                'description' => 'Versi 1: Formula standar hari kerja (1.5x jam pertama, 2x jam berikutnya)',
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );

        // 2. Lembur Hari Libur
        $holidayConfig = PayrollCalculationConfig::updateOrCreate(
            [
                'code' => 'OVERTIME_HOLIDAY',
            ],
            [
                'name' => 'Lembur Hari Libur',
                'component_id' => $overtimeComponent->id,
                'day_type' => 'holiday',
                'is_active' => true,
                'current_version' => 1,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]
        );

        PayrollCalculationConfigVersion::updateOrCreate(
            [
                'config_id' => $holidayConfig->id,
                'version' => 1,
            ],
            [
                'formula' => $overtimeService->getDefaultHolidayFormula(),
                'description' => 'Versi 1: Formula standar hari libur (2x upah lembur per jam)',
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );
    }
}
