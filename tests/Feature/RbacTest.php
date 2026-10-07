<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_seeder_creates_all_roles_and_default_users(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
        $this->assertDatabaseHas('roles', ['name' => 'personalia']);
        $this->assertDatabaseHas('roles', ['name' => 'approver']);
        $this->assertDatabaseHas('roles', ['name' => 'auditor']);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'personalia@example.com', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'approver@example.com', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'auditor@example.com', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'inactive@example.com', 'is_active' => false]);
    }

    public function test_super_admin_can_access_all_management_routes(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/roles')->assertOk();
        $this->actingAs($admin)->get('/audit-logs')->assertOk();
    }

    public function test_personalia_cannot_access_users_management_and_receives_403(): void
    {
        $personalia = User::where('email', 'personalia@example.com')->first();

        $response = $this->actingAs($personalia)->get('/users');

        $response->assertStatus(403);
    }

    public function test_auditor_can_access_audit_logs_and_roles_but_not_users_management(): void
    {
        $auditor = User::where('email', 'auditor@example.com')->first();

        $this->actingAs($auditor)->get('/audit-logs')->assertOk();
        $this->actingAs($auditor)->get('/roles')->assertOk();

        // Tidak boleh akses manajemen user
        $this->actingAs($auditor)->get('/users')->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login_when_accessing_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
        $this->get('/roles')->assertRedirect('/login');
        $this->get('/audit-logs')->assertRedirect('/login');
    }

    public function test_super_admin_can_create_new_internal_user(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $personaliaRole = Role::where('name', 'personalia')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@example.com',
            'password' => 'SecurePass123!',
            'role_id' => $personaliaRole->id,
            'is_active' => true,
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success');

        $newUser = User::where('email', 'petugas.baru@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('personalia'));

        // Cek audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_id' => $admin->id,
            'subject_id' => (string) $newUser->id,
        ]);
    }

    public function test_user_without_permission_cannot_create_user(): void
    {
        $personalia = User::where('email', 'personalia@example.com')->first();
        $personaliaRole = Role::where('name', 'personalia')->first();

        $response = $this->actingAs($personalia)->post('/users', [
            'name' => 'Hacker User',
            'email' => 'hacker@example.com',
            'password' => 'Password123!',
            'role_id' => $personaliaRole->id,
            'is_active' => true,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_super_admin_can_toggle_user_status(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $targetUser = User::where('email', 'personalia@example.com')->first();
        $this->assertTrue($targetUser->is_active);

        // Nonaktifkan
        $response = $this->actingAs($admin)->patch("/users/{$targetUser->id}/toggle-status");
        $response->assertRedirect('/users');
        $this->assertFalse($targetUser->fresh()->is_active);

        // Audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.status_toggled',
            'user_id' => $admin->id,
            'subject_id' => (string) $targetUser->id,
        ]);

        // Aktifkan kembali
        $this->actingAs($admin)->patch("/users/{$targetUser->id}/toggle-status");
        $this->assertTrue($targetUser->fresh()->is_active);
    }

    public function test_user_cannot_deactivate_own_account(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)->patch("/users/{$admin->id}/toggle-status");

        $response->assertRedirect('/users');
        $response->assertSessionHas('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        $this->assertTrue($admin->fresh()->is_active);
    }
}
