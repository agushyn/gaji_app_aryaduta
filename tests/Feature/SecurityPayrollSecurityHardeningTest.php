<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\Office;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use App\Services\Security\SecurityPayrollExportService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityPayrollSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected Office $office;

    protected Position $securityPosition;

    protected PayrollPeriod $period;

    protected Employee $employee;

    protected SecurityPayrollCalculationService $securityService;

    protected PayslipService $payslipService;

    protected PayslipFinalizationService $finalizationService;

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

        $this->office = Office::first();
        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security', 'payroll_type' => 'security', 'is_active' => true]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        $this->securityService = app(SecurityPayrollCalculationService::class);
        $this->payslipService = app(PayslipService::class);
        $this->finalizationService = app(PayslipFinalizationService::class);

        $this->period = PayrollPeriod::create([
            'period_code' => 'SEC-SEC-01',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'cutoff_date' => '2026-08-25',
            'payment_date' => '2026-08-31',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $this->employee = Employee::create([
            'employee_number' => 'SEC-SEC-001',
            'nik' => '3201000000000099',
            'full_name' => 'Supriyadi',
            'employment_status' => 'active',
            'hire_date' => '2024-01-01',
            'position_id' => $this->securityPosition->id,
            'office_id' => $this->office->id,
            'department' => 'Security',
            'gaji_pokok' => 4500000,
        ]);
    }

    /** 1. Unauthenticated users cannot access security routes */
    public function test_unauthenticated_user_cannot_access_security_routes(): void
    {
        $this->get(route('payroll-security.index'))->assertRedirect(route('login'));
        $this->get(route('payroll-security.reports.index'))->assertRedirect(route('login'));
        $this->post(route('payroll-security.summary.update'), [])->assertRedirect(route('login'));
        $this->post(route('payroll-security.attendance.update'), [])->assertRedirect(route('login'));
        $this->post(route('payroll-security.periods.sync', $this->period->id))->assertRedirect(route('login'));
        $this->post(route('payroll-security.reconciliation.run'), [])->assertRedirect(route('login'));
    }

    /** 2. Unauthorized user without security permissions gets 403 */
    public function test_unauthorized_user_cannot_access_security_dashboard_or_reports(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('payroll-security.index'))
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->get(route('payroll-security.reports.index'))
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->post(route('payroll-security.summary.update'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $this->employee->id,
                'present_days' => 20,
            ])
            ->assertStatus(403);
    }

    /** 3. Auditor has strictly read-only access (403 on mutation endpoints) */
    public function test_auditor_has_strictly_read_only_access(): void
    {
        // Allowed: view reports, export, run reconciliation
        $this->actingAs($this->auditor)
            ->get(route('payroll-security.reports.index', ['period_id' => $this->period->id]))
            ->assertStatus(200);

        $this->actingAs($this->auditor)
            ->get(route('payroll-security.reports.export', [
                'type' => 'period-summary',
                'period_id' => $this->period->id,
            ]))
            ->assertStatus(200);

        $this->actingAs($this->auditor)
            ->post(route('payroll-security.reconciliation.run'), [
                'period_id' => $this->period->id,
            ])
            ->assertStatus(302); // Redirect back with results

        // Forbidden: update attendance, update summary, bulk update, sync
        $this->actingAs($this->auditor)
            ->post(route('payroll-security.summary.update'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $this->employee->id,
                'present_days' => 20,
            ])
            ->assertStatus(403);

        $this->actingAs($this->auditor)
            ->post(route('payroll-security.attendance.update'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $this->employee->id,
                'date' => '2026-08-01',
                'status' => 'present',
            ])
            ->assertStatus(403);

        $this->actingAs($this->auditor)
            ->post(route('payroll-security.periods.sync', $this->period->id))
            ->assertStatus(403);
    }

    /** 4. User without config permission cannot update formula config */
    public function test_user_without_config_permission_cannot_update_config(): void
    {
        $this->actingAs($this->auditor)
            ->post(route('payroll-security.config.update'), [
                'hourly_rate' => 20000,
                'first_hours_limit' => 5,
                'first_hours_multiplier' => 1.5,
                'subsequent_hours_multiplier' => 2.0,
                'round_rule' => 'round',
            ])
            ->assertStatus(403);
    }

    /** 5. Input validation rejects negative days and hours */
    public function test_input_validation_rejects_negative_days_and_hours(): void
    {
        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => -5,
            'regular_overtime_hours' => -10,
        ]);

        $response->assertSessionHasErrors(['present_days', 'regular_overtime_hours']);
    }

    /** 6. Input validation rejects decimal on integer day fields */
    public function test_input_validation_rejects_decimal_days(): void
    {
        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 20.5,
            'off_days' => 4.2,
        ]);

        $response->assertSessionHasErrors(['present_days', 'off_days']);
    }

    /** 7. Input validation rejects days exceeding period calendar days */
    public function test_input_validation_rejects_days_exceeding_period(): void
    {
        // Period has 31 days. Requesting 35 present days should be rejected.
        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 35,
            'off_days' => 0,
        ]);

        $response->assertSessionHasErrors(['present_days']);
    }

    /** 8. Tampering prevention: Computed fields injected into request are ignored */
    public function test_calculated_fields_cannot_be_tampered_from_request(): void
    {
        // Attacker attempts to forge high attendance_hours and overtime cost in request payload
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 20,
            'off_days' => 5,
            'attendance_hours' => 9999.00, // Injected forgery
            'regular_overtime_cost' => 99999999.00, // Injected forgery
            'total_overtime_cost' => 99999999.00, // Injected forgery
        ]);

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        // System must calculate actual attendance hours (20 * 8 = 160), not forged 9999
        $this->assertEquals(160.0, (float) $summary->attendance_hours);
        $this->assertNotEquals(99999999.00, (float) $summary->total_overtime_cost);
    }

    /** 9. Cannot modify payroll in review or approved state */
    public function test_cannot_modify_payroll_in_review_status(): void
    {
        $this->period->update(['status' => PayrollPeriodStatus::IN_REVIEW->value]);

        $this->expectException(DomainException::class);
        $this->withoutExceptionHandling()->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 20,
        ]);
    }

    /** 10. Cannot modify finalized payroll period */
    public function test_cannot_modify_finalized_payroll_period(): void
    {
        $this->period->update(['status' => PayrollPeriodStatus::FINALIZED->value]);

        $this->expectException(DomainException::class);
        $this->withoutExceptionHandling()->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 20,
        ]);
    }

    /** 11. Idempotent payroll sync does not duplicate components */
    public function test_idempotent_payroll_sync_does_not_duplicate_components(): void
    {
        // 1. Setup summary with overtime
        $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'present_days' => 25,
            'off_days' => 6,
            'regular_overtime_hours' => 10.0,
        ]);

        // 2. Generate base payslip
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        // 3. Run sync 3 times in a row
        $this->actingAs($this->personalia)->post(route('payroll-security.periods.sync', $this->period->id));
        $this->actingAs($this->personalia)->post(route('payroll-security.periods.sync', $this->period->id));
        $this->actingAs($this->personalia)->post(route('payroll-security.periods.sync', $this->period->id));

        $payslip = Payslip::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        // Must only have exactly 1 OVERTIME item
        $otCount = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->count();
        $this->assertEquals(1, $otCount);
    }

    /** 12. Expired or tampered signed PDF URL is rejected */
    public function test_expired_or_tampered_signed_pdf_url_is_rejected(): void
    {
        $payslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'slip_number' => 'SLIP-SEC-SIGN',
            'employee_number_snapshot' => $this->employee->employee_number,
            'employee_name_snapshot' => $this->employee->full_name,
            'status' => PayslipStatus::FINALIZED->value,
            'created_by' => $this->personalia->id,
        ]);

        // Tampered URL
        $validSignedUrl = URL::temporarySignedRoute(
            'payslips.download',
            now()->addMinutes(30),
            ['payslip' => $payslip->id]
        );
        $tamperedUrl = $validSignedUrl.'tampered';

        $response = $this->get($tamperedUrl);
        $this->assertTrue($response->status() === 403 || $response->status() === 401 || $response->isRedirect());
    }

    /** 13. CSV Formula Injection (CWE-1236) defense across all payloads */
    public function test_cwe_1236_formula_injection_defense(): void
    {
        $maliciousInputs = [
            '=cmd|"/C calc"!A0',
            '+cmd|"/C notepad"!A0',
            '-2+3+cmd|"/C calc"!A0',
            '@SUM(1+1)*cmd|"/C calc"!A0',
            "\tHYPERLINK(\"http://attacker.com\")",
            "\r=2+5",
        ];

        foreach ($maliciousInputs as $bad) {
            $cleaned = SecurityPayrollExportService::sanitizeCell($bad);
            $this->assertStringStartsWith("'", $cleaned, "Input [{$bad}] must start with single quote");
        }

        // Numbers with legitimate plus or minus must NOT be quoted
        $this->assertSame('-500000', SecurityPayrollExportService::sanitizeCell('-500000'));
        $this->assertSame('+250000', SecurityPayrollExportService::sanitizeCell('+250000'));
        $this->assertSame('15.5', SecurityPayrollExportService::sanitizeCell('15.5'));
    }
}
