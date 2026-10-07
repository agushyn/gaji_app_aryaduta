<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Office;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PayrollExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $regularUser;

    protected PayrollPeriod $period;

    protected Employee $employee1;

    protected Employee $employee2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();

        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $office = Office::create([
            'code' => 'PST',
            'name' => 'CV. ARYA DUTA Pusat',
            'address' => 'Jl. Merdeka No. 45, Jakarta Pusat',
            'is_active' => true,
        ]);

        $this->employee1 = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Agus Heryana',
            'office_id' => $office->id,
            'department' => 'IT',
            'position' => 'Senior Developer',
            'email' => 'agus@example.com',
            'phone' => '6281234567890',
            'hire_date' => '2022-03-15',
            'employment_status' => 'active',
            'gaji_pokok' => 8000000,
            'bpjs_ketenagakerjaan_enabled' => true,
        ]);

        $this->employee2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Sheren Regiana',
            'office_id' => $office->id,
            'department' => 'Keuangan',
            'position' => 'Staff Keuangan',
            'email' => 'sheren@example.com',
            'phone' => '6281298765432',
            'hire_date' => '2023-07-01',
            'employment_status' => 'active',
            'gaji_pokok' => 6000000,
            'bpjs_ketenagakerjaan_enabled' => false,
        ]);

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-09',
            'start_date' => '2026-08-23',
            'end_date' => '2026-09-22',
            'working_days' => 25,
            'period_month_label' => 'September 2026',
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $gapokComp = PayComponent::create(['code' => 'BASIC_SALARY', 'name' => 'Gaji Pokok', 'type' => 'earning']);
        $jabatanComp = PayComponent::create(['code' => 'POSITION_ALLOWANCE', 'name' => 'Tunjangan Jabatan', 'type' => 'earning']);
        $makanComp = PayComponent::create(['code' => 'MEAL_ALLOWANCE', 'name' => 'Uang Makan', 'type' => 'earning']);
        $lemburComp = PayComponent::create(['code' => 'OVERTIME', 'name' => 'Uang Lembur', 'type' => 'earning']);
        $bpjsKesComp = PayComponent::create(['code' => 'BPJS_KES', 'name' => 'BPJS Kesehatan', 'type' => 'deduction']);
        $bpjsTkComp = PayComponent::create(['code' => 'BPJS_TK', 'name' => 'BPJS Ketenagakerjaan', 'type' => 'deduction']);
        $potAbsenComp = PayComponent::create(['code' => 'ATT_SIC', 'name' => 'Potongan Kehadiran (S,I,C)', 'type' => 'deduction']);

        $payslipService = app(PayslipService::class);

        // Payslip 1
        $payslipService->createPayslip($this->period, [
            'employee_id' => $this->employee1->id,
            'items' => [
                ['pay_component_id' => $gapokComp->id, 'item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '8000000.00'],
                ['pay_component_id' => $jabatanComp->id, 'item_code_snapshot' => 'POSITION_ALLOWANCE', 'description_snapshot' => 'Tunjangan Jabatan', 'item_type' => 'earning', 'amount' => '1500000.00'],
                ['pay_component_id' => $makanComp->id, 'item_code_snapshot' => 'MEAL_ALLOWANCE', 'description_snapshot' => 'Uang Makan', 'item_type' => 'earning', 'amount' => '750000.00'],
                ['pay_component_id' => $lemburComp->id, 'item_code_snapshot' => 'OVERTIME', 'description_snapshot' => 'Uang Lembur', 'item_type' => 'earning', 'amount' => '500000.00'],
                ['pay_component_id' => $bpjsKesComp->id, 'item_code_snapshot' => 'BPJS_KES', 'description_snapshot' => 'BPJS Kesehatan', 'item_type' => 'deduction', 'amount' => '80000.00'],
                ['pay_component_id' => $bpjsTkComp->id, 'item_code_snapshot' => 'BPJS_TK', 'description_snapshot' => 'BPJS Ketenagakerjaan', 'item_type' => 'deduction', 'amount' => '160000.00'],
                ['pay_component_id' => $potAbsenComp->id, 'item_code_snapshot' => 'ATT_SIC', 'description_snapshot' => 'Potongan Absen', 'item_type' => 'deduction', 'amount' => '100000.00', 'quantity' => '1'],
            ],
        ]);

        // Payslip 2
        $payslipService->createPayslip($this->period, [
            'employee_id' => $this->employee2->id,
            'items' => [
                ['pay_component_id' => $gapokComp->id, 'item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '6000000.00'],
                ['pay_component_id' => $makanComp->id, 'item_code_snapshot' => 'MEAL_ALLOWANCE', 'description_snapshot' => 'Uang Makan', 'item_type' => 'earning', 'amount' => '600000.00'],
                ['pay_component_id' => $bpjsKesComp->id, 'item_code_snapshot' => 'BPJS_KES', 'description_snapshot' => 'BPJS Kesehatan', 'item_type' => 'deduction', 'amount' => '60000.00'],
            ],
        ]);

        $this->period->update(['status' => 'approved']);
    }

    public function test_unauthenticated_user_cannot_export_excel(): void
    {
        $response = $this->get(route('approvals.periods.export-excel', $this->period->id));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_export_excel(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('approvals.periods.export-excel', $this->period->id));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_export_excel_stream(): void
    {
        $response = $this->actingAs($this->personalia)
            ->get(route('approvals.periods.export-excel', $this->period->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Payroll_Staff_Pusat_September_2026.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_exported_excel_has_exactly_two_sheets_and_correct_structure(): void
    {
        $response = $this->actingAs($this->approver)
            ->get(route('approvals.periods.export-excel', $this->period->id));

        $response->assertOk();

        // Capture streamed binary output
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertNotEmpty($content);

        // Write to temporary file to read via PhpSpreadsheet
        $tempPath = tempnam(sys_get_temp_dir(), 'payroll_xlsx_');
        file_put_contents($tempPath, $content);

        try {
            $spreadsheet = IOFactory::load($tempPath);

            // 1. Verify exactly 2 sheets
            $this->assertEquals(2, $spreadsheet->getSheetCount(), 'Workbook must contain exactly 2 sheets');

            $sheetNames = $spreadsheet->getSheetNames();
            $this->assertEquals(['Summary', 'Staff Pusat'], $sheetNames);

            // Verify sheet 3 "Slip Staf Pusat" is not present
            $this->assertNotContains('Slip Staf Pusat', $sheetNames);

            // 2. Verify Sheet 1: Summary
            $summarySheet = $spreadsheet->getSheetByName('Summary');
            $this->assertNotNull($summarySheet);

            $titleA1 = (string) $summarySheet->getCell('A1')->getValue();
            $this->assertStringContainsString('PENGAJUAN GAJI KARYAWAN CV. ARYA DUTA', $titleA1);
            $this->assertStringContainsString('SEPTEMBER 2026', $titleA1);

            // Summary Table Headers
            $this->assertEquals('No.', $summarySheet->getCell('A3')->getValue());
            $this->assertEquals('Cabang/Divisi', $summarySheet->getCell('B3')->getValue());
            $this->assertEquals('PERINCIAN GAJI', $summarySheet->getCell('C3')->getValue());
            $this->assertEquals('Jumlah Karyawan', $summarySheet->getCell('L3')->getValue());

            // Summary Row 5: Staff Pusat data references
            $this->assertEquals('1', (string) $summarySheet->getCell('A5')->getValue());
            $this->assertEquals('Staff Pusat', $summarySheet->getCell('B5')->getValue());
            $this->assertEquals("='Staff Pusat'!H8", (string) $summarySheet->getCell('C5')->getValue());
            $this->assertEquals("='Staff Pusat'!J8", (string) $summarySheet->getCell('D5')->getValue());
            $this->assertEquals("='Staff Pusat'!K8", (string) $summarySheet->getCell('E5')->getValue());
            $this->assertEquals("='Staff Pusat'!L8", (string) $summarySheet->getCell('F5')->getValue());
            $this->assertEquals("='Staff Pusat'!M8", (string) $summarySheet->getCell('G5')->getValue());
            $this->assertEquals(2, $summarySheet->getCell('L5')->getValue()); // 2 employees

            // 3. Verify Sheet 2: Staff Pusat
            $staffSheet = $spreadsheet->getSheetByName('Staff Pusat');
            $this->assertNotNull($staffSheet);

            $this->assertEquals('DAFTAR GAJI KARYAWAN CV. ARYA DUTA', (string) $staffSheet->getCell('A1')->getValue());
            $this->assertEquals('PERINCIAN GAJI', (string) $staffSheet->getCell('H4')->getValue());
            $this->assertEquals('GAJI POKOK', (string) $staffSheet->getCell('H5')->getValue());

            // Employees data in rows 6 and 7
            $this->assertEquals('Agus Heryana', (string) $staffSheet->getCell('B6')->getValue());
            $this->assertEquals(8000000, $staffSheet->getCell('H6')->getValue());
            $this->assertEquals('=H6+J6+K6+L6+M6+O6+P6+Q6', (string) $staffSheet->getCell('R6')->getValue());

            $this->assertEquals('Sheren Regiana', (string) $staffSheet->getCell('B7')->getValue());
            $this->assertEquals(6000000, $staffSheet->getCell('H7')->getValue());

            // Total row in row 8
            $this->assertEquals('TOTAL', (string) $staffSheet->getCell('A8')->getValue());
            $this->assertEquals('=SUM(H6:H7)', (string) $staffSheet->getCell('H8')->getValue());
            $this->assertEquals('=SUM(AE6:AE7)', (string) $staffSheet->getCell('AE8')->getValue());
            $this->assertEquals('=SUM(AN6:AN7)', (string) $staffSheet->getCell('AN8')->getValue());

        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
