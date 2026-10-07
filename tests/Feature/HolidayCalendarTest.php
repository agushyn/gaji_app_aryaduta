<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\HolidayCalendar;
use App\Models\User;
use App\Services\HolidaySyncService;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
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

    public function test_can_view_holiday_calendar_list(): void
    {
        HolidayCalendar::create([
            'date' => '2026-01-01',
            'name' => 'Tahun Baru 2026 Masehi',
            'type' => 'national_holiday',
            'is_joint_leave' => false,
            'is_payroll_holiday' => true,
            'year' => 2026,
            'source' => 'skb_3_menteri',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->get('/holiday-calendars?year=2026');
        $response->assertOk();
    }

    public function test_can_manually_add_holiday(): void
    {
        $response = $this->actingAs($this->personalia)->post('/holiday-calendars', [
            'date' => '2026-12-25',
            'name' => 'Hari Raya Natal',
            'type' => 'national_holiday',
            'is_joint_leave' => false,
            'is_payroll_holiday' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('holiday_calendars', [
            'name' => 'Hari Raya Natal',
            'type' => 'national_holiday',
            'year' => 2026,
            'is_payroll_holiday' => true,
        ]);
    }

    public function test_can_sync_holidays_idempotently_without_duplicates(): void
    {
        $syncService = app(HolidaySyncService::class);

        // First sync
        $result1 = $syncService->syncYear(2026);
        $this->assertGreaterThan(0, $result1['synced']);
        $initialCount = HolidayCalendar::where('year', 2026)->count();

        // Second sync for same year
        $result2 = $syncService->syncYear(2026);
        $secondCount = HolidayCalendar::where('year', 2026)->count();

        // Verify count didn't double (idempotency by date + type)
        $this->assertEquals($initialCount, $secondCount);
    }

    public function test_can_toggle_is_payroll_holiday(): void
    {
        $holiday = HolidayCalendar::create([
            'date' => '2026-05-01',
            'name' => 'Hari Buruh Internasional',
            'type' => 'national_holiday',
            'is_joint_leave' => false,
            'is_payroll_holiday' => true,
            'year' => 2026,
            'source' => 'skb_3_menteri',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->personalia)->patch("/holiday-calendars/{$holiday->id}/toggle-payroll-holiday");
        $response->assertRedirect();

        $holiday->refresh();
        $this->assertFalse($holiday->is_payroll_holiday);
    }

    public function test_artisan_sync_command(): void
    {
        $this->artisan('payroll:sync-holidays', ['--year' => 2026])
            ->assertExitCode(0);

        $this->assertDatabaseHas('holiday_calendars', [
            'year' => 2026,
            'is_active' => true,
        ]);
    }
}
