<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipPdfService;
use App\Services\PayslipService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityPayrollIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected PayrollPeriod $period;

    protected Position $securityPosition;

    protected Position $regularPosition;

    protected SecurityPayrollCalculationService $securityService;

    protected PayslipService $payslipService;

    protected PayslipFinalizationService $finalizationService;

    protected SecurityPayrollConfig $config;

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
        $this->auditor = User::where('email', 'auditor@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();

        $this->securityService = app(SecurityPayrollCalculationService::class);
        $this->payslipService = app(PayslipService::class);
        $this->finalizationService = app(PayslipFinalizationService::class);
        $this->config = SecurityPayrollConfig::getActiveConfig();

        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security', 'payroll_type' => 'security', 'is_active' => true]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        $this->regularPosition = Position::firstOrCreate(
            ['code' => 'STF'],
            ['name' => 'Staff Pelaksana', 'payroll_type' => 'regular', 'is_active' => true]
        );

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-08-SEC',
            'start_date' => '2026-08-22',
            'end_date' => '2026-09-21',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);
    }

    protected function createSecurityEmployee(string $number, string $name, float $gajiPokok = 3500000): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'full_name' => $name,
            'position_id' => $this->securityPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => $gajiPokok,
            'tunjangan_jabatan' => 200000,
            'tunjangan_kehadiran' => 300000,
            'tunjangan_keluarga' => 0,
        ]);
    }

    protected function createRegularEmployee(string $number, string $name, float $gajiPokok = 4000000): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'full_name' => $name,
            'position_id' => $this->regularPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => $gajiPokok,
            'tunjangan_jabatan' => 500000,
        ]);
    }

    /**
     * 1. Generate payroll Security dari hasil Calculation Engine.
     */
    public function test_generate_payroll_security_from_calculation_engine_results(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-01', 'Wawan Security');

        // Configure Wawan's summary with 26 present, 5 off, 5 days regular overtime (40h)
        $this->securityService->calculateForEmployee($sec, $this->period, $this->config, [
            'present_days' => 26,
            'off_days' => 5,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
        ]);

        // Auto generate payroll for the period
        $res = $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->assertGreaterThanOrEqual(1, $res['created'] + $res['updated']);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $sec->id)
            ->first();

        $this->assertNotNull($payslip);
        $this->assertEquals(PayslipStatus::DRAFT->value, $payslip->status);

        // Check OVERTIME item
        $overtimeItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNotNull($overtimeItem);
        $this->assertEquals('945000.00', $overtimeItem->amount);
        $this->assertEquals('35.00', $overtimeItem->quantity);

        // Ensure net salary = basic salary (3.500.000) + allowances (500.000) + overtime (945.000) = 4.945.000
        $this->assertEquals('4945000.00', $payslip->net_salary);
    }

    /**
     * 2. Mencegah generate payroll ganda untuk karyawan dan periode yang sama (Idempotency).
     */
    public function test_prevents_duplicate_payroll_generation_for_same_employee_and_period(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-02', 'Budi Security');

        // Run auto generate 1st time
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);
        $countAfterFirst = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->count();
        $this->assertEquals(1, $countAfterFirst);

        // Run auto generate 2nd time (retry)
        $resultSecond = $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);
        $countAfterSecond = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->count();

        $this->assertEquals(1, $countAfterSecond, 'Payslip count must remain exactly 1 without duplicates.');
        $this->assertEquals(1, SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->count());
        $this->assertGreaterThanOrEqual(1, $resultSecond['updated']);
    }

    /**
     * 3. Mapping komponen penghasilan dan pemisahan komponen lembur REGULAR dan OFF_HOLIDAY.
     */
    public function test_component_mapping_regular_and_off_holiday_overtime(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-03', 'Hendra Security');

        // Employee has 5 days regular overtime (40h) and 1 day off holiday overtime (8h)
        $this->securityService->calculateForEmployee($sec, $this->period, $this->config, [
            'present_days' => 26,
            'off_days' => 5,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
            'off_holiday_overtime_days' => 1,
            'off_holiday_overtime_hours' => 8.0,
            'off_holiday_rate_confirmed' => false, // unconfirmed tariff protection
        ]);

        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $sec->id)
            ->first();

        $overtimeItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNotNull($overtimeItem);

        // Regular cost is Rp 945.000, off holiday cost is Rp 0 (unconfirmed protection)
        $this->assertEquals('945000.00', $overtimeItem->amount);

        // Check note mentions both REGULAR and OFF_HOLIDAY
        $this->assertStringContainsString('Lembur Reguler', $overtimeItem->note);
        $this->assertStringContainsString('Lembur Libur', $overtimeItem->note);
        $this->assertStringContainsString('Menunggu Konfirmasi Tarif', $overtimeItem->note);

        // Check calculation snapshot has distinct objects
        $snapshot = $overtimeItem->calculation_snapshot;
        $this->assertArrayHasKey('regular_overtime', $snapshot);
        $this->assertArrayHasKey('off_holiday_overtime', $snapshot);
        $this->assertEquals(945000.0, $snapshot['regular_overtime']['total_cost']);
        $this->assertEquals(0.0, $snapshot['off_holiday_overtime']['total_cost']);
        $this->assertFalse($snapshot['off_holiday_overtime']['rate_confirmed']);
    }

    /**
     * 4. Submission payroll Security / period workflow.
     */
    public function test_submission_payroll_period_workflow(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-04', 'Agus Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        // Personalia submits period for review
        $response = $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id), [
            'notes' => 'Pengajuan payroll Security periode Agustus-September 2026.',
        ]);

        $response->assertRedirect();

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $this->period->status);
        $this->assertEquals($this->personalia->id, $this->period->submitted_by);
        $this->assertNotNull($this->period->submitted_at);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->first();
        $this->assertEquals(PayslipStatus::PENDING_REVIEW->value, $payslip->status);

        // Verify approval event logged
        $event = PayrollApprovalEvent::where('payroll_period_id', $this->period->id)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertEquals('submitted', $event->decision);
    }

    /**
     * 5. Penolakan self-approval (Segregation of Duties).
     */
    public function test_segregation_of_duties_prevents_creator_from_approving_own_submission(): void
    {
        $this->createSecurityEmployee('SEC-INT-05', 'Dedi Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        // Personalia submits
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id), [
            'notes' => 'Pengajuan draft.',
        ]);

        // Personalia tries to approve own submission -> 403 Forbidden
        $response = $this->actingAs($this->personalia)->post(route('approvals.periods.approve', $this->period->id), [
            'comment' => 'Self-approving my own payroll.',
        ]);

        $response->assertStatus(403);

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $this->period->status);
    }

    /**
     * 6. Approval oleh user berwenang yang berbeda.
     */
    public function test_authorized_approver_can_approve_period(): void
    {
        $this->createSecurityEmployee('SEC-INT-06', 'Eko Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id), [
            'notes' => 'Siap di-review.',
        ]);

        // Independent approver approves
        $response = $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), [
            'comment' => 'Disetujui, perhitungan lembur Security sesuai standar.',
        ]);

        $response->assertRedirect();

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $this->period->status);
        $this->assertEquals($this->approver->id, $this->period->approved_by);
        $this->assertNotNull($this->period->approved_at);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)->first();
        $this->assertEquals(PayslipStatus::APPROVED->value, $payslip->status);
    }

    /**
     * 7. Request changes dan pengajuan ulang.
     */
    public function test_request_changes_and_resubmission_workflow(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-07', 'Fajar Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id), [
            'notes' => 'Pengajuan pertama.',
        ]);

        // Approver requests changes
        $response = $this->actingAs($this->approver)->post(route('approvals.periods.request-changes', $this->period->id), [
            'comment' => 'Mohon cek kembali kehadiran Fajar Security.',
        ]);

        $response->assertRedirect();

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::CHANGES_REQUESTED->value, $this->period->status);
        $this->assertTrue($this->period->canBeModified());

        // Personalia modifies summary
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
            'present_days' => 25,
            'off_days' => 5,
            'sick_days' => 1,
            'regular_overtime_days' => 2,
            'regular_overtime_hours' => 16.0,
        ]);

        // Personalia re-submits
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id), [
            'notes' => 'Sudah diperbaiki, cuti sakit 1 hari sudah dicatat.',
        ]);

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $this->period->status);
    }

    /**
     * 8. Mencegah finalisasi tanpa approval.
     */
    public function test_unapproved_period_cannot_be_finalized(): void
    {
        $this->createSecurityEmployee('SEC-INT-08', 'Gunawan Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        // Period is DRAFT
        $this->expectException(DomainException::class);
        $this->finalizationService->finalizePeriod($this->period, $this->approver);
    }

    /**
     * 9. Finalisasi setelah seluruh approval terpenuhi dan menghasilkan immutable snapshot.
     */
    public function test_finalization_creates_immutable_snapshot_and_generates_pdf(): void
    {
        Storage::fake('local');

        $sec = $this->createSecurityEmployee('SEC-INT-09', 'Hadi Security');
        $this->securityService->calculateForEmployee($sec, $this->period, $this->config, [
            'present_days' => 26,
            'off_days' => 5,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
        ]);
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        // Submit & approve
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();

        // Finalize period
        $finalizedPeriod = $this->finalizationService->finalizePeriod($this->period, $this->admin);

        $this->assertEquals(PayrollPeriodStatus::FINALIZED->value, $finalizedPeriod->status);
        $this->assertEquals($this->admin->id, $finalizedPeriod->finalized_by);
        $this->assertFalse($finalizedPeriod->canBeModified());

        $payslip = Payslip::where('payroll_period_id', $this->period->id)->first();
        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);
        $this->assertFalse($payslip->canBeModified());

        // Check snapshot values are populated
        $this->assertEquals('Hadi Security', $payslip->employee_name_snapshot);
        $this->assertEquals('SEC-INT-09', $payslip->employee_number_snapshot);
        $this->assertEquals('Security', $payslip->position_name_snapshot);
        $this->assertNotNull($payslip->pdf_path);
        $this->assertNotNull($payslip->pdf_sha256);

        // Verify PDF was written to storage
        Storage::disk('local')->assertExists($payslip->pdf_path);
    }

    /**
     * 10. Mencegah perubahan data pada payroll finalized.
     */
    public function test_finalized_payroll_cannot_be_modified(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-10', 'Irfan Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();
        $this->finalizationService->finalizePeriod($this->period, $this->admin);

        // Attempt to update summary on finalized period -> DomainException
        $this->expectException(DomainException::class);
        $this->actingAs($this->personalia)
            ->withoutExceptionHandling()
            ->post(route('payroll-security.summary.update'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $sec->id,
                'present_days' => 20,
            ]);
    }

    /**
     * 11. Mencegah finalisasi ganda (Idempotent finalization).
     */
    public function test_prevents_duplicate_finalization(): void
    {
        $this->createSecurityEmployee('SEC-INT-11', 'Joko Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();

        // First finalization
        $p1 = $this->finalizationService->finalizePeriod($this->period, $this->admin);
        $this->assertEquals(PayrollPeriodStatus::FINALIZED->value, $p1->status);

        // Second finalization call should return safely without error or double transactions
        $p2 = $this->finalizationService->finalizePeriod($this->period, $this->admin);
        $this->assertEquals(PayrollPeriodStatus::FINALIZED->value, $p2->status);
        $this->assertEquals($p1->id, $p2->id);
    }

    /**
     * 12. Slip gaji menggunakan data snapshot dan bukan data master yang berubah di kemudian hari.
     */
    public function test_payslip_uses_snapshot_data_and_protects_against_future_master_changes(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-12', 'Kurniawan Security', 3500000);
        $this->securityService->calculateForEmployee($sec, $this->period, $this->config, [
            'present_days' => 26,
            'off_days' => 5,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
        ]);
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();
        $this->finalizationService->finalizePeriod($this->period, $this->admin);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->first();
        $this->assertEquals('Kurniawan Security', $payslip->employee_name_snapshot);
        $this->assertEquals('4945000.00', $payslip->net_salary);

        // Change master employee data dramatically
        $sec->update([
            'full_name' => 'Kurniawan Telah Diubah Namanya',
            'gaji_pokok' => 10000000,
        ]);

        // Change configuration rate
        $this->config->update([
            'hourly_rate' => 50000,
        ]);

        // Reload payslip
        $payslip->refresh();

        // Snapshot remains preserved
        $this->assertEquals('Kurniawan Security', $payslip->employee_name_snapshot);
        $this->assertEquals('4945000.00', $payslip->net_salary);

        $overtimeItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertEquals('945000.00', $overtimeItem->amount);
    }

    /**
     * 13. PDF Slip Gaji renders correctly with expiring signed URL.
     */
    public function test_pdf_slip_gaji_generation_and_expiring_signed_url(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-13', 'Lukman Security');
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();
        $this->finalizationService->finalizePeriod($this->period, $this->admin);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $sec->id)->first();

        // Generate PDF and verify checksum
        $pdfService = app(PayslipPdfService::class);
        $pdfContent = $pdfService->getPdfContent($payslip);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF-', $pdfContent);
        $this->assertTrue($pdfService->verifyHash($payslip));

        // Expiring signed URL
        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'payslips.signed-pdf',
            now()->addMinutes(15),
            ['payslip' => $payslip->id]
        );
        $this->assertStringContainsString('signature=', $signedUrl);
        $this->assertStringContainsString('expires=', $signedUrl);

        // Verify signed URL can be accessed to download PDF
        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * 14. RBAC Authorization: Auditor is read-only and cannot mutate.
     */
    public function test_auditor_has_read_only_access_and_cannot_mutate_security_payroll(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-14', 'Maman Security');

        // Auditor cannot update summary
        $response = $this->actingAs($this->auditor)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
            'present_days' => 25,
        ]);
        $response->assertStatus(403);

        // Auditor cannot submit period
        $submitRes = $this->actingAs($this->auditor)->post(route('approvals.periods.submit', $this->period->id));
        $submitRes->assertStatus(403);

        // Auditor cannot approve period
        $approveRes = $this->actingAs($this->auditor)->post(route('approvals.periods.approve', $this->period->id));
        $approveRes->assertStatus(403);

        // Auditor CAN view index
        $viewRes = $this->actingAs($this->auditor)->get(route('payroll-security.index', ['period_id' => $this->period->id]));
        $viewRes->assertStatus(200);
    }

    /**
     * 15. Audit trail logged across entire Security payroll lifecycle.
     */
    public function test_audit_trail_logging_across_lifecycle(): void
    {
        $sec = $this->createSecurityEmployee('SEC-INT-15', 'Nanang Security');

        // Sync via endpoint
        $this->actingAs($this->personalia)->post(route('payroll-security.periods.sync', $this->period->id));

        $syncLog = AuditLog::where('action', 'security_payroll.synced')->latest('id')->first();
        $this->assertNotNull($syncLog);
        $this->assertEquals($this->period->id, $syncLog->subject_id);

        // Generate payslips
        $this->actingAs($this->personalia)->post(route('payroll-periods.generate-payroll', $this->period->id));

        $genLog = AuditLog::where('action', 'payroll_period.auto_generated')->latest('id')->first();
        $this->assertNotNull($genLog);

        // Submit period
        $this->actingAs($this->personalia)->post(route('approvals.periods.submit', $this->period->id));
        $this->actingAs($this->approver)->post(route('approvals.periods.approve', $this->period->id), ['comment' => 'Acc']);
        $this->period->refresh();

        // Finalize period
        $this->actingAs($this->personalia)->post(route('payroll-periods.finalize', $this->period->id));

        $finalizeLog = AuditLog::where('action', 'payroll_period.finalized')->latest('id')->first();
        $this->assertNotNull($finalizeLog);
    }

    /**
     * 16. Regular employees remain 100% unaltered when Security calculations run.
     */
    public function test_regular_employees_remain_unaffected_by_security_payroll_sync(): void
    {
        $regularEmp = $this->createRegularEmployee('REG-INT-01', 'Budi Staff Reguler', 4000000);
        $secEmp = $this->createSecurityEmployee('SEC-INT-16', 'Oki Security', 3500000);

        // Generate payroll
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $regSlip = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $regularEmp->id)->first();
        $secSlip = Payslip::where('payroll_period_id', $this->period->id)->where('employee_id', $secEmp->id)->first();

        $this->assertNotNull($regSlip);
        $this->assertNotNull($secSlip);

        // Regular employee has NO security overtime summary
        $this->assertFalse(SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)->where('employee_id', $regularEmp->id)->exists());

        // Regular employee does not have security overtime note
        $regOvertime = $regSlip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNull($regOvertime); // No overtime entries logged for regular employee

        // Regular employee net salary is basic salary + jabatan = 4.500.000 (plus meal/transport allowance based on working days)
        $this->assertGreaterThanOrEqual(4500000.0, (float) $regSlip->net_salary);
    }
}
