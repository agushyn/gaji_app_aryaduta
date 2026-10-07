<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();
    }

    public function test_unauthenticated_cannot_access_positions(): void
    {
        $this->get('/master/positions')->assertRedirect('/login');
        $this->post('/master/positions', [])->assertRedirect('/login');
    }

    public function test_auditor_cannot_modify_positions(): void
    {
        $payload = [
            'name' => 'Direktur Keuangan',
            'code' => 'DIR-FIN',
            'is_active' => true,
        ];

        $this->actingAs($this->auditor)->post('/master/positions', $payload)->assertForbidden();
    }

    public function test_personalia_can_crud_positions(): void
    {
        // 1. List
        $this->actingAs($this->personalia)->get('/master/positions')->assertOk();

        // 2. Create
        $createPayload = [
            'name' => 'Supervisor Lapangan',
            'code' => 'SPV-LAP',
            'description' => 'Bertanggung jawab atas pengawasan di lapangan',
            'is_active' => true,
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/master/positions', $createPayload);
        $postResponse->assertRedirect('/master/positions');
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('positions', [
            'name' => 'Supervisor Lapangan',
            'code' => 'SPV-LAP',
            'is_active' => 1,
        ]);

        $position = Position::where('code', 'SPV-LAP')->first();

        // 3. Update
        $updatePayload = [
            'name' => 'Senior Supervisor Lapangan',
            'code' => 'SPV-LAP',
            'description' => 'Pengawasan senior tingkat area',
            'is_active' => true,
        ];

        $putResponse = $this->actingAs($this->personalia)->put("/master/positions/{$position->id}", $updatePayload);
        $putResponse->assertRedirect('/master/positions');

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Senior Supervisor Lapangan',
        ]);

        // 4. Toggle Status
        $toggleResponse = $this->actingAs($this->personalia)->patch("/master/positions/{$position->id}/toggle-status");
        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'is_active' => 0,
        ]);

        // 5. Delete unused position
        $deleteResponse = $this->actingAs($this->personalia)->delete("/master/positions/{$position->id}");
        $deleteResponse->assertRedirect('/master/positions');
        $this->assertDatabaseMissing('positions', [
            'id' => $position->id,
        ]);
    }

    public function test_position_cannot_be_deleted_if_used_by_employees(): void
    {
        $office = Office::create([
            'name' => 'Kantor Pusat',
            'code' => 'KP',
            'address' => 'Jl. Sudirman',
            'is_active' => true,
        ]);

        $position = Position::create([
            'name' => 'Software Engineer',
            'code' => 'SWE',
            'is_active' => true,
        ]);

        Employee::create([
            'employee_number' => 'EMP-SWE-01',
            'full_name' => 'Budi Pratama',
            'office_id' => $office->id,
            'position_id' => $position->id,
            'position' => $position->name,
            'email' => 'budi@example.com',
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
        ]);

        $response = $this->actingAs($this->personalia)->delete("/master/positions/{$position->id}");
        $response->assertRedirect('/master/positions');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }
}
