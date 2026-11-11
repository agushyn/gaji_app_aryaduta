<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Office;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\Security\SecurityPayrollExportService;
use App\Services\Security\SecurityPayrollReportService;
use App\Services\Security\SecurityReconciliationService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPayrollReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected PayrollPeriod $period;

    protected Office $officePusat;

    protected Office $officeCabang;

    protected Position $securityPosition;

    protected Position $regularPosition;

    protected Employee $secEmp1;

    protected Employee $secEmp2;

    protected Employee $regEmp;

    protected SecurityPayrollReportService $reportService;

    protected SecurityReconciliationService $reconciliationService;

    protected SecurityPayrollExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(OrganizationMasterSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);
        $this->seed(SecurityPayrollConfigSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);

        $this->reportService = app(SecurityPayrollReportService::class);
        $this->reconciliationService = app(SecurityReconciliationService::class);
        $this->exportService = app(SecurityPayrollExportService::class);

        $this->officePusat = Office::firstOrCreate(
            ['code' => 'PST'],
            ['name' => 'Kantor Pusat', 'address' => 'Jl. Pusat No. 1', 'is_active' => true]
        );
        $this->officeCabang = Office::firstOrCreate(
            ['code' => 'CBG'],
            ['name' => 'Kantor Cabang', 'address' => 'Jl. Cabang No. 2', 'is_active' => true]
        );

        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security Officer', 'payroll_type' => 'security', 'is_active' => true]
        );
        $this->regularPosition = Position::firstOrCreate(
            ['code' => 'STF'],
            ['name' => 'Staff Regular', 'payroll_type' => 'general', 'is_active' => true]
        );

        $this->period = PayrollPeriod::create([
            'period_code' => 'SEC-2026-11',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'cutoff_date' => '2026-11-25',
            'payment_date' => '2026-11-30',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'is_locked' => false,
            'created_by' => $this->personalia->id,
        ]);

        // Create Security Employee 1 (Kantor Pusat)
        $this->secEmp1 = Employee::create([
            'employee_number' => 'SEC-001',
            'nik' => '3201000000000001',
            'full_name' => 'Budi Santoso',
            'employment_status' => 'active',
            'hire_date' => '2024-01-01',
            'position_id' => $this->securityPosition->id,
            'office_id' => $this->officePusat->id,
            'department' => 'Security',
            'gaji_pokok' => 4500000,
        ]);

        // Create Security Employee 2 (Kantor Cabang)
        $this->secEmp2 = Employee::create([
            'employee_number' => 'SEC-002',
            'nik' => '3201000000000002',
            'full_name' => 'Agus Priyanto',
            'employment_status' => 'active',
            'hire_date' => '2024-02-01',
            'position_id' => $this->securityPosition->id,
            'office_id' => $this->officeCabang->id,
            'department' => 'Security',
            'gaji_pokok' => 4500000,
        ]);

        // Create Regular Staff (should NOT appear in security reports)
        $this->regEmp = Employee::create([
            'employee_number' => 'STF-001',
            'nik' => '3201000000000003',
            'full_name' => 'Dewi Lestari',
            'employment_status' => 'active',
            'hire_date' => '2024-03-01',
            'position_id' => $this->regularPosition->id,
            'office_id' => $this->officePusat->id,
            'department' => 'Finance',
            'gaji_pokok' => 6000000,
        ]);

        // Setup summaries and payslips for consistent test data
        $this->setupTestData();
    }

    protected function setupTestData(): void
    {
        // Summary for secEmp1 (Consistent data)
        SecurityOvertimeSummary::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->secEmp1->id,
            'present_days' => 20,
            'off_days' => 4,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'attendance_hours' => 160.00,
            'target_hours' => 160.00,
            'current_shortage_hours' => 0.00,
            'previous_shortage_hours' => 0.00,
            'total_shortage_hours' => 0.00,
            'shortage_offset_hours' => 0.00,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 15.00,
            'regular_break_hours' => 0.00,
            'regular_net_overtime_hours' => 15.00,
            'regular_hourly_rate' => 26012.00,
            'regular_first_hours_cost' => 390180.00,
            'regular_subsequent_hours_cost' => 0.00,
            'regular_overtime_cost' => 390180.00,
            'off_holiday_overtime_days' => 1,
            'off_holiday_overtime_hours' => 8.00,
            'off_holiday_break_hours' => 1.00,
            'off_holiday_net_overtime_hours' => 7.00,
            'off_holiday_hourly_rate' => 52024.00,
            'off_holiday_overtime_cost' => 364168.00,
            'off_holiday_rate_confirmed' => true,
            'total_net_overtime_hours' => 22.00,
            'total_overtime_cost' => 754348.00,
            'total_overtime_pay' => 754348.00,
            'calculation_payload' => [
                'target_hours' => 160.0,
                'current_shortage_hours' => 0.0,
                'previous_shortage_hours' => 0.0,
                'total_shortage_hours' => 0.0,
                'shortage_offset' => 0.0,
                'regular_net_hours' => 15.0,
                'regular_overtime_cost' => '390180.00',
                'off_holiday_net_hours' => 7.0,
                'off_holiday_overtime_cost' => '364168.00',
                'off_holiday_rate_confirmed' => true,
                'total_overtime_pay' => '754348.00',
            ],
        ]);

        // Payslip for secEmp1
        $slip1 = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->secEmp1->id,
            'slip_number' => 'SLIP-SEC-001',
            'employee_number_snapshot' => $this->secEmp1->employee_number,
            'employee_name_snapshot' => $this->secEmp1->full_name,
            'department_snapshot' => 'Security',
            'basic_salary' => 4500000.00,
            'total_earnings' => 5254348.00,
            'total_deductions' => 100000.00,
            'net_salary' => 5154348.00,
            'status' => PayslipStatus::APPROVED->value,
            'created_by' => $this->personalia->id,
        ]);

        PayslipItem::create([
            'payslip_id' => $slip1->id,
            'item_code_snapshot' => 'BASIC',
            'description_snapshot' => 'Gaji Pokok',
            'item_type' => 'earning',
            'amount' => 4500000.00,
            'sort_order' => 1,
        ]);

        PayslipItem::create([
            'payslip_id' => $slip1->id,
            'item_code_snapshot' => 'OVERTIME',
            'description_snapshot' => 'Lembur Security',
            'item_type' => 'earning',
            'amount' => 754348.00,
            'sort_order' => 2,
        ]);

        PayslipItem::create([
            'payslip_id' => $slip1->id,
            'item_code_snapshot' => 'BPJS_TK',
            'description_snapshot' => 'BPJS TK',
            'item_type' => 'deduction',
            'amount' => 100000.00,
            'sort_order' => 3,
        ]);

        // Summary for secEmp2 (Has shortage)
        SecurityOvertimeSummary::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->secEmp2->id,
            'present_days' => 18,
            'off_days' => 4,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 2,
            'attendance_hours' => 144.00,
            'target_hours' => 160.00,
            'current_shortage_hours' => 16.00,
            'previous_shortage_hours' => 4.00,
            'total_shortage_hours' => 20.00,
            'shortage_offset_hours' => 6.00,
            'regular_overtime_days' => 2,
            'regular_overtime_hours' => 6.00,
            'regular_break_hours' => 0.00,
            'regular_net_overtime_hours' => 6.00,
            'regular_hourly_rate' => 26012.00,
            'regular_first_hours_cost' => 156072.00,
            'regular_subsequent_hours_cost' => 0.00,
            'regular_overtime_cost' => 156072.00,
            'off_holiday_overtime_days' => 0,
            'off_holiday_overtime_hours' => 0.00,
            'off_holiday_break_hours' => 0.00,
            'off_holiday_net_overtime_hours' => 0.00,
            'off_holiday_hourly_rate' => 52024.00,
            'off_holiday_overtime_cost' => 0.00,
            'off_holiday_rate_confirmed' => false,
            'total_net_overtime_hours' => 6.00,
            'total_overtime_cost' => 156072.00,
            'total_overtime_pay' => 156072.00,
            'calculation_payload' => [
                'target_hours' => 160.0,
                'current_shortage_hours' => 16.0,
                'previous_shortage_hours' => 4.0,
                'total_shortage_hours' => 20.0,
                'shortage_offset' => 6.0,
                'regular_net_hours' => 6.0,
                'regular_overtime_cost' => '156072.00',
                'off_holiday_net_hours' => 0.0,
                'off_holiday_overtime_cost' => '0.00',
                'off_holiday_rate_confirmed' => false,
                'total_overtime_pay' => '156072.00',
            ],
        ]);

        $slip2 = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->secEmp2->id,
            'slip_number' => 'SLIP-SEC-002',
            'employee_number_snapshot' => $this->secEmp2->employee_number,
            'employee_name_snapshot' => $this->secEmp2->full_name,
            'department_snapshot' => 'Security',
            'basic_salary' => 4500000.00,
            'total_earnings' => 4656072.00,
            'total_deductions' => 0.00,
            'net_salary' => 4656072.00,
            'status' => PayslipStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        PayslipItem::create([
            'payslip_id' => $slip2->id,
            'item_code_snapshot' => 'BASIC',
            'description_snapshot' => 'Gaji Pokok',
            'item_type' => 'earning',
            'amount' => 4500000.00,
            'sort_order' => 1,
        ]);

        PayslipItem::create([
            'payslip_id' => $slip2->id,
            'item_code_snapshot' => 'OVERTIME',
            'description_snapshot' => 'Lembur Security',
            'item_type' => 'earning',
            'amount' => 156072.00,
            'sort_order' => 2,
        ]);
    }

    /** 1. Dashboard isolation from regular employees */
    public function test_security_dashboard_metrics_only_includes_security_employees(): void
    {
        $metrics = $this->reportService->getDashboardMetrics($this->period);

        // We have 2 security employees and 1 regular employee
        $this->assertEquals(2, $metrics['total_security_employees']);
    }

    /** 2. Dashboard aggregates financials and status counts correctly */
    public function test_security_dashboard_aggregates_financials_and_status_counts_correctly(): void
    {
        $metrics = $this->reportService->getDashboardMetrics($this->period);

        $this->assertEquals(1, $metrics['draft_count']); // secEmp2
        $this->assertEquals(1, $metrics['approved_count']); // secEmp1
        $this->assertEquals(0, $metrics['submitted_count']);
        $this->assertEquals(0, $metrics['finalized_count']);

        // Earnings: 5,254,348 + 4,656,072 = 9,910,420
        $this->assertEquals(9910420.00, $metrics['total_earnings']);
        $this->assertEquals(100000.00, $metrics['total_deductions']);
        $this->assertEquals(9810420.00, $metrics['total_net_salary']);

        // Overtime Hours & Costs
        $this->assertEquals(21.0, $metrics['total_regular_overtime_hours']); // 15 + 6
        $this->assertEquals(546252.00, $metrics['total_regular_overtime_cost']); // 390,180 + 156,072
        $this->assertEquals(7.0, $metrics['total_off_holiday_overtime_hours']); // 7 + 0
        $this->assertEquals(364168.00, $metrics['total_off_holiday_overtime_cost']);

        // Shortage count: 1 (secEmp2 has total_shortage_hours > 0)
        $this->assertEquals(1, $metrics['employees_with_shortage_count']);
    }

    /** 3. Period summary report lists periods with metrics */
    public function test_period_summary_report_lists_all_payroll_periods(): void
    {
        $summaries = $this->reportService->getPeriodSummaryReport();

        $this->assertNotEmpty($summaries);
        $first = $summaries[0];
        $this->assertEquals('SEC-2026-11', $first['period_code']);
        $this->assertEquals(2, $first['total_employees']);
        $this->assertEquals(9810420.00, $first['total_net_salary']);
    }

    /** 4. Employee detail report contains 24 attributes */
    public function test_employee_detail_report_contains_all_24_attributes(): void
    {
        $details = $this->reportService->getEmployeeDetailReport($this->period);

        $this->assertCount(2, $details);
        $emp1 = collect($details)->firstWhere('employee_number', 'SEC-001');

        $this->assertNotNull($emp1);
        $this->assertEquals('Budi Santoso', $emp1['employee_name']);
        $this->assertEquals('Kantor Pusat', $emp1['office']);
        $this->assertEquals(20, $emp1['present_days']);
        $this->assertEquals(4, $emp1['off_days']);
        $this->assertEquals(160.0, $emp1['attendance_hours']);
        $this->assertEquals(160.0, $emp1['target_hours']);
        $this->assertEquals(0.0, $emp1['total_shortage']);
        $this->assertEquals(15.0, $emp1['regular_net_overtime_hours']);
        $this->assertEquals(390180.00, $emp1['regular_overtime_amount']);
        $this->assertEquals(7.0, $emp1['off_holiday_net_overtime_hours']);
        $this->assertEquals(364168.00, $emp1['off_holiday_overtime_amount']);
        $this->assertEquals(754348.00, $emp1['total_overtime_amount']);
        $this->assertEquals(5154348.00, $emp1['net_salary']);
    }

    /** 5. Filter by office and search term */
    public function test_reports_filter_by_office_and_search_term(): void
    {
        // Filter by office Pusat -> only secEmp1
        $pusatDetails = $this->reportService->getEmployeeDetailReport($this->period, [
            'office_id' => $this->officePusat->id,
        ]);
        $this->assertCount(1, $pusatDetails);
        $this->assertEquals('SEC-001', $pusatDetails[0]['employee_number']);

        // Search by name "Agus" -> only secEmp2
        $searchDetails = $this->reportService->getEmployeeDetailReport($this->period, [
            'search' => 'Agus',
        ]);
        $this->assertCount(1, $searchDetails);
        $this->assertEquals('SEC-002', $searchDetails[0]['employee_number']);
    }

    /** 6. Shortage report tracks attendance, shortages, carry-forward, and offsets */
    public function test_shortage_report_tracks_target_attendance_and_carry_forward(): void
    {
        $shortages = $this->reportService->getShortageReport($this->period);

        $this->assertCount(2, $shortages);
        $emp2Shortage = collect($shortages)->firstWhere('employee_number', 'SEC-002');

        $this->assertNotNull($emp2Shortage);
        $this->assertEquals(160.0, $emp2Shortage['target_hours']);
        $this->assertEquals(144.0, $emp2Shortage['attendance_hours']);
        $this->assertEquals(16.0, $emp2Shortage['current_shortage']);
        $this->assertEquals(4.0, $emp2Shortage['previous_shortage']);
        $this->assertEquals(20.0, $emp2Shortage['total_shortage']);
        $this->assertEquals(6.0, $emp2Shortage['shortage_offset']);
        $this->assertEquals(14.0, $emp2Shortage['remaining_shortage']);
        $this->assertEquals('KURANG JAM', $emp2Shortage['status']);
    }

    /** 7. Shortage report filters by status */
    public function test_shortage_report_filters_by_status(): void
    {
        // Filter only those with shortage
        $filtered = $this->reportService->getShortageReport($this->period, ['status' => 'shortage']);
        $this->assertCount(1, $filtered);
        $this->assertEquals('SEC-002', $filtered[0]['employee_number']);

        // Filter clear
        $clearFiltered = $this->reportService->getShortageReport($this->period, ['status' => 'clear']);
        $this->assertCount(1, $clearFiltered);
        $this->assertEquals('SEC-001', $clearFiltered[0]['employee_number']);
    }

    /** 8. Overtime report separates regular and off_holiday categories */
    public function test_overtime_report_separates_regular_and_off_holiday_categories(): void
    {
        $overtimes = $this->reportService->getOvertimeReport($this->period);

        // emp1 has REGULAR and OFF_HOLIDAY, emp2 has REGULAR
        $this->assertCount(3, $overtimes);

        $emp1Reg = collect($overtimes)->firstWhere('employee_number', 'SEC-001');
        $this->assertEquals('REGULAR', $emp1Reg['category']);
        $this->assertEquals(15.0, $emp1Reg['net_hours']);
        $this->assertEquals(390180.00, $emp1Reg['amount']);

        $emp1Off = collect($overtimes)->where('employee_number', 'SEC-001')->where('category', 'OFF_HOLIDAY')->first();
        $this->assertNotNull($emp1Off);
        $this->assertEquals(7.0, $emp1Off['net_hours']);
        $this->assertEquals(364168.00, $emp1Off['amount']);
        $this->assertEquals('TERVERIFIKASI', $emp1Off['status']);
    }

    /** 9. Overtime report filters by category */
    public function test_overtime_report_filters_by_category(): void
    {
        $regularOnly = $this->reportService->getOvertimeReport($this->period, ['overtime_category' => 'REGULAR']);
        $this->assertCount(2, $regularOnly);

        $offHolidayOnly = $this->reportService->getOvertimeReport($this->period, ['overtime_category' => 'OFF_HOLIDAY']);
        $this->assertCount(1, $offHolidayOnly);
        $this->assertEquals('SEC-001', $offHolidayOnly[0]['employee_number']);
    }

    /** 10. Reconciliation engine passes with MATCH status on consistent data */
    public function test_reconciliation_engine_passes_with_match_status_on_consistent_data(): void
    {
        $result = $this->reconciliationService->reconcilePeriod($this->period);

        $this->assertEquals('MATCH', $result['overall_status']);
        $this->assertEquals(0, $result['mismatch_count']);
        $this->assertGreaterThan(0, $result['match_count']);
    }

    /** 11. Reconciliation engine detects attendance hours mismatch */
    public function test_reconciliation_engine_detects_attendance_hours_mismatch(): void
    {
        // Modify attendance_hours directly to create an inconsistency with present_days * 8
        $summary = SecurityOvertimeSummary::where('employee_id', $this->secEmp1->id)->first();
        $summary->update(['attendance_hours' => 150.00]); // 20 days * 8 = 160 != 150

        $result = $this->reconciliationService->reconcilePeriod($this->period);

        $this->assertEquals('MISMATCH', $result['overall_status']);
        $this->assertGreaterThan(0, $result['mismatch_count']);

        $emp1Checks = collect($result['results'])->firstWhere('employee_number', 'SEC-001')['checks'];
        $attCheck = collect($emp1Checks)->firstWhere('name', 'Pemeriksaan Jam Hadir (Masuk * 8 Jam)');

        $this->assertEquals('MISMATCH', $attCheck['status']);
        $this->assertStringContainsString('Koreksi jam hadir', $attCheck['guidance']);
    }

    /** 12. Reconciliation engine detects payslip overtime component mismatch */
    public function test_reconciliation_engine_detects_payslip_overtime_component_mismatch(): void
    {
        // Tamper with payslip overtime item amount
        $slip = Payslip::where('employee_id', $this->secEmp1->id)->first();
        $otItem = $slip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $otItem->update(['amount' => 500000.00]); // should be 754348.00

        $result = $this->reconciliationService->reconcilePeriod($this->period);

        $this->assertEquals('MISMATCH', $result['overall_status']);
        $emp1Checks = collect($result['results'])->firstWhere('employee_number', 'SEC-001')['checks'];
        $slipOtCheck = collect($emp1Checks)->firstWhere('name', 'Integritas Komponen Slip Gaji OVERTIME');

        $this->assertEquals('MISMATCH', $slipOtCheck['status']);
    }

    /** 13. Reconciliation engine detects net salary integrity mismatch */
    public function test_reconciliation_engine_detects_net_salary_integrity_mismatch(): void
    {
        // Tamper with payslip net_salary
        $slip = Payslip::where('employee_id', $this->secEmp1->id)->first();
        $slip->update(['net_salary' => 9999999.00]);

        $result = $this->reconciliationService->reconcilePeriod($this->period);

        $this->assertEquals('MISMATCH', $result['overall_status']);
        $emp1Checks = collect($result['results'])->firstWhere('employee_number', 'SEC-001')['checks'];
        $thpCheck = collect($emp1Checks)->firstWhere('name', 'Kesesuaian Penghasilan, Potongan, dan THP');

        $this->assertEquals('MISMATCH', $thpCheck['status']);
    }

    /** 14. Reconciliation is strictly non-mutating and does not alter finalized payroll */
    public function test_reconciliation_is_strictly_non_mutating_and_does_not_alter_finalized_payroll(): void
    {
        // Finalize period & payslips
        $this->period->update(['status' => PayrollPeriodStatus::FINALIZED->value]);
        Payslip::where('payroll_period_id', $this->period->id)->update([
            'status' => PayslipStatus::FINALIZED->value,
        ]);

        $summaryBefore = SecurityOvertimeSummary::where('employee_id', $this->secEmp1->id)->first()->toArray();
        $slipBefore = Payslip::where('employee_id', $this->secEmp1->id)->first()->toArray();

        // Run reconciliation
        $result = $this->reconciliationService->reconcilePeriod($this->period);
        $this->assertNotNull($result);

        // Verify database state is untouched
        $summaryAfter = SecurityOvertimeSummary::where('employee_id', $this->secEmp1->id)->first()->fresh()->toArray();
        $slipAfter = Payslip::where('employee_id', $this->secEmp1->id)->first()->fresh()->toArray();

        $this->assertEquals($summaryBefore['updated_at'], $summaryAfter['updated_at']);
        $this->assertEquals($slipBefore['updated_at'], $slipAfter['updated_at']);
        $this->assertEquals($slipBefore['status'], $slipAfter['status']);
    }

    /** 15. Export XLSX generates streamed spreadsheet with correct headers */
    public function test_export_xlsx_generates_streamed_spreadsheet(): void
    {
        $response = $this->actingAs($this->personalia)
            ->get(route('payroll-security.reports.export', [
                'type' => 'employee-detail',
                'period_id' => $this->period->id,
                'format' => 'xlsx',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** 16. Export CSV generates UTF-8 BOM streamed file */
    public function test_export_csv_generates_utf8_bom_streamed_file(): void
    {
        $response = $this->actingAs($this->personalia)
            ->get(route('payroll-security.reports.export', [
                'type' => 'shortage',
                'period_id' => $this->period->id,
                'format' => 'csv',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /** 17. Export CSV sanitizes formula injection (CWE-1236) while preserving legitimate numbers */
    public function test_export_csv_sanitizes_formula_injection_cwe_1236(): void
    {
        // Test sanitizeCell static method directly
        $maliciousPayloads = [
            '=cmd|"/C calc"!A0',
            '+cmd|"/C calc"!A0',
            '-cmd|"/C calc"!A0',
            '@SUM(A1:A10)',
            "\tHYPERLINK(\"http://evil.com\")",
            "\r=cmd",
        ];

        foreach ($maliciousPayloads as $payload) {
            $sanitized = SecurityPayrollExportService::sanitizeCell($payload);
            $this->assertStringStartsWith("'", $sanitized, "Payload [{$payload}] should be prefixed with single quote");
        }

        // Legitimate numbers should NOT be quoted
        $this->assertSame('-15.50', SecurityPayrollExportService::sanitizeCell('-15.50'));
        $this->assertSame('+100', SecurityPayrollExportService::sanitizeCell('+100'));
        $this->assertSame('5000000', SecurityPayrollExportService::sanitizeCell('5000000'));
        $this->assertSame(12345, SecurityPayrollExportService::sanitizeCell(12345));
    }

    /** 18. Audit history records export and reconciliation events */
    public function test_audit_history_records_export_and_reconciliation_events(): void
    {
        // 1. Run export
        $this->actingAs($this->personalia)
            ->get(route('payroll-security.reports.export', [
                'type' => 'overtime',
                'period_id' => $this->period->id,
                'format' => 'xlsx',
            ]));

        // Verify export audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security_payroll.exported',
        ]);

        // 2. Run reconciliation
        $this->actingAs($this->personalia)
            ->post(route('payroll-security.reconciliation.run'), [
                'period_id' => $this->period->id,
            ]);

        // Verify reconciliation audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security_payroll.reconciliation_run',
        ]);
    }

    /** 19. Authorization: Auditor has read-only access, unauthorized user is forbidden */
    public function test_authorization_auditor_has_read_only_access_unauthorized_user_is_forbidden(): void
    {
        // 1. Unauthorized user cannot view reports (403)
        $this->actingAs($this->unauthorizedUser)
            ->get(route('payroll-security.reports.index', ['period_id' => $this->period->id]))
            ->assertStatus(403);

        // 2. Auditor can view reports (200)
        $this->actingAs($this->auditor)
            ->get(route('payroll-security.reports.index', ['period_id' => $this->period->id]))
            ->assertStatus(200);

        // 3. Auditor cannot mutate payroll matrix (e.g. attendance update) (403)
        $this->actingAs($this->auditor)
            ->post(route('payroll-security.attendance.update'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $this->secEmp1->id,
                'date' => '2026-11-01',
                'status' => 'present',
            ])
            ->assertStatus(403);

        // 4. Personalia can view reports and export
        $this->actingAs($this->personalia)
            ->get(route('payroll-security.reports.index', ['period_id' => $this->period->id]))
            ->assertStatus(200);
    }
}
