<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_employee_routes(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $this->get('/employees/create')->assertRedirect('/login');
        $this->post('/employees', [])->assertRedirect('/login');
    }

    public function test_approver_without_employee_permissions_receives_403(): void
    {
        $this->actingAs($this->approver)->get('/employees')->assertStatus(403);
        $this->actingAs($this->approver)->get('/employees/create')->assertStatus(403);
        $this->actingAs($this->approver)->post('/employees', [
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
        ])->assertStatus(403);
    }

    public function test_personalia_can_view_employee_index_and_search(): void
    {
        Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Keuangan',
            'position' => 'Staff Accounting',
            'email' => 'budi@example.com',
            'phone' => '628123456789',
            'employment_status' => 'active',
        ]);

        Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Siti Nurhaliza',
            'department' => 'Teknologi',
            'position' => 'Backend Developer',
            'email' => 'siti@example.com',
            'phone' => '628123456780',
            'employment_status' => 'active',
        ]);

        // Akses daftar karyawan
        $response = $this->actingAs($this->personalia)->get('/employees');
        $response->assertOk();

        // Pencarian berdasarkan nama
        $searchResponse = $this->actingAs($this->personalia)->get('/employees?search=Budi');
        $searchResponse->assertOk();

        // Pencarian berdasarkan NIK
        $searchNikResponse = $this->actingAs($this->personalia)->get('/employees?search=EMP-002');
        $searchNikResponse->assertOk();
    }

    public function test_personalia_can_filter_employees_by_department_and_status(): void
    {
        Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Karyawan Aktif Keuangan',
            'department' => 'Keuangan',
            'employment_status' => 'active',
        ]);

        Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Karyawan Nonaktif HRD',
            'department' => 'HRD',
            'employment_status' => 'inactive',
        ]);

        $filterResponse = $this->actingAs($this->personalia)->get('/employees?department=Keuangan&status=active');
        $filterResponse->assertOk();
    }

    public function test_personalia_can_create_employee_with_normalized_phone(): void
    {
        $payload = [
            'employee_number' => 'EMP-2026-001',
            'full_name' => 'Ahmad Dahlan',
            'department' => 'Operasional',
            'position' => 'Supervisor Lapangan',
            'email' => 'Ahmad.Dahlan@Example.com',
            'phone' => '0812-3456-7890',
            'hire_date' => '2026-01-15',
            'employment_status' => 'active',
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHas('success');

        // Pastikan tersimpan dengan nomor yang sudah dinormalisasi dan email lowercase
        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-2026-001',
            'full_name' => 'Ahmad Dahlan',
            'email' => 'ahmad.dahlan@example.com',
            'phone' => '6281234567890',
            'employment_status' => 'active',
        ]);

        // Cek audit log tercatat
        $createdEmployee = Employee::where('employee_number', 'EMP-2026-001')->first();
        $this->assertNotNull($createdEmployee);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'user_id' => $this->personalia->id,
            'subject_id' => (string) $createdEmployee->id,
        ]);
    }

    public function test_employee_creation_fails_with_duplicate_employee_number(): void
    {
        Employee::create([
            'employee_number' => 'EMP-EXISTING',
            'full_name' => 'Karyawan Pertama',
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($this->personalia)->post('/employees', [
            'employee_number' => 'EMP-EXISTING',
            'full_name' => 'Karyawan Kedua',
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasErrors('employee_number');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_employee_creation_fails_with_invalid_phone_number(): void
    {
        // Nomor bebas pulsa 0800
        $response = $this->actingAs($this->personalia)->post('/employees', [
            'employee_number' => 'EMP-001',
            'full_name' => 'Karyawan Test',
            'phone' => '0800123456',
            'employment_status' => 'active',
        ]);
        $response->assertSessionHasErrors('phone');

        // Nomor terlalu pendek
        $response2 = $this->actingAs($this->personalia)->post('/employees', [
            'employee_number' => 'EMP-002',
            'full_name' => 'Karyawan Test 2',
            'phone' => '08123',
            'employment_status' => 'active',
        ]);
        $response2->assertSessionHasErrors('phone');
    }

    public function test_personalia_can_view_employee_details(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-SHOW',
            'full_name' => 'Rina Wijaya',
            'department' => 'Keuangan',
            'position' => 'Finance Analyst',
            'email' => 'rina@example.com',
            'phone' => '6281311223344',
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($this->personalia)->get("/employees/{$employee->id}");
        $response->assertOk();
    }

    public function test_personalia_can_update_employee_and_it_records_audit_log(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-010',
            'full_name' => 'Joko Widodo',
            'department' => 'Logistik',
            'position' => 'Staff',
            'email' => 'joko@example.com',
            'phone' => '628111111111',
            'employment_status' => 'active',
        ]);

        $updatePayload = [
            'employee_number' => 'EMP-010',
            'full_name' => 'Joko Widodo Updated',
            'department' => 'Pengadaan',
            'position' => 'Senior Staff',
            'email' => 'joko.new@example.com',
            'phone' => '081222222222',
            'employment_status' => 'active',
        ];

        $response = $this->actingAs($this->personalia)->put("/employees/{$employee->id}", $updatePayload);

        $response->assertRedirect("/employees/{$employee->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'full_name' => 'Joko Widodo Updated',
            'department' => 'Pengadaan',
            'position' => 'Senior Staff',
            'email' => 'joko.new@example.com',
            'phone' => '6281222222222',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.updated',
            'user_id' => $this->personalia->id,
            'subject_id' => (string) $employee->id,
        ]);
    }

    public function test_employee_update_fails_with_duplicate_employee_number_of_another_employee(): void
    {
        $emp1 = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Karyawan 1',
            'employment_status' => 'active',
        ]);

        $emp2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Karyawan 2',
            'employment_status' => 'active',
        ]);

        // Coba ganti NIK emp2 menjadi EMP-001
        $response = $this->actingAs($this->personalia)->put("/employees/{$emp2->id}", [
            'employee_number' => 'EMP-001',
            'full_name' => 'Karyawan 2 Berubah',
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasErrors('employee_number');
    }

    public function test_personalia_can_toggle_employee_status_without_losing_history(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-TOGGLE',
            'full_name' => 'Dewi Sartika',
            'employment_status' => 'active',
        ]);

        $this->assertTrue($employee->is_active);

        // Nonaktifkan
        $response = $this->actingAs($this->personalia)->patch("/employees/{$employee->id}/toggle-status");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertEquals('inactive', $employee->fresh()->employment_status);

        // Data tidak terhapus
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);

        // Audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.status_toggled',
            'user_id' => $this->personalia->id,
            'subject_id' => (string) $employee->id,
        ]);

        // Aktifkan kembali
        $this->actingAs($this->personalia)->patch("/employees/{$employee->id}/toggle-status");
        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_personalia_can_delete_employee_without_payslip_history(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-DELETE',
            'full_name' => 'Calon Dihapus',
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($this->personalia)->delete("/employees/{$employee->id}");

        $response->assertRedirect('/employees');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);

        // Audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.deleted',
            'user_id' => $this->personalia->id,
        ]);
    }

    public function test_personalia_cannot_delete_employee_with_payslip_history(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-HISTORIS',
            'full_name' => 'Karyawan Bersejarah',
            'employment_status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'period_code' => '2026-10-TEST',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'slip_number' => 'SLIP-TEST-001',
            'employee_number_snapshot' => $employee->employee_number,
            'employee_name_snapshot' => $employee->full_name,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->personalia)->delete("/employees/{$employee->id}");

        // Harus ditolak dan kembali dengan error flash
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Data karyawan tetap ada di database
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_auditor_can_view_employees_but_cannot_create_update_or_delete(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-AUDIT',
            'full_name' => 'Karyawan Untuk Audit',
            'employment_status' => 'active',
        ]);

        // Auditor boleh lihat index dan show
        $this->actingAs($this->auditor)->get('/employees')->assertOk();
        $this->actingAs($this->auditor)->get("/employees/{$employee->id}")->assertOk();

        // Auditor dilarang membuat
        $this->actingAs($this->auditor)->get('/employees/create')->assertStatus(403);
        $this->actingAs($this->auditor)->post('/employees', [
            'employee_number' => 'EMP-999',
            'full_name' => 'Penipuan',
        ])->assertStatus(403);

        // Auditor dilarang mengedit
        $this->actingAs($this->auditor)->get("/employees/{$employee->id}/edit")->assertStatus(403);
        $this->actingAs($this->auditor)->put("/employees/{$employee->id}", [
            'employee_number' => 'EMP-AUDIT',
            'full_name' => 'Perubahan Ilegal',
        ])->assertStatus(403);

        // Auditor dilarang toggle status
        $this->actingAs($this->auditor)->patch("/employees/{$employee->id}/toggle-status")->assertStatus(403);

        // Auditor dilarang menghapus
        $this->actingAs($this->auditor)->delete("/employees/{$employee->id}")->assertStatus(403);
    }

    public function test_personalia_can_create_employee_with_default_overtime_rate(): void
    {
        $office = \App\Models\Office::firstOrCreate(
            ['code' => 'PST'],
            ['name' => 'Kantor Pusat', 'address' => 'Jl. Sudirman No. 1', 'is_active' => true]
        );
        $division = \App\Models\Division::firstOrCreate(
            ['office_id' => $office->id, 'name' => 'Operasional'],
            ['code' => 'OPS', 'is_active' => true]
        );
        $position = \App\Models\Position::firstOrCreate(
            ['name' => 'Staff Lapangan'],
            ['is_active' => true]
        );

        $payload = [
            'employee_number' => 'EMP-OT-001',
            'full_name' => 'Agus Lembur',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'gaji_pokok' => 4500000,
            'default_overtime_rate' => 25000,
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $payload);
        $response->assertRedirect('/employees');

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-OT-001',
            'full_name' => 'Agus Lembur',
            'default_overtime_rate' => '25000.00',
        ]);

        $employee = Employee::where('employee_number', 'EMP-OT-001')->first();
        $this->assertEquals('25000.00', $employee->default_overtime_rate);
    }

    public function test_personalia_can_update_employee_default_overtime_rate(): void
    {
        $office = \App\Models\Office::firstOrCreate(
            ['code' => 'PST'],
            ['name' => 'Kantor Pusat', 'address' => 'Jl. Sudirman No. 1', 'is_active' => true]
        );
        $division = \App\Models\Division::firstOrCreate(
            ['office_id' => $office->id, 'name' => 'Operasional'],
            ['code' => 'OPS', 'is_active' => true]
        );
        $position = \App\Models\Position::firstOrCreate(
            ['name' => 'Staff Lapangan'],
            ['is_active' => true]
        );

        $employee = Employee::create([
            'employee_number' => 'EMP-OT-002',
            'full_name' => 'Budi Lembur',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'gaji_pokok' => 4500000,
            'default_overtime_rate' => 25000,
        ]);

        $updatePayload = [
            'employee_number' => 'EMP-OT-002',
            'full_name' => 'Budi Lembur Update',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'gaji_pokok' => 4500000,
            'default_overtime_rate' => 30000,
        ];

        $response = $this->actingAs($this->personalia)->put("/employees/{$employee->id}", $updatePayload);
        $response->assertRedirect("/employees/{$employee->id}");

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'default_overtime_rate' => '30000.00',
        ]);
    }

    public function test_employee_creation_fails_with_negative_overtime_rate(): void
    {
        $office = \App\Models\Office::firstOrCreate(
            ['code' => 'PST'],
            ['name' => 'Kantor Pusat', 'address' => 'Jl. Sudirman No. 1', 'is_active' => true]
        );
        $division = \App\Models\Division::firstOrCreate(
            ['office_id' => $office->id, 'name' => 'Operasional'],
            ['code' => 'OPS', 'is_active' => true]
        );
        $position = \App\Models\Position::firstOrCreate(
            ['name' => 'Staff Lapangan'],
            ['is_active' => true]
        );

        $payload = [
            'employee_number' => 'EMP-OT-NEG',
            'full_name' => 'Negatif Lembur',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'default_overtime_rate' => -1000,
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $payload);
        $response->assertSessionHasErrors(['default_overtime_rate']);
    }

    public function test_existing_employee_without_overtime_rate_renders_index_and_show(): void
    {
        $office = \App\Models\Office::firstOrCreate(
            ['code' => 'PST'],
            ['name' => 'Kantor Pusat', 'address' => 'Jl. Sudirman No. 1', 'is_active' => true]
        );
        $division = \App\Models\Division::firstOrCreate(
            ['office_id' => $office->id, 'name' => 'Operasional'],
            ['code' => 'OPS', 'is_active' => true]
        );
        $position = \App\Models\Position::firstOrCreate(
            ['name' => 'Staff Lapangan'],
            ['is_active' => true]
        );

        $employee = Employee::create([
            'employee_number' => 'EMP-OT-ZERO',
            'full_name' => 'Zero Lembur',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'default_overtime_rate' => 0,
        ]);

        $indexResponse = $this->actingAs($this->personalia)->get('/employees');
        $indexResponse->assertOk();

        $showResponse = $this->actingAs($this->personalia)->get("/employees/{$employee->id}");
        $showResponse->assertOk();

        $editResponse = $this->actingAs($this->personalia)->get("/employees/{$employee->id}/edit");
        $editResponse->assertOk();
    }
}
