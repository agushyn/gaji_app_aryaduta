<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\User;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPayrollInputUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $personalia;

    protected User $auditor;

    protected PayrollPeriod $period;

    protected Position $securityPosition;

    protected Employee $securityEmployee1;

    protected Employee $securityEmployee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(OrganizationMasterSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);
        $this->seed(SecurityPayrollConfigSeeder::class);

        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            [
                'name' => 'Security',
                'payroll_type' => 'security',
                'is_active' => true,
            ]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        // 31-day payroll period (August 22 to September 21, 2026)
        $this->period = PayrollPeriod::create([
            'period_code' => '2026-08-SEC',
            'start_date' => '2026-08-22',
            'end_date' => '2026-09-21',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $this->securityEmployee1 = Employee::create([
            'employee_number' => 'SEC-001',
            'full_name' => 'Wawan Security',
            'position_id' => $this->securityPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => 3500000,
        ]);

        $this->securityEmployee2 = Employee::create([
            'employee_number' => 'SEC-002',
            'full_name' => 'Supardi Security',
            'position_id' => $this->securityPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => 3500000,
        ]);
    }

    /**
     * Test render table for security payroll page.
     */
    public function test_can_render_security_payroll_table_with_breakdown_props(): void
    {
        $response = $this->actingAs($this->personalia)
            ->get(route('payroll-security.index', ['period_id' => $this->period->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SecurityPayroll/Index')
            ->has('periods')
            ->has('currentPeriod')
            ->has('securityEmployees', 2)
            ->has('summaries')
        );
    }

    /**
     * Test update single row with all attendance and overtime categories.
     */
    public function test_can_save_attendance_and_overtime_breakdown_for_single_security(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => 21,
            'off_days' => 5,
            'sick_days' => 1,
            'permission_days' => 1,
            'leave_days' => 1,
            'absent_days' => 2,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'off_holiday_overtime_days' => 2,
            'off_holiday_overtime_hours' => 16.5,
        ];

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-security.summary.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->securityEmployee1->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(21, $summary->present_days);
        $this->assertEquals(5, $summary->off_days);
        $this->assertEquals(1, $summary->sick_days);
        $this->assertEquals(1, $summary->permission_days);
        $this->assertEquals(1, $summary->leave_days);
        $this->assertEquals(2, $summary->absent_days);
        // attendance_hours = 21 * 8 = 168.00
        $this->assertEquals('168.00', (string) $summary->attendance_hours);
        $this->assertEquals(5, $summary->regular_overtime_days);
        $this->assertEquals('40.00', (string) $summary->regular_overtime_hours);
        $this->assertEquals(2, $summary->off_holiday_overtime_days);
        $this->assertEquals('16.50', (string) $summary->off_holiday_overtime_hours);
        // Combined gross overtime = 40 + 16.5 = 56.50
        $this->assertEquals('56.50', (string) $summary->gross_overtime_hours);
        // Backward-compatible fields
        $this->assertEquals(21, $summary->total_work_days);
        $this->assertEquals(5, $summary->total_off_days);
        $this->assertEquals(5, $summary->total_absent_days); // 1 + 1 + 1 + 2
    }

    /**
     * Test verification that attendance_hours cannot be manipulated from frontend.
     */
    public function test_attendance_hours_is_always_calculated_by_backend_and_ignores_client_tampering(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => 20,
            'off_days' => 5,
            'attendance_hours' => 999.00, // Attempted tamper from client
            'regular_overtime_days' => 0,
            'regular_overtime_hours' => 0.0,
        ];

        $this->actingAs($this->personalia)
            ->post(route('payroll-security.summary.update'), $payload);

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->securityEmployee1->id)
            ->first();

        // Must be exactly 20 * 8 = 160.00, NOT 999.00
        $this->assertEquals('160.00', (string) $summary->attendance_hours);
        $this->assertEquals('160.00', (string) $summary->total_work_hours);
    }

    /**
     * Test validation rejects negative numbers.
     */
    public function test_rejects_negative_days_and_hours(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => -1,
            'regular_overtime_hours' => -5.5,
        ];

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-security.summary.update'), $payload);

        $response->assertSessionHasErrors(['present_days', 'regular_overtime_hours']);
    }

    /**
     * Test validation rejects total days exceeding period calendar days.
     */
    public function test_rejects_total_days_exceeding_period_days(): void
    {
        // Period is 31 days. Sending 32 days total:
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => 25,
            'off_days' => 5,
            'sick_days' => 3, // 25 + 5 + 3 = 33 days > 31 days
        ];

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-security.summary.update'), $payload);

        $response->assertSessionHasErrors('present_days');
    }

    /**
     * Test bulk update for all security employees in one transaction.
     */
    public function test_bulk_update_saves_multiple_security_records_in_single_transaction(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'items' => [
                [
                    'employee_id' => $this->securityEmployee1->id,
                    'present_days' => 20,
                    'off_days' => 5,
                    'sick_days' => 0,
                    'permission_days' => 0,
                    'leave_days' => 0,
                    'absent_days' => 0,
                    'regular_overtime_days' => 5,
                    'regular_overtime_hours' => 40.0,
                    'off_holiday_overtime_days' => 0,
                    'off_holiday_overtime_hours' => 0.0,
                ],
                [
                    'employee_id' => $this->securityEmployee2->id,
                    'present_days' => 22,
                    'off_days' => 5,
                    'sick_days' => 1,
                    'permission_days' => 0,
                    'leave_days' => 0,
                    'absent_days' => 0,
                    'regular_overtime_days' => 3,
                    'regular_overtime_hours' => 24.5,
                    'off_holiday_overtime_days' => 1,
                    'off_holiday_overtime_hours' => 8.0,
                ],
            ],
        ];

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-security.bulk-update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $s1 = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->securityEmployee1->id)
            ->first();
        $this->assertEquals(20, $s1->present_days);
        $this->assertEquals('160.00', (string) $s1->attendance_hours);
        $this->assertEquals('40.00', (string) $s1->regular_overtime_hours);

        $s2 = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->securityEmployee2->id)
            ->first();
        $this->assertEquals(22, $s2->present_days);
        $this->assertEquals('176.00', (string) $s2->attendance_hours);
        $this->assertEquals('24.50', (string) $s2->regular_overtime_hours);
        $this->assertEquals('8.00', (string) $s2->off_holiday_overtime_hours);
        $this->assertEquals('32.50', (string) $s2->gross_overtime_hours);
    }

    /**
     * Test permission enforcement: Auditor cannot update or bulk update.
     */
    public function test_user_without_permission_cannot_update_security_payroll(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => 20,
        ];

        $response = $this->actingAs($this->auditor)
            ->post(route('payroll-security.summary.update'), $payload);
        $response->assertForbidden();

        $bulkPayload = [
            'payroll_period_id' => $this->period->id,
            'items' => [
                ['employee_id' => $this->securityEmployee1->id, 'present_days' => 20],
            ],
        ];
        $responseBulk = $this->actingAs($this->auditor)
            ->post(route('payroll-security.bulk-update'), $bulkPayload);
        $responseBulk->assertForbidden();
    }

    /**
     * Test locked period enforcement: Finalized period cannot be modified.
     */
    public function test_finalized_period_cannot_be_updated(): void
    {
        $this->period->update(['status' => PayrollPeriodStatus::FINALIZED->value]);

        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->securityEmployee1->id,
            'present_days' => 20,
        ];

        $this->expectException(\DomainException::class);
        $this->actingAs($this->personalia)
            ->withoutExceptionHandling()
            ->post(route('payroll-security.summary.update'), $payload);
    }
}

