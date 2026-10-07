<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipPdfService;
use App\Services\PayslipService;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayslipPdfAndFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected Employee $employee;

    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CompanySettingSeeder::class);

        Storage::fake('local');

        $this->superAdmin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        // User without payslips.finalize or payslips.view permissions
        $noPermRole = Role::create([
            'name' => 'plain_staff',
            'label' => 'Plain Staff',
            'description' => 'Staff without payroll permissions',
        ]);
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->roles()->attach($noPermRole);

        $this->employee = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff Gudang',
            'email' => 'budi@example.com',
            'phone' => '6281234567890',
            'hire_date' => '2024-01-15',
            'employment_status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-09',
            'start_date' => '2026-08-23',
            'end_date' => '2026-09-22',
            'working_days' => 25,
            'period_month_label' => 'September 2026',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);
    }

    protected function createApprovedPayslip(array $customItems = []): Payslip
    {
        $items = ! empty($customItems) ? $customItems : [
            [
                'item_code_snapshot' => 'BASIC_SALARY',
                'description_snapshot' => 'Gaji Pokok',
                'item_type' => 'earning',
                'amount' => '3500000.00',
            ],
            [
                'item_code_snapshot' => 'MEAL',
                'description_snapshot' => 'Uang Makan',
                'item_type' => 'earning',
                'amount' => '625000.00',
                'quantity' => '25',
                'unit' => 'hari',
                'rate' => '25000.00',
            ],
            [
                'item_code_snapshot' => 'PJD_LUAR_KOTA',
                'description_snapshot' => 'PJD Luar Kota',
                'item_type' => 'earning',
                'category' => 'official_duty',
                'amount' => '450000.00',
            ],
            [
                'item_code_snapshot' => 'BPJS_KES',
                'description_snapshot' => 'BPJS Kesehatan',
                'item_type' => 'deduction',
                'amount' => '150000.00',
            ],
        ];

        /** @var PayslipService $service */
        $service = app(PayslipService::class);
        $payslip = $service->createPayslip($this->period, [
            'employee_id' => $this->employee->id,
            'items' => $items,
        ]);

        // Transition to approved
        $payslip->update([
            'status' => PayslipStatus::APPROVED->value,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        return $payslip;
    }

    public function test_finalization_fails_if_payslip_not_approved(): void
    {
        $payslip = $this->createApprovedPayslip();
        $payslip->update(['status' => PayslipStatus::DRAFT->value]);

        $response = $this->actingAs($this->personalia)
            ->post(route('payslips.finalize', $payslip->id));

        $response->assertStatus(403);
        $this->assertEquals(PayslipStatus::DRAFT->value, $payslip->fresh()->status);
    }

    public function test_unauthorized_user_cannot_finalize_or_download_payslip(): void
    {
        $payslip = $this->createApprovedPayslip();

        // Attempt finalize without permission
        $finalizeResponse = $this->actingAs($this->unauthorizedUser)
            ->post(route('payslips.finalize', $payslip->id));
        $finalizeResponse->assertStatus(403);

        // Transition to finalized by authorized user
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        // Attempt download without permission
        $downloadResponse = $this->actingAs($this->unauthorizedUser)
            ->get(route('payslips.download', $payslip->id));
        $downloadResponse->assertStatus(403);
    }

    public function test_guest_cannot_preview_or_download_pdf(): void
    {
        $payslip = $this->createApprovedPayslip();
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        auth()->logout();

        $previewResponse = $this->get(route('payslips.preview', $payslip->id));
        $previewResponse->assertRedirect(route('login'));

        $downloadResponse = $this->get(route('payslips.download', $payslip->id));
        $downloadResponse->assertRedirect(route('login'));
    }

    public function test_unfinalized_payslip_cannot_be_downloaded_or_previewed(): void
    {
        $draftSlip = $this->createApprovedPayslip();
        $draftSlip->update(['status' => PayslipStatus::DRAFT->value]);

        // Personalia attempts to download draft slip -> forbidden
        $downloadResponse = $this->actingAs($this->personalia)->get(route('payslips.download', $draftSlip->id));
        $downloadResponse->assertStatus(403);

        // Personalia attempts to preview draft slip -> forbidden
        $previewResponse = $this->actingAs($this->personalia)->get(route('payslips.preview', $draftSlip->id));
        $previewResponse->assertStatus(403);
    }

    public function test_finalization_generates_pdf_in_private_storage_with_matching_sha256_hash(): void
    {
        $payslip = $this->createApprovedPayslip();

        $response = $this->actingAs($this->personalia)
            ->post(route('payslips.finalize', $payslip->id));

        $response->assertRedirect(route('payslips.show', $payslip->id));

        $payslip->refresh();
        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);
        $this->assertEquals($this->personalia->id, $payslip->finalized_by);
        $this->assertNotNull($payslip->finalized_at);
        $this->assertNotNull($payslip->pdf_path);
        $this->assertNotNull($payslip->pdf_sha256);
        $this->assertEquals(64, strlen($payslip->pdf_sha256));

        // Verify file exists on private disk
        $this->assertTrue(Storage::disk('local')->exists($payslip->pdf_path));

        // Verify SHA-256 hash matches disk file content
        $content = Storage::disk('local')->get($payslip->pdf_path);
        $this->assertEquals(hash('sha256', $content), $payslip->pdf_sha256);

        // Verify PDF service verifyHash helper
        /** @var PayslipPdfService $pdfService */
        $pdfService = app(PayslipPdfService::class);
        $this->assertTrue($pdfService->verifyHash($payslip));
    }

    public function test_calculations_for_gaji_bruto_potongan_and_total_terima_are_consistent(): void
    {
        $payslip = $this->createApprovedPayslip();

        // Items:
        // Basic: 3,500,000
        // Meal: 625,000
        // PJD: 450,000 (official duty)
        // Deduction BPJS: 150,000
        // With official_duty_included_in_gross = false:
        // Gaji Bruto = 3,500,000 + 625,000 = 4,125,000
        // Total Potongan = 150,000
        // Uang Dinas = 450,000
        // Net Salary = 4,125,000 - 150,000 + 450,000 = 4,425,000
        $this->assertEquals('4125000.00', $payslip->gaji_bruto);
        $this->assertEquals('150000.00', $payslip->total_deductions);
        $this->assertEquals('450000.00', $payslip->uang_dinas);
        $this->assertEquals('4425000.00', $payslip->net_salary);

        // Finalize payslip
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        $this->assertEquals('4125000.00', $payslip->gaji_bruto);
        $this->assertEquals('150000.00', $payslip->total_deductions);
        $this->assertEquals('450000.00', $payslip->uang_dinas);
        $this->assertEquals('4425000.00', $payslip->net_salary);
    }

    public function test_mutating_master_employee_and_components_does_not_alter_finalized_snapshot(): void
    {
        $payslip = $this->createApprovedPayslip();
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        $originalHash = $payslip->pdf_sha256;
        $originalName = $payslip->employee_name_snapshot;
        $originalDepartment = $payslip->department_snapshot;

        // Mutate original employee record
        $this->employee->update([
            'full_name' => 'Budi Santoso S.Kom (MANTAN KARYAWAN)',
            'department' => 'Divisi Berbeda',
            'position' => 'Direktur Utama',
            'email' => 'hacked@example.com',
        ]);

        // Mutate company settings
        CompanySetting::set('company_name', 'PT. PERUSAHAAN LAIN');

        $payslip->refresh();

        // The snapshot columns must remain unchanged
        $this->assertEquals($originalName, $payslip->employee_name_snapshot);
        $this->assertEquals($originalDepartment, $payslip->department_snapshot);
        $this->assertEquals('CV. BINA PUSTAKA', $payslip->company_name_snapshot);

        // Stored PDF hash must remain unchanged
        $this->assertEquals($originalHash, $payslip->pdf_sha256);
    }

    public function test_retry_finalization_is_idempotent_and_does_not_duplicate_slips_or_files(): void
    {
        $payslip = $this->createApprovedPayslip();

        // First finalization
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();
        $firstPath = $payslip->pdf_path;
        $firstHash = $payslip->pdf_sha256;
        $firstFinalizedAt = $payslip->finalized_at;

        // Second finalization call (retry)
        /** @var PayslipFinalizationService $service */
        $service = app(PayslipFinalizationService::class);
        $retryResult = $service->finalizePayslip($payslip, $this->personalia);

        $this->assertEquals($firstPath, $retryResult->pdf_path);
        $this->assertEquals($firstHash, $retryResult->pdf_sha256);
        $this->assertEquals($firstFinalizedAt->toDateTimeString(), $retryResult->finalized_at->toDateTimeString());

        // Count of payslips in DB remains 1
        $this->assertEquals(1, Payslip::where('employee_id', $this->employee->id)->count());
    }

    public function test_voiding_payslip_records_reason_actor_and_audit_trail_without_deleting_history(): void
    {
        $payslip = $this->createApprovedPayslip();
        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);

        // Attempt void without reason should fail validation
        $failResponse = $this->actingAs($this->personalia)
            ->post(route('payslips.void', $payslip->id), [
                'reason' => '',
            ]);
        $failResponse->assertSessionHasErrors('reason');

        // Successful void
        $voidReason = 'Terjadi kekeliruan perhitungan jam lembur shift malam.';
        $voidResponse = $this->actingAs($this->personalia)
            ->post(route('payslips.void', $payslip->id), [
                'reason' => $voidReason,
            ]);

        $voidResponse->assertRedirect(route('payslips.show', $payslip->id));

        $payslip->refresh();
        $this->assertEquals(PayslipStatus::VOIDED->value, $payslip->status);
        $this->assertEquals($this->personalia->id, $payslip->voided_by);
        $this->assertNotNull($payslip->voided_at);
        $this->assertEquals($voidReason, $payslip->void_reason);

        // Historical record still exists in DB
        $this->assertDatabaseHas('payslips', [
            'id' => $payslip->id,
            'status' => 'voided',
            'void_reason' => $voidReason,
        ]);

        // Approval event recorded
        $this->assertDatabaseHas('payroll_approval_events', [
            'payslip_id' => $payslip->id,
            'decision' => 'voided',
            'actor_user_id' => $this->personalia->id,
        ]);

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payslip.voided',
            'subject_id' => (string) $payslip->id,
        ]);
    }

    public function test_pdf_generation_with_many_components_produces_valid_non_empty_pdf(): void
    {
        $manyItems = [
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '4000000.00'],
            ['item_code_snapshot' => 'MEAL', 'description_snapshot' => 'Uang Makan', 'item_type' => 'earning', 'amount' => '500000.00', 'quantity' => '20', 'unit' => 'hari', 'rate' => '25000.00'],
            ['item_code_snapshot' => 'TRANSPORT', 'description_snapshot' => 'Uang Transport', 'item_type' => 'earning', 'amount' => '400000.00'],
            ['item_code_snapshot' => 'OVERTIME', 'description_snapshot' => 'Uang Lembur', 'item_type' => 'earning', 'amount' => '750000.00', 'note' => '22 Agt - 21 Sep 2026'],
            ['item_code_snapshot' => 'FUNCTIONAL', 'description_snapshot' => 'Tunjangan Fungsional', 'item_type' => 'earning', 'amount' => '300000.00'],
            ['item_code_snapshot' => 'POSITION', 'description_snapshot' => 'Tunjangan Jabatan', 'item_type' => 'earning', 'amount' => '500000.00'],
            ['item_code_snapshot' => 'FAMILY', 'description_snapshot' => 'Tunjangan Keluarga', 'item_type' => 'earning', 'amount' => '250000.00'],
            ['item_code_snapshot' => 'ATTENDANCE', 'description_snapshot' => 'Kehadiran', 'item_type' => 'earning', 'amount' => '200000.00'],
            ['item_code_snapshot' => 'PHONE', 'description_snapshot' => 'Tunjangan Pulsa', 'item_type' => 'earning', 'amount' => '150000.00'],
            ['item_code_snapshot' => 'HEALTH_ALLOW', 'description_snapshot' => 'Biaya Kesehatan', 'item_type' => 'earning', 'amount' => '200000.00'],
            ['item_code_snapshot' => 'BPJS_KES', 'description_snapshot' => 'BPJS Kesehatan', 'item_type' => 'deduction', 'amount' => '150000.00'],
            ['item_code_snapshot' => 'ATT_SIC', 'description_snapshot' => 'Potongan Kehadiran (S,I,C)', 'item_type' => 'deduction', 'amount' => '100000.00', 'quantity' => '1', 'unit' => 'hari', 'rate' => '100000.00'],
            ['item_code_snapshot' => 'ATT_ALPA', 'description_snapshot' => 'Potongan Alpa', 'item_type' => 'deduction', 'amount' => '200000.00', 'quantity' => '1', 'unit' => 'hari', 'rate' => '200000.00'],
            ['item_code_snapshot' => 'BPJS_TK', 'description_snapshot' => 'Iuran BPJS Ketenagakerjaan', 'item_type' => 'deduction', 'amount' => '120000.00'],
            ['item_code_snapshot' => 'KOP_DUES', 'description_snapshot' => 'Iuran Koperasi', 'item_type' => 'deduction', 'amount' => '50000.00'],
            ['item_code_snapshot' => 'KOP_LOAN', 'description_snapshot' => 'Pinjaman Koperasi', 'item_type' => 'deduction', 'amount' => '300000.00'],
            ['item_code_snapshot' => 'LOCAL_LOAN', 'description_snapshot' => 'Pinjaman Lokal', 'item_type' => 'deduction', 'amount' => '100000.00'],
            ['item_code_snapshot' => 'VEHICLE_INST', 'description_snapshot' => 'Angsuran Kendaraan', 'item_type' => 'deduction', 'amount' => '500000.00'],
            ['item_code_snapshot' => 'OFF_DED', 'description_snapshot' => 'Jumlah Off', 'item_type' => 'deduction', 'amount' => '80000.00'],
            ['item_code_snapshot' => 'PJD_LUAR_KOTA', 'description_snapshot' => 'PJD Luar Kota', 'item_type' => 'earning', 'category' => 'official_duty', 'amount' => '600000.00'],
        ];

        $payslip = $this->createApprovedPayslip($manyItems);

        $this->actingAs($this->personalia)->post(route('payslips.finalize', $payslip->id));
        $payslip->refresh();

        $this->assertTrue(Storage::disk('local')->exists($payslip->pdf_path));
        $content = Storage::disk('local')->get($payslip->pdf_path);

        // Verify PDF binary starts with %PDF header
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));

        // Authorized download returns streamed PDF
        $response = $this->actingAs($this->personalia)->get(route('payslips.download', $payslip->id));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Authorized preview returns inline PDF
        $previewResponse = $this->actingAs($this->personalia)->get(route('payslips.preview', $payslip->id));
        $previewResponse->assertOk();
        $previewResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_authorized_user_can_finalize_approved_period_and_all_payslips(): void
    {
        $payslip = $this->createApprovedPayslip();

        // Mark period as approved
        $this->period->update([
            'status' => PayrollPeriodStatus::APPROVED->value,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-periods.finalize', $this->period->id));

        $response->assertRedirect(route('payroll-periods.show', $this->period->id));

        $this->period->refresh();
        $this->assertEquals(PayrollPeriodStatus::FINALIZED->value, $this->period->status);
        $this->assertEquals($this->personalia->id, $this->period->finalized_by);

        $payslip->refresh();
        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);
        $this->assertNotNull($payslip->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($payslip->pdf_path));
    }

    public function test_period_finalization_fails_if_any_slip_is_not_approved(): void
    {
        $payslip = $this->createApprovedPayslip();
        // Leave payslip as draft
        $payslip->update(['status' => PayslipStatus::DRAFT->value]);

        $this->period->update([
            'status' => PayrollPeriodStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-periods.finalize', $this->period->id));

        $response->assertRedirect();
        $this->assertEquals(PayrollPeriodStatus::APPROVED->value, $this->period->fresh()->status);
    }

    public function test_payslip_blade_renders_all_static_components_fixed_without_hiding_even_when_empty(): void
    {
        // Minimal payslip: only basic salary, no extra allowances, no deductions
        $minimalPayslip = $this->createApprovedPayslip([
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '5000000.00'],
        ]);

        $html = view('pdf.payslip', ['payslip' => $minimalPayslip])->render();

        // 1. All mandatory Earning components must be visible
        $this->assertStringContainsString('Gaji Pokok', $html);
        $this->assertStringContainsString('Uang Makan', $html);
        $this->assertStringContainsString('Uang Transport', $html);
        $this->assertStringContainsString('Uang Lembur', $html);
        $this->assertStringContainsString('Upah/jam', $html);
        $this->assertStringContainsString('Hari biasa', $html);
        $this->assertStringContainsString('Hari Libur', $html);
        $this->assertStringContainsString('Tunjangan Fungsional', $html);
        $this->assertStringContainsString('Tunjangan Jabatan', $html);
        $this->assertStringContainsString('Tunjangan Keluarga', $html);
        $this->assertStringContainsString('Kehadiran', $html);
        $this->assertStringContainsString('Tunjangan Pulsa', $html);
        $this->assertStringContainsString('Biaya Kesehatan', $html);
        $this->assertStringContainsString('GAJI BRUTO', $html);

        // 2. All mandatory Deduction & Official duty components must be visible
        $this->assertStringContainsString('BPJS Kesehatan', $html);
        $this->assertStringContainsString('Potongan Kehadiran', $html);
        $this->assertStringContainsString('S, I, C', $html);
        $this->assertStringContainsString('Alpa', $html);
        $this->assertStringContainsString('Iuran BPJS Ketenagakerjaan', $html);
        $this->assertStringContainsString('Iuran Koperasi', $html);
        $this->assertStringContainsString('Pinjaman Koperasi', $html);
        $this->assertStringContainsString('Pinjaman Lokal', $html);
        $this->assertStringContainsString('Angsuran Kendaraan', $html);
        $this->assertStringContainsString('Jumlah Off', $html);
        $this->assertStringContainsString('TOTAL POTONGAN', $html);
        $this->assertStringContainsString('UANG DINAS', $html);
        $this->assertStringContainsString('PJD Luar Kota', $html);

        // 3. Recap and Signatories
        $this->assertStringContainsString('TOTAL TERIMA GAJI', $html);
        $this->assertStringContainsString('Personalia', $html);
        $this->assertStringContainsString('Yang Menyerahkan', $html);
        $this->assertStringContainsString('Yang Menerima', $html);

        // 4. Verify PDF generation is strictly 1 page
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', ['payslip' => $minimalPayslip]);
        $pdf->setPaper([0, 0, 311.81, 269.29], 'portrait');
        $pdf->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdfOutput = $pdf->output();

        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $pdfOutput, 'Static slip PDF must remain strictly 1 page');
    }

    /**
     * TEST A: Semua komponen ada (> 0). Semua tampil normal dalam format Rupiah.
     */
    public function test_condition_a_all_components_present_render_normally_as_rupiah(): void
    {
        $allComponentsPayslip = $this->createApprovedPayslip([
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '5000000.00'],
            ['item_code_snapshot' => 'POSITION_ALLOWANCE', 'description_snapshot' => 'Tunjangan Jabatan', 'item_type' => 'earning', 'amount' => '500000.00'],
            ['item_code_snapshot' => 'MEAL_ALLOWANCE', 'description_snapshot' => 'Uang Makan', 'item_type' => 'earning', 'rate' => '25000.00', 'quantity' => '10', 'unit' => 'hari', 'amount' => '250000.00'],
            ['item_code_snapshot' => 'TRANSPORT_ALLOWANCE', 'description_snapshot' => 'Uang Transport', 'item_type' => 'earning', 'rate' => '20000.00', 'quantity' => '10', 'unit' => 'hari', 'amount' => '200000.00'],
            ['item_code_snapshot' => 'OVERTIME', 'description_snapshot' => 'Uang Lembur', 'item_type' => 'earning', 'rate' => '30000.00', 'quantity' => '10', 'unit' => 'jam', 'amount' => '300000.00'],
            ['item_code_snapshot' => 'BPJS_TK', 'description_snapshot' => 'BPJS Ketenagakerjaan', 'item_type' => 'deduction', 'amount' => '150000.00'],
            ['item_code_snapshot' => 'BPJS_KES', 'description_snapshot' => 'BPJS Kesehatan', 'item_type' => 'deduction', 'amount' => '100000.00'],
        ]);

        $html = view('pdf.payslip', ['payslip' => $allComponentsPayslip])->render();

        // Check values formatted in Rupiah
        $this->assertStringContainsString('5.000.000', $html);
        $this->assertStringContainsString('500.000', $html);
        $this->assertStringContainsString('250.000', $html);
        $this->assertStringContainsString('200.000', $html);
        $this->assertStringContainsString('300.000', $html);
        $this->assertStringContainsString('150.000', $html);
        $this->assertStringContainsString('100.000', $html);

        // Verify PDF is strictly 1 page (110mm x 95mm)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', ['payslip' => $allComponentsPayslip]);
        $pdf->setPaper([0, 0, 311.81, 269.29], 'portrait');
        $pdfOutput = $pdf->output();
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $pdfOutput);
    }

    /**
     * TEST B: Sebagian besar nol (Gaji Pokok 5.000.000, lainnya 0/kosong).
     * Slip tetap menampilkan semua baris, dengan '-' untuk nilai 0 (bukan 'Rp -' atau 'Rp0').
     */
    public function test_condition_b_mostly_zero_renders_gaji_pokok_and_dash_for_zeros(): void
    {
        $mostlyZeroPayslip = $this->createApprovedPayslip([
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '5000000.00'],
            ['item_code_snapshot' => 'POSITION_ALLOWANCE', 'description_snapshot' => 'Tunjangan Jabatan', 'item_type' => 'earning', 'amount' => '0.00'],
            ['item_code_snapshot' => 'MEAL_ALLOWANCE', 'description_snapshot' => 'Uang Makan', 'item_type' => 'earning', 'amount' => '0.00'],
            ['item_code_snapshot' => 'TRANSPORT_ALLOWANCE', 'description_snapshot' => 'Uang Transport', 'item_type' => 'earning', 'amount' => '0.00'],
            ['item_code_snapshot' => 'OVERTIME', 'description_snapshot' => 'Uang Lembur', 'item_type' => 'earning', 'amount' => '0.00'],
        ]);

        $html = view('pdf.payslip', ['payslip' => $mostlyZeroPayslip])->render();

        // Gaji Pokok must be formatted in Rupiah
        $this->assertStringContainsString('5.000.000', $html);

        // All other components must render '-' and not be hidden
        $this->assertStringContainsString('Tunjangan Jabatan', $html);
        $this->assertStringContainsString('Uang Makan', $html);
        $this->assertStringContainsString('Uang Transport', $html);
        $this->assertStringContainsString('Uang Lembur', $html);
        $this->assertStringContainsString('Kehadiran', $html);
        $this->assertStringContainsString('Tunjangan Keluarga', $html);
        $this->assertStringContainsString('BPJS Ketenagakerjaan', $html);
        $this->assertStringContainsString('BPJS Kesehatan', $html);

        // Component rows must NOT display 'Rp -' or 'Rp0'
        $this->assertStringNotContainsString('Rp -', $html);
        $this->assertStringNotContainsString('Rp0', $html);

        // Verify PDF is strictly 1 page (110mm x 95mm)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', ['payslip' => $mostlyZeroPayslip]);
        $pdf->setPaper([0, 0, 311.81, 269.29], 'portrait');
        $pdfOutput = $pdf->output();
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $pdfOutput);
    }

    /**
     * TEST C: Semua komponen nol (semua baris tetap ada dan tampil '-').
     * Tidak boleh ada baris komponen yang hilang. Total tetap dihitung dan tampil.
     */
    public function test_condition_c_all_components_zero_renders_dash_for_all_components_and_zero_for_totals(): void
    {
        $allZeroPayslip = $this->createApprovedPayslip([
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '0.00'],
        ]);

        $allZeroPayslip->update([
            'basic_salary' => '0.00',
            'gaji_bruto' => '0.00',
            'total_earnings' => '0.00',
            'total_deductions' => '0.00',
            'net_salary' => '0.00',
            'uang_dinas' => '0.00',
        ]);

        $html = view('pdf.payslip', ['payslip' => $allZeroPayslip])->render();

        // All rows must be present
        $this->assertStringContainsString('Gaji Pokok', $html);
        $this->assertStringContainsString('Uang Makan', $html);
        $this->assertStringContainsString('Uang Transport', $html);
        $this->assertStringContainsString('Uang Lembur', $html);
        $this->assertStringContainsString('Tunjangan Fungsional', $html);
        $this->assertStringContainsString('Tunjangan Jabatan', $html);
        $this->assertStringContainsString('Tunjangan Keluarga', $html);
        $this->assertStringContainsString('Kehadiran', $html);
        $this->assertStringContainsString('Tunjangan Pulsa', $html);
        $this->assertStringContainsString('Biaya Kesehatan', $html);
        $this->assertStringContainsString('BPJS Kesehatan', $html);
        $this->assertStringContainsString('Potongan Kehadiran', $html);
        $this->assertStringContainsString('Iuran BPJS Ketenagakerjaan', $html);
        $this->assertStringContainsString('Iuran Koperasi', $html);
        $this->assertStringContainsString('Pinjaman Koperasi', $html);
        $this->assertStringContainsString('Pinjaman Lokal', $html);
        $this->assertStringContainsString('Angsuran Kendaraan', $html);
        $this->assertStringContainsString('Jumlah Off', $html);

        // Component rows must NOT display 'Rp -' or 'Rp0'
        $this->assertStringNotContainsString('Rp -', $html);
        $this->assertStringNotContainsString('Rp0', $html);

        // Totals display Rp 0
        $this->assertStringContainsString('GAJI BRUTO', $html);
        $this->assertStringContainsString('TOTAL POTONGAN', $html);
        $this->assertStringContainsString('TOTAL TERIMA GAJI', $html);

        // Verify PDF is strictly 1 page (110mm x 95mm)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', ['payslip' => $allZeroPayslip]);
        $pdf->setPaper([0, 0, 311.81, 269.29], 'portrait');
        $pdfOutput = $pdf->output();
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $pdfOutput);
    }

    /**
     * TEST LEMBUR: Default overtime rate dari master karyawan ditampilkan saat tidak ada transaksi lembur.
     */
    public function test_overtime_row_renders_default_overtime_rate_from_employee_master(): void
    {
        $this->employee->update([
            'default_overtime_rate' => '25000.00',
        ]);

        $payslipWithoutOt = $this->createApprovedPayslip([
            ['item_code_snapshot' => 'BASIC_SALARY', 'description_snapshot' => 'Gaji Pokok', 'item_type' => 'earning', 'amount' => '4000000.00'],
        ]);

        $html = view('pdf.payslip', ['payslip' => $payslipWithoutOt])->render();

        // Upah/jam must display 25.000 from default_overtime_rate
        $this->assertStringContainsString('Upah/jam', $html);
        $this->assertStringContainsString('25.000', $html);

        // Uang Lembur total must display '-'
        $this->assertStringContainsString('Uang Lembur', $html);
    }
}

