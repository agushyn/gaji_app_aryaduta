<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayComponentTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_pay_components(): void
    {
        $this->get('/pay-components')->assertRedirect('/login');
        $this->post('/pay-components', [])->assertRedirect('/login');
    }

    public function test_personalia_can_view_and_create_pay_component(): void
    {
        $response = $this->actingAs($this->personalia)->get('/pay-components');
        $response->assertOk();

        $payload = [
            'code' => 'TUNJANGAN_KHUSUS',
            'name' => 'Tunjangan Khusus Lapangan',
            'type' => 'earning',
            'sort_order' => 25,
            'is_active' => true,
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/pay-components', $payload);
        $postResponse->assertRedirect('/pay-components');
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('pay_components', [
            'code' => 'TUNJANGAN_KHUSUS',
            'name' => 'Tunjangan Khusus Lapangan',
            'type' => 'earning',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pay_component.created',
            'user_id' => $this->personalia->id,
        ]);
    }

    public function test_duplicate_component_code_is_rejected(): void
    {
        PayComponent::create([
            'code' => 'KOMPONEN_ADA',
            'name' => 'Komponen Ada',
            'type' => 'earning',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->post('/pay-components', [
            'code' => 'KOMPONEN_ADA',
            'name' => 'Komponen Duplikat',
            'type' => 'earning',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_personalia_can_toggle_component_active_status(): void
    {
        $component = PayComponent::create([
            'code' => 'BONUS_TAHUNAN',
            'name' => 'Bonus Tahunan',
            'type' => 'earning',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->patch("/pay-components/{$component->id}/toggle-status");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse($component->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pay_component.status_toggled',
            'user_id' => $this->personalia->id,
        ]);
    }

    public function test_component_used_in_payslip_cannot_be_deleted_physically(): void
    {
        $component = PayComponent::create([
            'code' => 'GAPOK_TERPAKAI',
            'name' => 'Gaji Pokok Terpakai',
            'type' => 'earning',
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'period_code' => '2026-COMP-TEST',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $employee = Employee::create([
            'employee_number' => 'EMP-COMP-01',
            'full_name' => 'Karyawan Komponen',
            'employment_status' => 'active',
        ]);

        $payslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'slip_number' => 'SLIP-COMP-001',
            'employee_number_snapshot' => $employee->employee_number,
            'employee_name_snapshot' => $employee->full_name,
            'created_by' => $this->admin->id,
        ]);

        // Simulasikan pemakaian komponen dalam tabel payslip_items
        $payslip->items()->create([
            'pay_component_id' => $component->id,
            'item_code_snapshot' => $component->code,
            'description_snapshot' => $component->name,
            'item_type' => 'earning',
            'amount' => 5000000,
        ]);

        $response = $this->actingAs($this->personalia)->delete("/pay-components/{$component->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Komponen masih ada di database
        $this->assertDatabaseHas('pay_components', ['id' => $component->id]);
    }

    public function test_auditor_has_read_only_access_to_pay_components(): void
    {
        $this->actingAs($this->auditor)->get('/pay-components')->assertOk();

        // Tidak boleh membuat
        $this->actingAs($this->auditor)->post('/pay-components', [
            'code' => 'KOMPONEN_AUDITOR',
            'name' => 'Test',
            'type' => 'earning',
        ])->assertStatus(403);
    }
}
