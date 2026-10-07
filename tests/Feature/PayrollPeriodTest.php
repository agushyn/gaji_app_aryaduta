<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $auditor;

    protected User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();
    }

    public function test_unauthenticated_user_cannot_access_payroll_periods(): void
    {
        $this->get('/payroll-periods')->assertRedirect('/login');
        $this->post('/payroll-periods', [])->assertRedirect('/login');
    }

    public function test_personalia_can_create_and_view_payroll_period(): void
    {
        $response = $this->actingAs($this->personalia)->get('/payroll-periods');
        $response->assertOk();

        $payload = [
            'period_code' => '2026-11',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'approval_comment' => 'Periode reguler November 2026',
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/payroll-periods', $payload);

        $period = PayrollPeriod::where('period_code', '2026-11')->first();
        $this->assertNotNull($period);
        $postResponse->assertRedirect("/payroll-periods/{$period->id}");

        $this->assertDatabaseHas('payroll_periods', [
            'period_code' => '2026-11',
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_period.created',
            'user_id' => $this->personalia->id,
        ]);
    }

    public function test_duplicate_period_code_is_rejected(): void
    {
        PayrollPeriod::create([
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->personalia)->post('/payroll-periods', [
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertSessionHasErrors('period_code');
    }

    public function test_auditor_has_read_only_access_to_payroll_periods(): void
    {
        $period = PayrollPeriod::create([
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->auditor)->get('/payroll-periods')->assertOk();
        $this->actingAs($this->auditor)->get("/payroll-periods/{$period->id}")->assertOk();

        // Tidak boleh membuat
        $this->actingAs($this->auditor)->post('/payroll-periods', [
            'period_code' => '2026-12',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
        ])->assertStatus(403);
    }
}
