<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_active_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // Verifikasi last_login_at terisi
        $this->assertNotNull($user->fresh()->last_login_at);

        // Verifikasi audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Akun Anda berstatus nonaktif. Silakan hubungi administrator.',
        ]);

        // Verifikasi audit log pencatatan penolakan
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.rejected_inactive',
            'user_id' => $user->id,
        ]);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        // Verifikasi audit log kegagalan login
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.failed',
        ]);
    }

    public function test_login_rate_limiting_locks_out_after_multiple_failed_attempts(): void
    {
        $user = User::factory()->create();

        // 5 percobaan gagal
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // Percobaan ke-6 harus terkunci rate limit (throttle)
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        // Verifikasi audit log logout
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_logged_in_user_that_is_deactivated_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Autentikasi terlebih dahulu
        $this->actingAs($user);

        // Akses halaman saat aktif sukses
        $response = $this->get('/dashboard');
        $response->assertOk();

        // Admin menonaktifkan akun
        $user->is_active = false;
        $user->save();

        // Request berikutnya harus ditolak, sesi dibatalkan, dan diredirect ke login
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
