<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayrollApprovalService;
use App\Services\PayrollSubmissionService;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use Database\Seeders\RbacSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected Employee $employee;

    protected PayComponent $basicSalaryComp;

    protected PayComponent $transportComp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->employee = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff Lapangan',
            'email' => 'budi.santoso@example.com',
            'phone' => '628123456789',
            'employment_status' => 'active',
        ]);

        $this->basicSalaryComp = PayComponent::create([
            'code' => 'BASIC_SALARY',
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->transportComp = PayComponent::create([
            'code' => 'TRANSPORT',
            'name' => 'Tunjangan Transport',
            'type' => 'earning',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    protected function createPeriodWithPayslip(string $periodCode = '2026-10', string $status = 'draft'): array
    {
        $period = PayrollPeriod::create([
            'period_code' => $periodCode,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        /** @var PayslipService $payslipService */
        $payslipService = app(PayslipService::class);
        $payslip = $payslipService->createPayslip($period, [
            'employee_id' => $this->employee->id,
            'created_by' => $this->personalia->id,
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'amount' => 5000000,
                ],
                [
                    'pay_component_id' => $this->transportComp->id,
                    'amount' => 500000,
                ],
            ],
        ]);

        if ($status !== 'draft') {
            $period->update(['status' => $status]);
            $payslip->update(['status' => $status === 'in_review' ? PayslipStatus::PENDING_REVIEW->value : $status]);
        }

        return [$period->fresh(), $payslip->fresh()];
    }

    public function test_unauthenticated_user_cannot_access_approval_routes(): void
    {
        $response = $this->get('/approvals');
        $response->assertRedirect('/login');
    }

    public function test_user_without_approval_permission_cannot_access_approval_routes(): void
    {
        $unauthorizedUser = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($unauthorizedUser)->get('/approvals');
        $response->assertStatus(403);
    }

    public function test_personalia_and_approver_can_view_approval_index_and_details(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'in_review');

        // Approver can view approvals index
        $resApprover = $this->actingAs($this->approver)->get('/approvals');
        $resApprover->assertOk();

        // Personalia can view approvals index
        $resPersonalia = $this->actingAs($this->personalia)->get('/approvals');
        $resPersonalia->assertOk();

        // Approver can view period review details
        $resDetail = $this->actingAs($this->approver)->get(route('approvals.periods.show', $period->id));
        $resDetail->assertOk();
    }

    public function test_submitting_empty_period_is_rejected(): void
    {
        $emptyPeriod = PayrollPeriod::create([
            'period_code' => '2026-11',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $this->actingAs($this->personalia);

        $response = $this->from(route('payroll-periods.show', $emptyPeriod->id))
            ->post(route('approvals.periods.submit', $emptyPeriod->id), [
                'notes' => 'Coba ajukan periode kosong',
            ]);

        // Policy denies submitting empty period (canBeSubmitted is false)
        $response->assertStatus(403);
        $this->assertEquals('draft', $emptyPeriod->fresh()->status);
    }

    public function test_personalia_can_submit_period_with_slips_for_review(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        $this->actingAs($this->personalia);

        $response = $this->from(route('payroll-periods.show', $period->id))
            ->post(route('approvals.periods.submit', $period->id), [
                'notes' => 'Mohon ditinjau untuk persetujuan gaji Oktober 2026.',
            ]);

        $response->assertRedirect(route('payroll-periods.show', $period->id));
        $response->assertSessionHas('success');

        $period->refresh();
        $payslip->refresh();

        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->status);
        $this->assertEquals($this->personalia->id, $period->submitted_by);
        $this->assertNotNull($period->submitted_at);

        $this->assertEquals(PayslipStatus::PENDING_REVIEW->value, $payslip->status);
        $this->assertEquals($this->personalia->id, $payslip->submitted_by);
        $this->assertNotNull($payslip->submitted_at);

        // Check approval event recorded
        $event = PayrollApprovalEvent::where('payroll_period_id', $period->id)
            ->where('decision', 'submitted')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($this->personalia->id, $event->actor_user_id);
        $this->assertEquals('Mohon ditinjau untuk persetujuan gaji Oktober 2026.', $event->comment);

        // Check audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_period.submitted',
            'user_id' => $this->personalia->id,
        ]);
    }

    public function test_approver_can_approve_period_under_review(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        // Personalia submits
        app(PayrollSubmissionService::class)->submitPeriod($period, $this->personalia, 'Siap ditinjau');

        // Approver approves (separate user from creator/submitter)
        $this->actingAs($this->approver);

        $response = $this->from(route('approvals.periods.show', $period->id))
            ->post(route('approvals.periods.approve', $period->id), [
                'comment' => 'Perhitungan gaji telah diperiksa dan disetujui sesuai regulasi.',
            ]);

        $response->assertRedirect(route('approvals.periods.show', $period->id));
        $response->assertSessionHas('success');

        $period->refresh();
        $payslip->refresh();

        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $period->status);
        $this->assertEquals($this->approver->id, $period->approved_by);
        $this->assertNotNull($period->approved_at);
        $this->assertEquals('Perhitungan gaji telah diperiksa dan disetujui sesuai regulasi.', $period->approval_comment);

        $this->assertEquals(PayslipStatus::APPROVED->value, $payslip->status);
        $this->assertEquals($this->approver->id, $payslip->approved_by);
        $this->assertNotNull($payslip->approved_at);

        // Verify approval event
        $this->assertDatabaseHas('payroll_approval_events', [
            'payroll_period_id' => $period->id,
            'actor_user_id' => $this->approver->id,
            'decision' => 'approved',
            'comment' => 'Perhitungan gaji telah diperiksa dan disetujui sesuai regulasi.',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_period.approved',
            'user_id' => $this->approver->id,
        ]);
    }

    public function test_approver_can_request_changes_with_mandatory_reason(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');
        app(PayrollSubmissionService::class)->submitPeriod($period, $this->personalia, 'Siap ditinjau');

        $this->actingAs($this->approver);

        $reason = 'Mohon sesuaikan nominal tunjangan transport karena ada koreksi absensi.';
        $response = $this->from(route('approvals.periods.show', $period->id))
            ->post(route('approvals.periods.request-changes', $period->id), [
                'comment' => $reason,
            ]);

        $response->assertRedirect(route('approvals.periods.show', $period->id));
        $response->assertSessionHas('success');

        $period->refresh();
        $payslip->refresh();

        $this->assertEquals(PayrollPeriodStatus::CHANGES_REQUESTED->value, $period->status);
        $this->assertEquals($reason, $period->approval_comment);
        $this->assertNull($period->approved_by);

        $this->assertEquals(PayslipStatus::CHANGES_REQUESTED->value, $payslip->status);
        $this->assertEquals($reason, $payslip->approval_comment);

        // Verify approval event
        $this->assertDatabaseHas('payroll_approval_events', [
            'payroll_period_id' => $period->id,
            'actor_user_id' => $this->approver->id,
            'decision' => 'changes_requested',
            'comment' => $reason,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_period.changes_requested',
            'user_id' => $this->approver->id,
        ]);
    }

    public function test_request_changes_fails_without_reason(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');
        app(PayrollSubmissionService::class)->submitPeriod($period, $this->personalia, 'Siap ditinjau');

        $this->actingAs($this->approver);

        // Empty reason should fail validation
        $response = $this->from(route('approvals.periods.show', $period->id))
            ->post(route('approvals.periods.request-changes', $period->id), [
                'comment' => '',
            ]);

        $response->assertSessionHasErrors(['comment']);
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->fresh()->status);
    }

    public function test_segregation_of_duties_prevents_creator_from_approving_own_submission(): void
    {
        config(['payroll.enforce_segregation_of_duties' => true]);

        // Approver user creates and submits period
        $period = PayrollPeriod::create([
            'period_code' => '2026-12',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'created_by' => $this->approver->id,
        ]);

        /** @var PayslipService $payslipService */
        $payslipService = app(PayslipService::class);
        $payslipService->createPayslip($period, [
            'employee_id' => $this->employee->id,
            'created_by' => $this->approver->id,
            'items' => [
                ['pay_component_id' => $this->basicSalaryComp->id, 'amount' => 6000000],
            ],
        ]);

        app(PayrollSubmissionService::class)->submitPeriod($period, $this->approver);

        // Approver attempts to approve their own created period
        $this->actingAs($this->approver);

        $response = $this->post(route('approvals.periods.approve', $period->id), [
            'comment' => 'Mencoba setujui buatan sendiri',
        ]);

        // Policy rejects approval due to segregation of duties (returns 403)
        $response->assertStatus(403);
        $this->assertEquals(PayrollPeriodStatus::IN_REVIEW->value, $period->fresh()->status);
    }

    public function test_modifying_payslip_after_submit_or_approval_invalidates_prior_approvals_and_reverts_to_draft(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        // Submit and Approve
        app(PayrollSubmissionService::class)->submitPeriod($period, $this->personalia, 'Submit');
        app(PayrollApprovalService::class)->approvePeriod($period, $this->approver, 'Approved');

        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $period->fresh()->status);
        $this->assertEquals(PayslipStatus::APPROVED->value, $payslip->fresh()->status);

        // Personalia modifies payslip items (e.g., changes basic salary from 5,000,000 to 5,500,000)
        $this->actingAs($this->personalia);

        $response = $this->put(route('payslips.update', $payslip->id), [
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'item_type' => 'earning',
                    'amount' => 5500000,
                ],
                [
                    'pay_component_id' => $this->transportComp->id,
                    'item_type' => 'earning',
                    'amount' => 500000,
                ],
            ],
        ]);

        $response->assertRedirect(route('payslips.show', $payslip->id));

        $payslip->refresh();
        $period->refresh();

        // 1. Payslip must be reverted to draft
        $this->assertEquals(PayslipStatus::DRAFT->value, $payslip->status);
        $this->assertNull($payslip->approved_by);
        $this->assertNull($payslip->approved_at);
        $this->assertNull($payslip->approval_comment);
        $this->assertEquals('6000000.00', $payslip->net_salary);

        // 2. Parent period must be reverted to draft as well
        $this->assertEquals(PayrollPeriodStatus::DRAFT->value, $period->status);
        $this->assertNull($period->approved_by);
        $this->assertNull($period->approved_at);
        $this->assertNull($period->approval_comment);

        // 3. Approval event with decision 'reopened' must be recorded
        $this->assertDatabaseHas('payroll_approval_events', [
            'payroll_period_id' => $period->id,
            'payslip_id' => $payslip->id,
            'decision' => 'reopened',
        ]);

        $this->assertDatabaseHas('payroll_approval_events', [
            'payroll_period_id' => $period->id,
            'payslip_id' => null,
            'decision' => 'reopened',
        ]);
    }

    public function test_unapproved_payslip_or_period_cannot_be_finalized(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        /** @var PayslipFinalizationService $finalizationService */
        $finalizationService = app(PayslipFinalizationService::class);

        // Attempt to finalize period while in draft
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Periode penggajian tidak dapat difinalisasi karena belum disetujui');

        $finalizationService->finalizePeriod($period, $this->admin);
    }

    public function test_unapproved_individual_payslip_cannot_be_finalized(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        /** @var PayslipFinalizationService $finalizationService */
        $finalizationService = app(PayslipFinalizationService::class);

        // Attempt to finalize individual slip while in draft
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Slip gaji tidak dapat difinalisasi karena belum disetujui');

        $finalizationService->finalizePayslip($payslip, $this->admin);
    }

    public function test_personalia_without_approvals_decide_cannot_approve_or_request_changes(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');
        app(PayrollSubmissionService::class)->submitPeriod($period, $this->personalia, 'Submit');

        $this->actingAs($this->personalia);

        // Personalia attempts to approve -> 403 Forbidden
        $resApprove = $this->post(route('approvals.periods.approve', $period->id), [
            'comment' => 'Mencoba approve',
        ]);
        $resApprove->assertStatus(403);

        // Personalia attempts to request changes -> 403 Forbidden
        $resChanges = $this->post(route('approvals.periods.request-changes', $period->id), [
            'comment' => 'Mencoba reject',
        ]);
        $resChanges->assertStatus(403);
    }

    public function test_approver_can_approve_and_request_changes_for_individual_payslip(): void
    {
        [$period, $payslip] = $this->createPeriodWithPayslip('2026-10', 'draft');

        // Submit individual payslip
        app(PayrollSubmissionService::class)->submitPayslip($payslip, $this->personalia, 'Cek slip ini');

        $this->assertEquals(PayslipStatus::PENDING_REVIEW->value, $payslip->fresh()->status);

        // Approver requests changes for this individual slip
        $this->actingAs($this->approver);

        $resReject = $this->from(route('payslips.show', $payslip->id))
            ->post(route('approvals.payslips.request-changes', $payslip->id), [
                'comment' => 'Slip ini perlu penyesuaian nominal.',
            ]);

        $resReject->assertSessionHas('success');
        $this->assertEquals(PayslipStatus::CHANGES_REQUESTED->value, $payslip->fresh()->status);
        $this->assertEquals(PayrollPeriodStatus::CHANGES_REQUESTED->value, $period->fresh()->status);

        // Personalia resubmits slip
        $this->actingAs($this->personalia);
        $payslip->refresh();
        app(PayrollSubmissionService::class)->submitPayslip($payslip, $this->personalia, 'Sudah diperbaiki');

        // Approver approves slip
        $this->actingAs($this->approver);
        $payslip->refresh();
        $resApprove = $this->post(route('approvals.payslips.approve', $payslip->id), [
            'comment' => 'Slip sah disetujui.',
        ]);

        $resApprove->assertSessionHas('success');
        $this->assertEquals(PayslipStatus::APPROVED->value, $payslip->fresh()->status);
    }
}
