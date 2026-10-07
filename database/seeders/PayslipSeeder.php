<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\PayslipService;
use Illuminate\Database\Seeder;

class PayslipSeeder extends Seeder
{
    /**
     * Seed initial demo payslips for testing and UI preview.
     */
    public function run(): void
    {
        $period = PayrollPeriod::where('period_code', '2026-10')->first();
        if (! $period) {
            return;
        }

        $payslipService = app(PayslipService::class);

        $employees = Employee::where('employment_status', 'active')
            ->take(4)
            ->get();

        $gapok = PayComponent::where('code', 'BASIC_SALARY')->first();
        $tunjanganJabatan = PayComponent::where('code', 'POSITION_ALLOWANCE')->first();
        $tunjanganMakan = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $bpjsTk = PayComponent::where('code', 'BPJS_TK')->first();
        $bpjsKes = PayComponent::where('code', 'BPJS_KES')->first();
        $pph21 = PayComponent::where('code', 'PPH21')->first();

        $salaries = [
            0 => ['basic' => 8500000, 'jabatan' => 2000000, 'makan' => 1200000, 'bpjs_tk' => 170000, 'bpjs_kes' => 85000, 'pph' => 320000],
            1 => ['basic' => 6000000, 'jabatan' => 1000000, 'makan' => 1000000, 'bpjs_tk' => 120000, 'bpjs_kes' => 60000, 'pph' => 180000],
            2 => ['basic' => 9500000, 'jabatan' => 2500000, 'makan' => 1500000, 'bpjs_tk' => 190000, 'bpjs_kes' => 95000, 'pph' => 450000],
            3 => ['basic' => 7000000, 'jabatan' => 1200000, 'makan' => 1100000, 'bpjs_tk' => 140000, 'bpjs_kes' => 70000, 'pph' => 240000],
        ];

        foreach ($employees as $index => $emp) {
            if (Payslip::where('payroll_period_id', $period->id)->where('employee_id', $emp->id)->exists()) {
                continue;
            }

            $conf = $salaries[$index] ?? $salaries[0];

            $items = [
                [
                    'pay_component_id' => $gapok?->id,
                    'item_code_snapshot' => 'BASIC_SALARY',
                    'description_snapshot' => 'Gaji Pokok',
                    'item_type' => 'earning',
                    'amount' => $conf['basic'],
                ],
                [
                    'pay_component_id' => $tunjanganJabatan?->id,
                    'item_code_snapshot' => 'POSITION_ALLOWANCE',
                    'description_snapshot' => 'Tunjangan Jabatan',
                    'item_type' => 'earning',
                    'amount' => $conf['jabatan'],
                ],
                [
                    'pay_component_id' => $tunjanganMakan?->id,
                    'item_code_snapshot' => 'MEAL_ALLOWANCE',
                    'description_snapshot' => 'Tunjangan Makan & Transport',
                    'item_type' => 'earning',
                    'amount' => $conf['makan'],
                ],
                [
                    'pay_component_id' => $bpjsTk?->id,
                    'item_code_snapshot' => 'BPJS_TK',
                    'description_snapshot' => 'BPJS Ketenagakerjaan (2%)',
                    'item_type' => 'deduction',
                    'amount' => $conf['bpjs_tk'],
                ],
                [
                    'pay_component_id' => $bpjsKes?->id,
                    'item_code_snapshot' => 'BPJS_KES',
                    'description_snapshot' => 'BPJS Kesehatan (1%)',
                    'item_type' => 'deduction',
                    'amount' => $conf['bpjs_kes'],
                ],
                [
                    'pay_component_id' => $pph21?->id,
                    'item_code_snapshot' => 'PPH21',
                    'description_snapshot' => 'Potongan PPh 21',
                    'item_type' => 'deduction',
                    'amount' => $conf['pph'],
                ],
            ];

            $payslipService->createPayslip($period, [
                'employee_id' => $emp->id,
                'items' => $items,
            ]);
        }
    }
}
