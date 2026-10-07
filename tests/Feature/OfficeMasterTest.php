<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeMasterTest extends TestCase
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

    public function test_unauthenticated_cannot_access_offices(): void
    {
        $this->get('/master/offices')->assertRedirect('/login');
        $this->post('/master/offices', [])->assertRedirect('/login');
    }

    public function test_auditor_cannot_create_or_modify_office(): void
    {
        $payload = [
            'name' => 'Kantor Cabang Surabaya',
            'code' => 'SBY',
            'address' => 'Jl. Pemuda No. 45, Surabaya',
            'is_active' => true,
        ];

        $this->actingAs($this->auditor)->post('/master/offices', $payload)->assertForbidden();
    }

    public function test_personalia_can_crud_office(): void
    {
        // 1. List
        $this->actingAs($this->personalia)->get('/master/offices')->assertOk();

        // 2. Create
        $createPayload = [
            'name' => 'CV. BINA PUSTAKA',
            'code' => 'BP',
            'address' => "Jl. Raya Serang Km 12\nTangerang, Banten",
            'phone' => '021-5551234',
            'email' => 'info@binapustaka.co.id',
            'is_active' => true,
        ];

        $postResponse = $this->actingAs($this->personalia)->post('/master/offices', $createPayload);
        $postResponse->assertRedirect('/master/offices');
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('offices', [
            'name' => 'CV. BINA PUSTAKA',
            'code' => 'BP',
            'phone' => '021-5551234',
            'is_active' => 1,
        ]);

        $office = Office::where('code', 'BP')->first();

        // 3. Update
        $updatePayload = [
            'name' => 'CV. BINA PUSTAKA UTAMA',
            'code' => 'BP',
            'address' => "Jl. Raya Serang Km 14\nTangerang, Banten",
            'phone' => '021-5559999',
            'email' => 'contact@binapustaka.co.id',
            'is_active' => true,
        ];

        $putResponse = $this->actingAs($this->personalia)->put("/master/offices/{$office->id}", $updatePayload);
        $putResponse->assertRedirect('/master/offices');

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'name' => 'CV. BINA PUSTAKA UTAMA',
            'phone' => '021-5559999',
        ]);

        // 4. Toggle Status
        $toggleResponse = $this->actingAs($this->personalia)->patch("/master/offices/{$office->id}/toggle-status");
        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'is_active' => 0,
        ]);

        // 5. Delete unused office
        $deleteResponse = $this->actingAs($this->personalia)->delete("/master/offices/{$office->id}");
        $deleteResponse->assertRedirect('/master/offices');
        $this->assertDatabaseMissing('offices', [
            'id' => $office->id,
        ]);
    }

    public function test_office_code_must_be_unique(): void
    {
        Office::create([
            'name' => 'Kantor Pusat',
            'code' => 'KP',
            'address' => 'Jl. Jendral Sudirman',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->post('/master/offices', [
            'name' => 'Kantor Baru',
            'code' => 'KP',
            'address' => 'Jl. Lain',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_office_cannot_be_deleted_if_used_by_divisions_or_employees(): void
    {
        $office = Office::create([
            'name' => 'Kantor Cabang Bandung',
            'code' => 'BDG',
            'address' => 'Jl. Asia Afrika',
            'is_active' => true,
        ]);

        Division::create([
            'office_id' => $office->id,
            'name' => 'Operasional Bandung',
            'code' => 'BDG-OPS',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->delete("/master/offices/{$office->id}");
        $response->assertRedirect('/master/offices');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('offices', ['id' => $office->id]);
    }

    public function test_cascading_divisions_endpoint_returns_only_office_divisions(): void
    {
        $officeA = Office::create([
            'name' => 'Kantor A',
            'code' => 'K-A',
            'address' => 'Alamat A',
            'is_active' => true,
        ]);

        $officeB = Office::create([
            'name' => 'Kantor B',
            'code' => 'K-B',
            'address' => 'Alamat B',
            'is_active' => true,
        ]);

        $divA1 = Division::create([
            'office_id' => $officeA->id,
            'name' => 'Divisi Personalia A',
            'code' => 'DIV-A1',
            'is_active' => true,
        ]);

        $divA2 = Division::create([
            'office_id' => $officeA->id,
            'name' => 'Divisi Finance A',
            'code' => 'DIV-A2',
            'is_active' => true,
        ]);

        $divB1 = Division::create([
            'office_id' => $officeB->id,
            'name' => 'Divisi Marketing B',
            'code' => 'DIV-B1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->getJson("/master/offices/{$officeA->id}/divisions");
        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'Divisi Personalia A']);
        $response->assertJsonFragment(['name' => 'Divisi Finance A']);
        $response->assertJsonMissing(['name' => 'Divisi Marketing B']);
    }
}
