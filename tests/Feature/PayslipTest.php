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

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $auditor;

    protected User $approver;

    protected PayrollPeriod $period;

    protected Employee $employee;

    protected PayComponent $basicSalaryComp;

    protected PayComponent $mealComp;

    protected PayComponent $bpjsComp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->employee = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Keuangan',
            'position' => 'Staff Finance',
            'email' => 'budi@example.com',
            'phone' => '628123456789',
            'employment_status' => 'active',
        ]);

        $this->basicSalaryComp = PayComponent::create([
            'code' => 'BASIC_SALARY',
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->mealComp = PayComponent::create([
            'code' => 'MEAL_ALLOWANCE',
            'name' => 'Tunjangan Makan',
            'type' => 'earning',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->bpjsComp = PayComponent::create([
            'code' => 'BPJS_TK',
            'name' => 'BPJS Ketenagakerjaan',
            'type' => 'deduction',
            'sort_order' => 100,
            'is_active' => true,
        ]);
    }

    public function test_personalia_can_create_payslip_with_dynamic_items(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'item_code_snapshot' => $this->basicSalaryComp->code,
                    'description_snapshot' => $this->basicSalaryComp->name,
                    'item_type' => 'earning',
                    'amount' => 5000000,
                ],
                [
                    'pay_component_id' => $this->mealComp->id,
                    'item_code_snapshot' => $this->mealComp->code,
                    'description_snapshot' => $this->mealComp->name,
                    'item_type' => 'earning',
                    'amount' => 1000000,
                ],
                [
                    'pay_component_id' => $this->bpjsComp->id,
                    'item_code_snapshot' => $this->bpjsComp->code,
                    'description_snapshot' => $this->bpjsComp->name,
                    'item_type' => 'deduction',
                    'amount' => 150000,
                ],
            ],
        ];

        $response = $this->actingAs($this->personalia)->post('/payslips', $payload);

        $response->assertRedirect("/payroll-periods/{$this->period->id}");
        $response->assertSessionHas('success');

        // Pastikan payslip tersimpan dengan snapshot dan total akurat
        // Total earnings = 5,000,000 + 1,000,000 = 6,000,000
        // Total deductions = 150,000
        // Net salary = 5,850,000
        // Basic salary = 5,000,000 (tidak double counted)
        $payslip = Payslip::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($payslip);
        $this->assertEquals('5000000.00', $payslip->basic_salary);
        $this->assertEquals('6000000.00', $payslip->total_earnings);
        $this->assertEquals('150000.00', $payslip->total_deductions);
        $this->assertEquals('5850000.00', $payslip->net_salary);
        $this->assertEquals('Budi Santoso', $payslip->employee_name_snapshot);
        $this->assertEquals('EMP-001', $payslip->employee_number_snapshot);

        // Pastikan items tersimpan
        $this->assertCount(3, $payslip->items);

        // Pastikan audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payslip.created',
            'user_id' => $this->personalia->id,
            'subject_id' => (string) $payslip->id,
        ]);
    }

    public function test_duplicate_payslip_for_same_employee_in_same_period_is_rejected(): void
    {
        // Buat slip pertama
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'item_code_snapshot' => $this->basicSalaryComp->code,
                    'description_snapshot' => $this->basicSalaryComp->name,
                    'item_type' => 'earning',
                    'amount' => 5000000,
                ],
            ],
        ];

        $this->actingAs($this->personalia)->post('/payslips', $payload)->assertSessionHasNoErrors();

        // Coba buat slip kedua untuk karyawan yang sama pada periode yang sama
        $response = $this->actingAs($this->personalia)->post('/payslips', $payload);

        $response->assertSessionHasErrors('employee_id');
        $this->assertEquals(1, Payslip::where('payroll_period_id', $this->period->id)->count());
    }

    public function test_payslip_creation_fails_with_negative_amount(): void
    {
        $payload = [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'item_code_snapshot' => $this->basicSalaryComp->code,
                    'description_snapshot' => $this->basicSalaryComp->name,
                    'item_type' => 'earning',
                    'amount' => -500000,
                ],
            ],
        ];

        $response = $this->actingAs($this->personalia)->post('/payslips', $payload);
        $response->assertSessionHasErrors('items.0.amount');
    }

    public function test_personalia_can_update_draft_payslip_and_totals_are_recalculated(): void
    {
        // Buat slip awal
        $payslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'slip_number' => 'SLIP/2026-10/EMP-001',
            'employee_number_snapshot' => 'EMP-001',
            'employee_name_snapshot' => 'Budi Santoso',
            'basic_salary' => 5000000,
            'total_earnings' => 5000000,
            'total_deductions' => 0,
            'net_salary' => 5000000,
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $payslip->items()->create([
            'pay_component_id' => $this->basicSalaryComp->id,
            'item_code_snapshot' => $this->basicSalaryComp->code,
            'description_snapshot' => $this->basicSalaryComp->name,
            'item_type' => 'earning',
            'amount' => 5000000,
            'sort_order' => 1,
        ]);

        // Update: tambahkan bonus 1.500.000 dan potongan BPJS 200.000
        $updatePayload = [
            'items' => [
                [
                    'pay_component_id' => $this->basicSalaryComp->id,
                    'item_code_snapshot' => $this->basicSalaryComp->code,
                    'description_snapshot' => $this->basicSalaryComp->name,
                    'item_type' => 'earning',
                    'amount' => 5000000,
                ],
                [
                    'pay_component_id' => $this->mealComp->id,
                    'item_code_snapshot' => $this->mealComp->code,
                    'description_snapshot' => $this->mealComp->name,
                    'item_type' => 'earning',
                    'amount' => 1500000,
                ],
                [
                    'pay_component_id' => $this->bpjsComp->id,
                    'item_code_snapshot' => $this->bpjsComp->code,
                    'description_snapshot' => $this->bpjsComp->name,
                    'item_type' => 'deduction',
                    'amount' => 200000,
                ],
            ],
        ];

        $response = $this->actingAs($this->personalia)->put("/payslips/{$payslip->id}", $updatePayload);

        $response->assertRedirect("/payslips/{$payslip->id}");
        $response->assertSessionHas('success');

        $payslip->refresh();

        // 5.000.000 + 1.500.000 = 6.500.000
        $this->assertEquals('6500000.00', $payslip->total_earnings);
        // 200.000
        $this->assertEquals('200000.00', $payslip->total_deductions);
        // 6.500.000 - 200.000 = 6.300.000
        $this->assertEquals('6300000.00', $payslip->net_salary);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payslip.updated',
            'user_id' => $this->personalia->id,
            'subject_id' => (string) $payslip->id,
        ]);
    }

    public function test_auditor_has_read_only_access_to_payslips(): void
    {
        $payslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'slip_number' => 'SLIP/2026-10/EMP-001',
            'employee_number_snapshot' => 'EMP-001',
            'employee_name_snapshot' => 'Budi Santoso',
            'basic_salary' => 5000000,
            'total_earnings' => 5000000,
            'total_deductions' => 0,
            'net_salary' => 5000000,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->auditor)->get('/payslips')->assertOk();
        $this->actingAs($this->auditor)->get("/payslips/{$payslip->id}")->assertOk();

        // Auditor dilarang mengedit atau menghapus
        $this->actingAs($this->auditor)->put("/payslips/{$payslip->id}", [
            'items' => [
                ['item_type' => 'earning', 'amount' => 1000],
            ],
        ])->assertStatus(403);

        $this->actingAs($this->auditor)->delete("/payslips/{$payslip->id}")->assertStatus(403);
    }
}
