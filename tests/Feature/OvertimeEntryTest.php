<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\PayComponent;
use App\Models\PayrollCalculationConfig;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\OvertimeCalculationService;
use App\Services\PayslipService;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $personalia;
    protected User $auditor;
    protected PayrollPeriod $period;
    protected Employee $employee;
    protected PayslipService $payslipService;
    protected OvertimeCalculationService $overtimeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->payslipService = app(PayslipService::class);
        $this->overtimeService = app(OvertimeCalculationService::class);

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $this->employee = Employee::create([
            'employee_number' => 'EMP-OT-001',
            'full_name' => 'Karyawan Test Lembur',
            'department' => 'IT',
            'position' => 'Developer',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
        ]);
    }

    /**
     * Test 1: Input satu entri lembur berhasil dan durasi dihitung otomatis.
     */
    public function test_can_store_single_overtime_entry_with_auto_duration(): void
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'note' => 'Lembur deploy sistem',
        ];

        $response = $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('overtime_entries', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'duration_minutes' => 180,
            'duration_hours' => 3.00,
            'note' => 'Lembur deploy sistem',
        ]);
    }

    /**
     * Test 2: Beberapa entri untuk karyawan yang sama tersimpan sebagai entri tersendiri.
     */
    public function test_multiple_overtime_entries_for_same_employee(): void
    {
        $entries = [
            ['date' => '2026-10-10', 'day_type' => 'working_day', 'start_time' => '17:00', 'end_time' => '20:00'],
            ['date' => '2026-10-11', 'day_type' => 'holiday', 'start_time' => '08:00', 'end_time' => '12:00'],
            ['date' => '2026-10-12', 'day_type' => 'working_day', 'start_time' => '18:00', 'end_time' => '21:00'],
        ];

        foreach ($entries as $e) {
            $this->actingAs($this->personalia)
                ->post("/payroll-periods/{$this->period->id}/overtimes", array_merge($e, ['employee_id' => $this->employee->id]))
                ->assertSessionHasNoErrors();
        }

        $this->assertEquals(3, OvertimeEntry::where('employee_id', $this->employee->id)->count());
    }

    /**
     * Test 3: Perhitungan durasi lintas tengah malam (e.g. 22:00 - 02:00 = 4 jam / 240 menit).
     */
    public function test_overtime_crossing_midnight_calculation(): void
    {
        $duration = OvertimeEntry::calculateDuration('22:00', '02:00');
        $this->assertEquals(240, $duration['minutes']);
        $this->assertEquals(4.00, $duration['hours']);

        $duration2 = OvertimeEntry::calculateDuration('23:30', '00:30');
        $this->assertEquals(60, $duration2['minutes']);
        $this->assertEquals(1.00, $duration2['hours']);

        // Test saving via controller
        $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-15',
                'day_type' => 'working_day',
                'start_time' => '22:00',
                'end_time' => '02:00',
                'note' => 'Shift lembur malam',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('overtime_entries', [
            'start_time' => '22:00',
            'end_time' => '02:00',
            'duration_minutes' => 240,
            'duration_hours' => 4.00,
        ]);
    }

    /**
     * Test 4: Threshold tepat 3 jam untuk Hari Biasa:
     * - 2 jam 59 menit (179 menit) -> Tidak dipotong.
     * - 3 jam 0 menit (180 menit) -> DIPOTONG 0.5 jam.
     * - 3 jam 1 menit (181 menit) -> DIPOTONG 0.5 jam.
     */
    public function test_working_day_threshold_break_deduction(): void
    {
        // 2:59 -> 179 mins
        $calc1 = $this->overtimeService->calculateBreakDeduction('2:59', 1.0, 'working_day', 3.0, 0.5);
        $this->assertFalse($calc1['is_deducted']);
        $this->assertEquals(0.0, $calc1['deduction']);
        $this->assertEquals(179, $calc1['net_minutes']);

        // 3:00 -> 180 mins
        $calc2 = $this->overtimeService->calculateBreakDeduction('3:00', 1.0, 'working_day', 3.0, 0.5);
        $this->assertTrue($calc2['is_deducted']);
        $this->assertEquals(0.5, $calc2['deduction']);
        $this->assertEquals(150, $calc2['net_minutes']); // 180 - 30 = 150 mins (2.5 jam)

        // 3:01 -> 181 mins
        $calc3 = $this->overtimeService->calculateBreakDeduction('3:01', 1.0, 'working_day', 3.0, 0.5);
        $this->assertTrue($calc3['is_deducted']);
        $this->assertEquals(0.5, $calc3['deduction']);
        $this->assertEquals(151, $calc3['net_minutes']);
    }

    /**
     * Test 5: Threshold tepat 4 jam untuk Hari Libur:
     * - 3 jam 59 menit (239 menit) -> Tidak dipotong.
     * - 4 jam 0 menit (240 menit) -> DIPOTONG 1.0 jam.
     * - 4 jam 1 menit (241 menit) -> DIPOTONG 1.0 jam.
     */
    public function test_holiday_threshold_break_deduction(): void
    {
        // 3:59 -> 239 mins
        $calc1 = $this->overtimeService->calculateBreakDeduction('3:59', 1.0, 'holiday', 4.0, 1.0);
        $this->assertFalse($calc1['is_deducted']);
        $this->assertEquals(0.0, $calc1['deduction']);
        $this->assertEquals(239, $calc1['net_minutes']);

        // 4:00 -> 240 mins
        $calc2 = $this->overtimeService->calculateBreakDeduction('4:00', 1.0, 'holiday', 4.0, 1.0);
        $this->assertTrue($calc2['is_deducted']);
        $this->assertEquals(1.0, $calc2['deduction']);
        $this->assertEquals(180, $calc2['net_minutes']); // 240 - 60 = 180 mins (3.0 jam)

        // 4:01 -> 241 mins
        $calc3 = $this->overtimeService->calculateBreakDeduction('4:01', 1.0, 'holiday', 4.0, 1.0);
        $this->assertTrue($calc3['is_deducted']);
        $this->assertEquals(1.0, $calc3['deduction']);
        $this->assertEquals(181, $calc3['net_minutes']);
    }

    /**
     * Test 6: Threshold dinamis yang diubah melalui konfigurasi `/payroll-configurations`.
     */
    public function test_custom_threshold_configured_via_payroll_configurations(): void
    {
        // Update threshold via endpoint: working_day = 2.0, holiday = 5.0
        $this->actingAs($this->personalia)->post('/payroll-configurations/overtime-settings', [
            'working_day_threshold' => 2.0,
            'holiday_threshold' => 5.0,
        ])->assertSessionHasNoErrors();

        // 2:00 on working day now triggers deduction
        $resWd = $this->overtimeService->calculateForEmployee($this->employee, [
            'working_day_hours' => 2.0,
            'working_day_days' => 1,
        ]);
        $this->assertEquals('0.50', $resWd['break_deduction']);
        $this->assertEquals('1.50', $resWd['quantity']);

        // 4:00 on holiday no longer triggers deduction (threshold is 5.0)
        $resHl = $this->overtimeService->calculateForEmployee($this->employee, [
            'holiday_hours' => 4.0,
            'holiday_days' => 1,
        ]);
        $this->assertEquals('0.00', $resHl['break_deduction']);
        $this->assertEquals('4.00', $resHl['quantity']);
    }

    /**
     * Test 7: Agregasi dan pemisahan upah dan potongan lembur secara rinci.
     */
    public function test_separate_wage_and_deductions_recap(): void
    {
        // Entry 1: Hari Biasa 3 jam (17:00 - 20:00) -> threshold 3.0 reached -> potong 0.5 jam -> bersih 2.5 jam
        // Jam 1: 1.5 * 25.000 = 37.500; Jam 2-2.5: 1.5 * 2.0 * 25.000 = 75.000 -> Upah WD = 112.500
        // Tanpa potongan: (1*1.5 + 2*2.0)*25.000 = 137.500 -> Potongan nominal = 25.000
        OvertimeEntry::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'duration_minutes' => 180,
            'duration_hours' => 3.00,
        ]);

        // Entry 2: Hari Libur 4 jam (08:00 - 12:00) -> threshold 4.0 reached -> potong 1.0 jam -> bersih 3.0 jam
        // Upah HL: 3.0 * 2.0 * 25.000 = 150.000
        // Tanpa potongan: 4.0 * 2.0 * 25.000 = 200.000 -> Potongan nominal = 50.000
        OvertimeEntry::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-10-11',
            'day_type' => 'holiday',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'duration_minutes' => 240,
            'duration_hours' => 4.00,
        ]);

        $entries = OvertimeEntry::where('employee_id', $this->employee->id)->get();
        $recap = $this->overtimeService->calculateEmployeeOvertimeRecap($this->employee, $entries);

        $this->assertEquals(3.00, $recap['working_day']['gross_hours']);
        $this->assertTrue($recap['working_day']['threshold_reached']);
        $this->assertEquals(0.50, $recap['working_day']['break_deduction_hours']);
        $this->assertEquals('112500.00', $recap['working_day']['pay']);
        $this->assertEquals('25000.00', $recap['working_day']['deduction_amount']);

        $this->assertEquals(4.00, $recap['holiday']['gross_hours']);
        $this->assertTrue($recap['holiday']['threshold_reached']);
        $this->assertEquals(1.00, $recap['holiday']['break_deduction_hours']);
        $this->assertEquals('150000.00', $recap['holiday']['pay']);
        $this->assertEquals('50000.00', $recap['holiday']['deduction_amount']);

        $this->assertEquals(7.00, $recap['total_gross_hours']);
        $this->assertEquals(1.50, $recap['total_break_deduction_hours']);
        $this->assertEquals(5.50, $recap['total_net_hours']);
        $this->assertEquals('337500.00', $recap['total_upah_bruto']);
        $this->assertEquals('75000.00', $recap['total_potongan_lembur']);
        $this->assertEquals('262500.00', $recap['total_upah_lembur']);
    }

    /**
     * Test 8: Validasi data tidak valid ditolak.
     */
    public function test_overtime_entry_validation_errors(): void
    {
        // 1. Same start and end time (duration 0)
        $res1 = $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '17:00',
            ]);
        $res1->assertSessionHasErrors(['end_time']);

        // 2. Date outside period range
        $res2 = $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-11-05', // outside period 2026-10-01 - 2026-10-31
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '20:00',
            ]);
        $res2->assertSessionHasErrors(['date']);

        // 3. Inactive employee
        $inactiveEmployee = Employee::create([
            'employee_number' => 'EMP-INACT',
            'full_name' => 'Karyawan Non-Aktif',
            'employment_status' => 'inactive',
        ]);
        $res3 = $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $inactiveEmployee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '20:00',
            ]);
        $res3->assertSessionHasErrors(['employee_id']);

        // 4. Duplicate identical entry
        OvertimeEntry::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'duration_minutes' => 180,
            'duration_hours' => 3.00,
        ]);

        $res4 = $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '20:00',
            ]);
        $res4->assertSessionHasErrors(['start_time']);
    }

    /**
     * Test 9: Authorization - Auditor cannot mutate overtime.
     */
    public function test_auditor_cannot_mutate_overtime_entries(): void
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
        ];

        // Auditor forbidden to store
        $this->actingAs($this->auditor)
            ->post("/payroll-periods/{$this->period->id}/overtimes", $payload)
            ->assertForbidden();

        $entry = OvertimeEntry::create(array_merge($payload, [
            'payroll_period_id' => $this->period->id,
            'duration_minutes' => 180,
            'duration_hours' => 3.00,
        ]));

        // Auditor forbidden to update or delete
        $this->actingAs($this->auditor)
            ->put("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}", $payload)
            ->assertForbidden();

        $this->actingAs($this->auditor)
            ->delete("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}")
            ->assertForbidden();
    }

    /**
     * Test 10: Penolakan perubahan setelah payroll terkunci (finalized atau submitted).
     */
    public function test_rejection_of_changes_when_period_is_locked(): void
    {
        $entry = OvertimeEntry::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-10-10',
            'day_type' => 'working_day',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'duration_minutes' => 180,
            'duration_hours' => 3.00,
        ]);

        // Lock period to in_review (submitted)
        $this->period->update(['status' => PayrollPeriodStatus::IN_REVIEW->value]);

        $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-12',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '20:00',
            ])
            ->assertForbidden();

        $this->actingAs($this->personalia)
            ->put("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '21:00',
            ])
            ->assertForbidden();

        $this->actingAs($this->personalia)
            ->delete("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}")
            ->assertForbidden();

        // Lock period to finalized
        $this->period->update(['status' => PayrollPeriodStatus::FINALIZED->value]);

        $this->actingAs($this->personalia)
            ->delete("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}")
            ->assertForbidden();
    }

    /**
     * Test 11: Sinkronisasi otomatis ke payslip draft karyawan dan konsistensi snapshot slip final.
     */
    public function test_automatic_sync_to_draft_payslip_and_snapshot_immutability(): void
    {
        // 1. Auto generate payroll for period
        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->first();
        $this->assertNotNull($payslip);
        $initialNet = $payslip->net_salary;

        // 2. Add an overtime entry (3 hours working day = 2.5 net hours = Rp 112.500)
        $this->actingAs($this->personalia)
            ->post("/payroll-periods/{$this->period->id}/overtimes", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '20:00',
            ]);

        $payslip->refresh();
        $overtimeItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNotNull($overtimeItem);
        $this->assertEquals('112500.00', $overtimeItem->amount);
        $this->assertEquals('2.50', $overtimeItem->quantity);

        // Payslip net salary is increased by exactly 112,500
        $this->assertEquals(bcadd($initialNet, '112500.00', 2), $payslip->net_salary);

        // 3. Edit entry: change to 4 hours (17:00 - 21:00 = 3.5 net hours = Rp 162.500)
        $entry = OvertimeEntry::first();
        $this->actingAs($this->personalia)
            ->put("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}", [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-10',
                'day_type' => 'working_day',
                'start_time' => '17:00',
                'end_time' => '21:00',
            ]);

        $payslip->refresh();
        $overtimeItem->refresh();
        $this->assertEquals('162500.00', $overtimeItem->amount);
        $this->assertEquals('3.50', $overtimeItem->quantity);

        // 4. Delete entry: overtime item is removed and net salary restored
        $this->actingAs($this->personalia)
            ->delete("/payroll-periods/{$this->period->id}/overtimes/{$entry->id}");

        $payslip->refresh();
        $this->assertNull($payslip->items()->where('item_code_snapshot', 'OVERTIME')->first());
        $this->assertEquals($initialNet, $payslip->net_salary);
    }
}
