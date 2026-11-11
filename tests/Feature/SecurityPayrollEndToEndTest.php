<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Office;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use App\Services\Security\SecurityPayrollExportService;
use App\Services\Security\SecurityPayrollReportService;
use App\Services\Security\SecurityReconciliationService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPayrollEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected Office $office;

    protected Position $securityPosition;

    protected SecurityPayrollConfig $config;

    protected SecurityPayrollCalculationService $calculationService;

    protected PayslipService $payslipService;

    protected PayslipFinalizationService $finalizationService;

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

        $this->office = Office::first();
        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security', 'payroll_type' => 'security', 'is_active' => true]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        $this->config = SecurityPayrollConfig::getActiveConfig();
        $this->calculationService = app(SecurityPayrollCalculationService::class);
        $this->payslipService = app(PayslipService::class);
        $this->finalizationService = app(PayslipFinalizationService::class);
        $this->reportService = app(SecurityPayrollReportService::class);
        $this->reconciliationService = app(SecurityReconciliationService::class);
        $this->exportService = app(SecurityPayrollExportService::class);
    }

    protected function createSecurityEmployee(string $number, string $name, float $gajiPokok = 4500000): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'nik' => '3201'.str_pad((string) rand(1, 99999999), 12, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'employment_status' => 'active',
            'hire_date' => '2024-01-01',
            'position_id' => $this->securityPosition->id,
            'office_id' => $this->office->id,
            'department' => 'Security',
            'gaji_pokok' => $gajiPokok,
            'tunjangan_kehadiran' => 200000,
            'tunjangan_jabatan' => 100000,
        ]);
    }

    /**
     * SCENARIO 1: Full Normal Payroll Lifecycle End-to-End
     * Input attendance -> input regular OT -> input off-holiday OT -> calculate ->
     * preview -> generate payroll -> submit approval -> approve -> finalize -> immutable snapshot.
     */
    public function test_scenario_1_normal_payroll_lifecycle(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => 'E2E-2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'cutoff_date' => '2026-01-25',
            'payment_date' => '2026-01-31',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $employee = $this->createSecurityEmployee('SEC-E2E-01', 'Bambang Sudarsono', 4000000);

        // 1. HRD updates attendance and overtime via simplified input endpoint
        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'present_days' => 26,
            'off_days' => 5,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'regular_overtime_days' => 6,
            'regular_overtime_hours' => 18.0,
            'off_holiday_overtime_days' => 1,
            'off_holiday_overtime_hours' => 8.0,
            'off_holiday_rate_confirmed' => true,
        ]);
        $response->assertSessionHasNoErrors();

        // 2. Verify summary calculation
        $summary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();
        $this->assertNotNull($summary);
        $this->assertEquals(208.0, (float) $summary->attendance_hours); // 26 * 8
        $this->assertGreaterThan(0, (float) $summary->regular_overtime_cost);
        $this->assertGreaterThan(0, (float) $summary->off_holiday_overtime_cost);
        $this->assertGreaterThan(0, (float) $summary->total_overtime_cost);

        // 3. HRD checks preview endpoint
        $previewResponse = $this->actingAs($this->personalia)->postJson(route('payroll-security.preview'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'present_days' => 26,
            'regular_overtime_hours' => 18.0,
            'off_holiday_overtime_hours' => 8.0,
        ]);
        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonStructure(['success', 'data' => ['employee_id', 'metrics' => ['attendance_hours', 'regular_net_overtime_hours']]]);

        // 4. Generate payroll and sync security payslips
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $syncResponse = $this->actingAs($this->personalia)->post(route('payroll-security.periods.sync', $period->id));
        $syncResponse->assertSessionHas('success');

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();
        $this->assertNotNull($payslip);
        $this->assertEquals(PayslipStatus::DRAFT->value, $payslip->status);

        // Verify OVERTIME component exists on payslip
        $summary->refresh();
        $otItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNotNull($otItem);
        $this->assertEquals(number_format($summary->total_overtime_cost, 2, '.', ''), $otItem->amount);

        // 5. Submit for approval
        $submitResponse = $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $period->id), [
            'comment' => 'Pengajuan payroll security periode Januari 2026',
        ]);
        $submitResponse->assertSessionHas('success');
        $period->refresh();
        $payslip->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->status);
        $this->assertEquals(PayslipStatus::PENDING_REVIEW->value, $payslip->status);

        // 6. Approver approves payroll
        $approveResponse = $this->actingAs($this->approver)->post(route('approvals.periods.approve', $period->id), [
            'comment' => 'Disetujui, kalkulasi lembur security sudah diverifikasi',
        ]);
        $approveResponse->assertSessionHas('success');
        $period->refresh();
        $payslip->refresh();
        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $period->status);
        $this->assertEquals(PayslipStatus::APPROVED->value, $payslip->status);

        // 7. Finalize payroll
        $finalizedPeriod = $this->finalizationService->finalizePeriod($period, $this->personalia);
        $this->assertEquals(PayrollPeriodStatus::FINALIZED->value, $finalizedPeriod->status);
        $payslip->refresh();
        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);
        $this->assertNotNull($payslip->finalized_at);
        $this->assertNotNull($payslip->pdf_sha256);

        // 8. Verify audit logs record full lifecycle
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security_payroll.synced',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_period.finalized',
        ]);
    }

    /**
     * SCENARIO 2: Shortage and Carry-Forward Lifecycle
     * Period 1 has shortage -> Period 2 imports carry forward -> net shortage and offset tracked.
     */
    public function test_scenario_2_shortage_and_carry_forward_lifecycle(): void
    {
        // Period 1 (Past Period)
        $period1 = PayrollPeriod::create([
            'period_code' => 'E2E-2026-02',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'cutoff_date' => '2026-02-25',
            'payment_date' => '2026-02-28',
            'status' => PayrollPeriodStatus::FINALIZED->value,
            'created_by' => $this->personalia->id,
        ]);

        $employee = $this->createSecurityEmployee('SEC-E2E-02', 'Hendra Gunawan', 4200000);

        // Summary Period 1 has 16 hours shortage
        SecurityOvertimeSummary::create([
            'payroll_period_id' => $period1->id,
            'employee_id' => $employee->id,
            'present_days' => 18,
            'off_days' => 4,
            'absent_days' => 2,
            'attendance_hours' => 144.0,
            'target_hours' => 160.0,
            'current_shortage_hours' => 16.0,
            'previous_shortage_hours' => 0.0,
            'total_shortage_hours' => 16.0,
            'shortage_offset_hours' => 0.0,
            'remaining_shortage_hours' => 16.0,
        ]);

        // Period 2 (Current Period)
        $period2 = PayrollPeriod::create([
            'period_code' => 'E2E-2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'cutoff_date' => '2026-03-25',
            'payment_date' => '2026-03-31',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        // Input attendance in Period 2 with overtime
        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period2->id,
            'employee_id' => $employee->id,
            'present_days' => 24,
            'off_days' => 7,
            'previous_shortage_hours' => 16.0, // Carry forward from Period 1
            'regular_overtime_days' => 4,
            'regular_overtime_hours' => 20.0, // 20 hours OT will offset 16 hours shortage
        ]);
        $response->assertSessionHasNoErrors();

        $summary2 = SecurityOvertimeSummary::where('payroll_period_id', $period2->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertEquals(16.0, (float) $summary2->previous_shortage_hours);
        $this->assertEquals(16.0, (float) $summary2->total_shortage_hours);
        $this->assertEquals(16.0, (float) $summary2->shortage_offset_hours); // 16 offset
        $this->assertEquals(0.0, (float) $summary2->remaining_shortage_hours); // Fully settled

        // Verify Shortage Report accurately tracks this
        $shortageRows = $this->reportService->getShortageReport($period2);
        $empRow = collect($shortageRows)->firstWhere('employee_number', 'SEC-E2E-02');
        $this->assertNotNull($empRow);
        $this->assertEquals(16.0, $empRow['previous_shortage']);
        $this->assertEquals(16.0, $empRow['shortage_offset']);
        $this->assertEquals(0.0, $empRow['remaining_shortage']);
        $this->assertEquals('TUNTAS', $empRow['status']);
    }

    /**
     * SCENARIO 3: Overtime Separation & Unconfirmed Tariff Protection
     * REGULAR vs OFF_HOLIDAY separation, protection when rate is unconfirmed.
     */
    public function test_scenario_3_overtime_separation_and_protection(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => 'E2E-2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'payment_date' => '2026-04-30',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $employee = $this->createSecurityEmployee('SEC-E2E-03', 'Rudi Hartono');

        // Input overtime with off_holiday_rate_confirmed = false (tariff not yet approved)
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'present_days' => 25,
            'off_days' => 5,
            'regular_overtime_days' => 4,
            'regular_overtime_hours' => 12.0,
            'off_holiday_overtime_days' => 2,
            'off_holiday_overtime_hours' => 16.0,
            'off_holiday_rate_confirmed' => false, // Unconfirmed!
        ]);

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Regular OT should be calculated, off-holiday OT cost MUST be 0.00
        $this->assertGreaterThan(0, (float) $summary->regular_overtime_cost);
        $this->assertEquals(0.00, (float) $summary->off_holiday_overtime_cost);
        $this->assertEquals($summary->regular_overtime_cost, $summary->total_overtime_cost);

        // Overtime report shows unconfirmed status
        $otRows = $this->reportService->getOvertimeReport($period);
        $offOt = collect($otRows)->where('employee_number', 'SEC-E2E-03')->where('category', 'OFF_HOLIDAY')->first();
        $this->assertNotNull($offOt);
        $this->assertEquals('PENDING_KONFIRMASI', $offOt['status']);
        $this->assertEquals(0.00, $offOt['amount']);
    }

    /**
     * SCENARIO 4: Request Changes Workflow
     * Submit -> Request Changes -> Modifiable State -> Recalculate -> Resubmit -> Approve.
     */
    public function test_scenario_4_request_changes_workflow(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => 'E2E-2026-05',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $employee = $this->createSecurityEmployee('SEC-E2E-04', 'Dedi Mulyadi');
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        // Personalia submits
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $period->id), [
            'comment' => 'Pengajuan awal',
        ]);
        $period->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->status);

        // Approver requests changes
        $this->actingAs($this->approver)->post(route('approvals.periods.request-changes', $period->id), [
            'comment' => 'Mohon cek kembali kehadiran tanggal 15 Mei',
        ]);
        $period->refresh();
        $this->assertEquals(PayrollPeriodStatus::CHANGES_REQUESTED->value, $period->status);
        $this->assertTrue($period->canBeModified());

        // Personalia fixes data and updates
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'present_days' => 21,
            'regular_overtime_hours' => 10.0,
        ]);

        // Resubmit
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $period->id), [
            'comment' => 'Sudah diperbaiki sesuai catatan',
        ]);
        $period->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->status);

        // Approver approves
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $period->id), [
            'comment' => 'Revisi sudah sesuai, disetujui',
        ]);
        $period->refresh();
        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $period->status);

        // Approval event history check
        $events = PayrollApprovalEvent::where('payroll_period_id', $period->id)->get();
        $this->assertCount(4, $events); // submit -> request changes -> resubmit -> approve
    }

    /**
     * SCENARIO 5: Finalized Payroll Immutability
     * Master changes do not affect old payslips; direct edits on finalized payroll rejected.
     */
    public function test_scenario_5_finalized_payroll_immutability(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => 'E2E-2026-06',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $employee = $this->createSecurityEmployee('SEC-E2E-05', 'Joko Susilo', 4000000);
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        // Approve and finalize
        $period->update(['status' => PayrollPeriodStatus::APPROVED->value]);
        $period->payslips()->update(['status' => PayslipStatus::APPROVED->value]);
        $this->finalizationService->finalizePeriod($period, $this->personalia);
        $period->refresh();

        $payslip = Payslip::where('payroll_period_id', $period->id)->first();
        $originalBasicSalary = $payslip->basic_salary;
        $originalNetSalary = $payslip->net_salary;
        $originalPdfSha256 = $payslip->pdf_sha256;

        // 1. Simulate tampering master employee salary
        $employee->update(['gaji_pokok' => 99000000]);

        // 2. Verify finalized payslip is completely unchanged
        $payslip->refresh();
        $this->assertEquals($originalBasicSalary, $payslip->basic_salary);
        $this->assertEquals($originalNetSalary, $payslip->net_salary);
        $this->assertEquals($originalPdfSha256, $payslip->pdf_sha256);

        // 3. Attempting to update attendance on finalized period throws DomainException
        $this->expectException(DomainException::class);
        $this->withoutExceptionHandling()->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'present_days' => 25,
        ]);
    }

    /**
     * SCENARIO 6: Reporting, Reconciliation, and Export End-to-End
     */
    public function test_scenario_6_reporting_reconciliation_and_export(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => 'E2E-2026-07',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $emp1 = $this->createSecurityEmployee('SEC-E2E-06', 'Asep Saepudin');
        $emp2 = $this->createSecurityEmployee('SEC-E2E-07', 'Cecep Hidayat');

        // Setup summaries
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $emp1->id,
            'present_days' => 20,
            'regular_overtime_hours' => 10.0,
        ]);

        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $emp2->id,
            'present_days' => 18,
            'absent_days' => 2,
        ]);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        // 1. Dashboard reports endpoint returns 200
        $dashResponse = $this->actingAs($this->personalia)->get(route('payroll-security.reports.index', [
            'period_id' => $period->id,
            'tab' => 'dashboard',
        ]));
        $dashResponse->assertStatus(200);

        // 2. Run reconciliation endpoint
        $reconcileResponse = $this->actingAs($this->personalia)->post(route('payroll-security.reconciliation.run'), [
            'period_id' => $period->id,
        ]);
        $reconcileResponse->assertSessionHas('success');

        // 3. Export XLSX
        $xlsxResponse = $this->actingAs($this->personalia)->get(route('payroll-security.reports.export', [
            'type' => 'employee-detail',
            'period_id' => $period->id,
            'format' => 'xlsx',
        ]));
        $xlsxResponse->assertStatus(200);

        // 4. Export CSV
        $csvResponse = $this->actingAs($this->personalia)->get(route('payroll-security.reports.export', [
            'type' => 'shortage',
            'period_id' => $period->id,
            'format' => 'csv',
        ]));
        $csvResponse->assertStatus(200);
    }
}
