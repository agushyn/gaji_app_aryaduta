<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAndDivisionTest extends TestCase
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

    public function test_unauthenticated_cannot_access_departments_or_divisions(): void
    {
        $this->get('/master/departments')->assertRedirect('/login');
        $this->post('/master/departments', [])->assertRedirect('/login');
        $this->get('/master/divisions')->assertRedirect('/login');
        $this->post('/master/divisions', [])->assertRedirect('/login');
    }

    public function test_personalia_can_create_and_update_department(): void
    {
        $response = $this->actingAs($this->personalia)->get('/master/departments');
        $response->assertOk();

        // 1. Create Department
        $createPayload = [
            'code' => 'MKT',
            'name' => 'Marketing',
            'description' => 'Departemen pemasaran dan periklanan',
            'is_active' => true,
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/master/departments', $createPayload);
        $postResponse->assertRedirect('/master/departments');
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('departments', [
            'code' => 'MKT',
            'name' => 'Marketing',
            'is_active' => 1,
        ]);

        $dept = Department::where('code', 'MKT')->first();

        // 2. Update Department
        $updatePayload = [
            'code' => 'MKT',
            'name' => 'Marketing & Sales',
            'description' => 'Departemen pemasaran dan penjualan terpadu',
            'is_active' => true,
        ];

        $putResponse = $this->actingAs($this->personalia)->put("/master/departments/{$dept->id}", $updatePayload);
        $putResponse->assertRedirect('/master/departments');
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Marketing & Sales',
        ]);

        // 3. Toggle Status
        $toggleResponse = $this->actingAs($this->personalia)->patch("/master/departments/{$dept->id}/toggle-status");
        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'is_active' => 0,
        ]);
    }

    public function test_duplicate_department_code_is_rejected(): void
    {
        Department::create([
            'code' => 'HRD',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->post('/master/departments', [
            'code' => 'HRD',
            'name' => 'Human Resources Two',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_department_cannot_be_deleted_if_used_by_divisions_or_employees(): void
    {
        $dept = Department::create([
            'code' => 'GA',
            'name' => 'General Affairs',
            'is_active' => true,
        ]);

        $div = Division::create([
            'department_id' => $dept->id,
            'code' => 'GA-OPS',
            'name' => 'Operasional Kantor',
            'is_active' => true,
        ]);

        // Trying to delete department with child division must be rejected
        $response = $this->actingAs($this->personalia)->delete("/master/departments/{$dept->id}");
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $dept->id]);

        // Delete division first
        $div->delete();

        // Now department can be deleted safely
        $responseSuccess = $this->actingAs($this->personalia)->delete("/master/departments/{$dept->id}");
        $responseSuccess->assertRedirect('/master/departments');
        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_personalia_can_crud_division(): void
    {
        $dept = Department::create([
            'code' => 'FIN',
            'name' => 'Finance',
            'is_active' => true,
        ]);

        // 1. Create Division
        $createPayload = [
            'department_id' => $dept->id,
            'code' => 'FIN-ACC',
            'name' => 'Accounting',
            'description' => 'Divisi pembukuan dan akuntansi',
            'is_active' => true,
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/master/divisions', $createPayload);
        $postResponse->assertRedirect('/master/divisions');

        $this->assertDatabaseHas('divisions', [
            'department_id' => $dept->id,
            'code' => 'FIN-ACC',
            'name' => 'Accounting',
        ]);

        $div = Division::where('code', 'FIN-ACC')->first();

        // 2. Update Division
        $updatePayload = [
            'department_id' => $dept->id,
            'code' => 'FIN-ACC',
            'name' => 'Accounting & Tax',
            'description' => 'Divisi akuntansi dan pajak',
            'is_active' => true,
        ];

        $putResponse = $this->actingAs($this->personalia)->put("/master/divisions/{$div->id}", $updatePayload);
        $putResponse->assertRedirect('/master/divisions');
        $this->assertDatabaseHas('divisions', [
            'id' => $div->id,
            'name' => 'Accounting & Tax',
        ]);

        // 3. Toggle Status
        $toggleResponse = $this->actingAs($this->personalia)->patch("/master/divisions/{$div->id}/toggle-status");
        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('divisions', [
            'id' => $div->id,
            'is_active' => 0,
        ]);
    }

    public function test_division_cannot_be_deleted_if_used_by_employees(): void
    {
        $dept = Department::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
        $div = Division::create(['department_id' => $dept->id, 'code' => 'IT-DEV', 'name' => 'Software Engineering', 'is_active' => true]);

        $employee = Employee::create([
            'employee_number' => 'EMP-IT-001',
            'full_name' => 'Developer One',
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'department' => $dept->name,
            'employment_status' => 'active',
        ]);

        // Attempt deletion of division assigned to employee must fail
        $response = $this->actingAs($this->personalia)->delete("/master/divisions/{$div->id}");
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('divisions', ['id' => $div->id]);

        // Remove division link from employee
        $employee->update(['division_id' => null]);

        // Now deletion succeeds
        $responseSuccess = $this->actingAs($this->personalia)->delete("/master/divisions/{$div->id}");
        $responseSuccess->assertRedirect('/master/divisions');
        $this->assertDatabaseMissing('divisions', ['id' => $div->id]);
    }

    public function test_backend_validates_division_belongs_to_department(): void
    {
        $deptMarketing = Department::create(['code' => 'MKT', 'name' => 'Marketing', 'is_active' => true]);
        $divDigitalMkt = Division::create(['department_id' => $deptMarketing->id, 'code' => 'MKT-DIG', 'name' => 'Digital Marketing', 'is_active' => true]);

        $deptFinance = Department::create(['code' => 'FIN', 'name' => 'Finance', 'is_active' => true]);
        $divAccounting = Division::create(['department_id' => $deptFinance->id, 'code' => 'FIN-ACC', 'name' => 'Accounting', 'is_active' => true]);

        // INVALID COMBINATION: Department Marketing with Division Accounting (belongs to Finance)
        $invalidPayload = [
            'employee_number' => 'EMP-INVALID-001',
            'full_name' => 'Invalid Employee',
            'department_id' => $deptMarketing->id,
            'division_id' => $divAccounting->id, // Accounting belongs to Finance, not Marketing!
            'employment_status' => 'active',
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $invalidPayload);
        $response->assertSessionHasErrors(['division_id']);

        $this->assertDatabaseMissing('employees', [
            'employee_number' => 'EMP-INVALID-001',
        ]);

        // VALID COMBINATION: Department Marketing with Division Digital Marketing
        $validPayload = [
            'employee_number' => 'EMP-VALID-001',
            'full_name' => 'Valid Employee',
            'department_id' => $deptMarketing->id,
            'division_id' => $divDigitalMkt->id,
            'employment_status' => 'active',
        ];

        $validResponse = $this->actingAs($this->personalia)->post('/employees', $validPayload);
        $validResponse->assertRedirect('/employees');
        $validResponse->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-VALID-001',
            'department_id' => $deptMarketing->id,
            'division_id' => $divDigitalMkt->id,
            'department' => 'Marketing',
        ]);
    }

    public function test_employee_department_and_division_relationships(): void
    {
        $dept = Department::create(['code' => 'HRD', 'name' => 'Human Resources', 'is_active' => true]);
        $div = Division::create(['department_id' => $dept->id, 'code' => 'HRD-REC', 'name' => 'Recruitment', 'is_active' => true]);

        $emp = Employee::create([
            'employee_number' => 'EMP-REL-01',
            'full_name' => 'Relational Test',
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'department' => $dept->name,
            'employment_status' => 'active',
        ]);

        $emp->load(['department', 'division']);

        $this->assertEquals($dept->id, $emp->department->id);
        $this->assertEquals('Human Resources', $emp->department->name);
        $this->assertEquals($div->id, $emp->division->id);
        $this->assertEquals('Recruitment', $emp->division->name);

        $this->assertTrue($dept->employees->contains($emp));
        $this->assertTrue($div->employees->contains($emp));
        $this->assertTrue($dept->divisions->contains($div));
    }

    public function test_filter_and_search_employees_by_department_and_division(): void
    {
        $deptA = Department::create(['code' => 'D-A', 'name' => 'Departemen A', 'is_active' => true]);
        $divA1 = Division::create(['department_id' => $deptA->id, 'code' => 'DIV-A1', 'name' => 'Divisi A1', 'is_active' => true]);

        $deptB = Department::create(['code' => 'D-B', 'name' => 'Departemen B', 'is_active' => true]);
        $divB1 = Division::create(['department_id' => $deptB->id, 'code' => 'DIV-B1', 'name' => 'Divisi B1', 'is_active' => true]);

        Employee::create([
            'employee_number' => 'EMP-A1',
            'full_name' => 'Budi Santoso',
            'department_id' => $deptA->id,
            'division_id' => $divA1->id,
            'department' => $deptA->name,
            'employment_status' => 'active',
        ]);

        Employee::create([
            'employee_number' => 'EMP-B1',
            'full_name' => 'Siti Rahma',
            'department_id' => $deptB->id,
            'division_id' => $divB1->id,
            'department' => $deptB->name,
            'employment_status' => 'active',
        ]);

        // Filter by Department A
        $resDept = $this->actingAs($this->personalia)->get("/employees?department={$deptA->id}");
        $resDept->assertOk();

        // Filter by Division A1
        $resDiv = $this->actingAs($this->personalia)->get("/employees?department={$deptA->id}&division={$divA1->id}");
        $resDiv->assertOk();

        // Search by Department name
        $resSearch = $this->actingAs($this->personalia)->get('/employees?search=Departemen A');
        $resSearch->assertOk();
    }

    public function test_auditor_can_view_departments_and_divisions_but_cannot_manage(): void
    {
        $dept = Department::create(['code' => 'AUD', 'name' => 'Auditing', 'is_active' => true]);
        $div = Division::create(['department_id' => $dept->id, 'code' => 'AUD-INT', 'name' => 'Internal Audit', 'is_active' => true]);

        // Auditor can view
        $this->actingAs($this->auditor)->get('/master/departments')->assertOk();
        $this->actingAs($this->auditor)->get('/master/divisions')->assertOk();

        // Auditor cannot create, update, or delete department
        $this->actingAs($this->auditor)->post('/master/departments', ['code' => 'TEST', 'name' => 'Test'])->assertStatus(403);
        $this->actingAs($this->auditor)->put("/master/departments/{$dept->id}", ['code' => 'TEST', 'name' => 'Test'])->assertStatus(403);
        $this->actingAs($this->auditor)->delete("/master/departments/{$dept->id}")->assertStatus(403);

        // Auditor cannot create, update, or delete division
        $this->actingAs($this->auditor)->post('/master/divisions', ['department_id' => $dept->id, 'name' => 'Test Div'])->assertStatus(403);
        $this->actingAs($this->auditor)->put("/master/divisions/{$div->id}", ['department_id' => $dept->id, 'name' => 'Test Div'])->assertStatus(403);
        $this->actingAs($this->auditor)->delete("/master/divisions/{$div->id}")->assertStatus(403);
    }

    public function test_cascading_divisions_api_endpoint(): void
    {
        $dept = Department::create(['code' => 'ENG', 'name' => 'Engineering', 'is_active' => true]);
        $div1 = Division::create(['department_id' => $dept->id, 'code' => 'ENG-FE', 'name' => 'Frontend', 'is_active' => true]);
        $div2 = Division::create(['department_id' => $dept->id, 'code' => 'ENG-BE', 'name' => 'Backend', 'is_active' => true]);
        $divInactive = Division::create(['department_id' => $dept->id, 'code' => 'ENG-OLD', 'name' => 'Legacy Team', 'is_active' => false]);

        $response = $this->actingAs($this->personalia)->get("/master/departments/{$dept->id}/divisions");
        $response->assertOk();
        $response->assertJsonCount(2); // Only 2 active divisions
        $response->assertJsonFragment(['name' => 'Frontend']);
        $response->assertJsonFragment(['name' => 'Backend']);
        $response->assertJsonMissing(['name' => 'Legacy Team']);
    }
}
