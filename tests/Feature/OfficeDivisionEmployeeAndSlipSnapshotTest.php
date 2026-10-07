<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Office;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\User;
use App\Services\PayslipPdfService;
use App\Services\PayslipService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeDivisionEmployeeAndSlipSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected User $personalia;

    protected Office $officeA;

    protected Office $officeB;

    protected Division $divisionA;

    protected Division $divisionB;

    protected Position $positionStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->personalia = User::where('email', 'personalia@example.com')->first();

        $this->officeA = Office::create([
            'name' => 'CV. BINA PUSTAKA',
            'code' => 'BP',
            'address' => "Jl. Raya Serang Km 12\nTangerang, Banten",
            'phone' => '021-5551234',
            'email' => 'info@binapustaka.co.id',
            'is_active' => true,
        ]);

        $this->officeB = Office::create([
            'name' => 'PT. LINTAS SAMUDRA',
            'code' => 'LS',
            'address' => 'Jl. Pelabuhan No. 9, Jakarta Utara',
            'phone' => '021-8884321',
            'email' => 'contact@samudra.co.id',
            'is_active' => true,
        ]);

        $this->divisionA = Division::create([
            'office_id' => $this->officeA->id,
            'name' => 'Personalia',
            'code' => 'BP-HR',
            'is_active' => true,
        ]);

        $this->divisionB = Division::create([
            'office_id' => $this->officeB->id,
            'name' => 'Operasional Logistik',
            'code' => 'LS-OPS',
            'is_active' => true,
        ]);

        $this->positionStaff = Position::create([
            'name' => 'Staff Administrasi',
            'code' => 'ADM-STF',
            'is_active' => true,
        ]);
    }

    public function test_can_create_employee_with_office_division_and_position(): void
    {
        $payload = [
            'employee_number' => 'EMP-TEST-001',
            'full_name' => 'Agus Heryana',
            'office_id' => $this->officeA->id,
            'division_id' => $this->divisionA->id,
            'position_id' => $this->positionStaff->id,
            'email' => 'agus@binapustaka.co.id',
            'phone' => '081234567890',
            'hire_date' => '2024-01-15',
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
            'tunjangan_kehadiran' => 500000,
            'tunjangan_keluarga' => 250000,
            'tunjangan_jabatan' => 300000,
            'uang_makan_per_hari' => 35000,
            'uang_transportasi_per_hari' => 25000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 150000,
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $payload);
        $response->assertRedirect('/employees');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-TEST-001',
            'full_name' => 'Agus Heryana',
            'office_id' => $this->officeA->id,
            'division_id' => $this->divisionA->id,
            'position_id' => $this->positionStaff->id,
            'position' => 'Staff Administrasi',
        ]);
    }

    public function test_backend_rejects_division_belonging_to_another_office(): void
    {
        $payload = [
            'employee_number' => 'EMP-MISMATCH-01',
            'full_name' => 'Budi Santoso',
            'office_id' => $this->officeA->id,
            'division_id' => $this->divisionB->id, // Belongs to officeB!
            'position_id' => $this->positionStaff->id,
            'email' => 'budi@binapustaka.co.id',
            'phone' => '081234567899',
            'hire_date' => '2024-01-15',
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
        ];

        $response = $this->actingAs($this->personalia)->post('/employees', $payload);
        $response->assertSessionHasErrors(['division_id']);
    }

    public function test_payslip_captures_office_identity_snapshots_and_remains_immutable(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-HIST-001',
            'full_name' => 'Agus Heryana',
            'office_id' => $this->officeA->id,
            'division_id' => $this->divisionA->id,
            'position_id' => $this->positionStaff->id,
            'position' => $this->positionStaff->name,
            'email' => 'agus@binapustaka.co.id',
            'phone' => '081234567890',
            'employment_status' => 'active',
            'gaji_pokok' => 4500000,
            'tunjangan_kehadiran' => 300000,
            'tunjangan_keluarga' => 0,
            'tunjangan_jabatan' => 0,
            'uang_makan_per_hari' => 20000,
            'uang_transportasi_per_hari' => 20000,
            'bpjs_ketenagakerjaan_enabled' => false,
            'bpjs_ketenagakerjaan_amount' => 0,
        ]);

        $period = PayrollPeriod::create([
            'period_code' => 'PAY-2026-09',
            'period_month' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'working_days' => 25,
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $payslipService = app(PayslipService::class);
        $payslip = $payslipService->createPayslip($period, [
            'employee_id' => $employee->id,
            'items' => [
                [
                    'name' => 'Gaji Pokok',
                    'component_type' => 'fixed',
                    'category' => 'earning',
                    'amount' => 4500000,
                ],
                [
                    'name' => 'Tunjangan Kehadiran',
                    'component_type' => 'fixed',
                    'category' => 'earning',
                    'amount' => 300000,
                ],
            ],
        ]);

        // Verify initial snapshot
        $this->assertEquals('CV. BINA PUSTAKA', $payslip->office_name_snapshot);
        $this->assertEquals('BP', $payslip->office_code_snapshot);
        $this->assertStringContainsString('Jl. Raya Serang Km 12', $payslip->office_address_snapshot);
        $this->assertEquals('021-5551234', $payslip->office_phone_snapshot);
        $this->assertEquals('info@binapustaka.co.id', $payslip->office_email_snapshot);
        $this->assertEquals('Personalia', $payslip->division_name_snapshot);
        $this->assertEquals('Staff Administrasi', $payslip->position_name_snapshot);

        // Later: Master Office is changed to a new name & address
        $this->officeA->update([
            'name' => 'CV. BINA PUSTAKA MAKMUR',
            'address' => 'Jl. Baru No. 99, Serang Baru',
            'phone' => '021-9998888',
        ]);

        // Master Division is renamed
        $this->divisionA->update([
            'name' => 'Human Capital Operations',
        ]);

        // Master Position is renamed
        $this->positionStaff->update([
            'name' => 'Lead Administrator',
        ]);

        // Refresh payslip from database
        $payslip->refresh();

        // Historical snapshot MUST NOT change
        $this->assertEquals('CV. BINA PUSTAKA', $payslip->office_name_snapshot);
        $this->assertStringContainsString('Jl. Raya Serang Km 12', $payslip->office_address_snapshot);
        $this->assertEquals('021-5551234', $payslip->office_phone_snapshot);
        $this->assertEquals('Personalia', $payslip->division_name_snapshot);
        $this->assertEquals('Staff Administrasi', $payslip->position_name_snapshot);
    }

    public function test_payslip_pdf_has_exact_110x95_mm_size_and_1_page(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-PDF-001',
            'full_name' => 'Muhammad Rifqi Ramadhan',
            'office_id' => $this->officeA->id,
            'division_id' => $this->divisionA->id,
            'position_id' => $this->positionStaff->id,
            'position' => $this->positionStaff->name,
            'email' => 'rifqi@binapustaka.co.id',
            'phone' => '081234567891',
            'employment_status' => 'active',
            'gaji_pokok' => 6000000,
            'tunjangan_kehadiran' => 500000,
            'tunjangan_keluarga' => 300000,
            'tunjangan_jabatan' => 450000,
            'uang_makan_per_hari' => 40000,
            'uang_transportasi_per_hari' => 30000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 180000,
        ]);

        $period = PayrollPeriod::create([
            'period_code' => 'PAY-2026-10',
            'period_month' => 'Oktober 2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'working_days' => 25,
            'status' => 'draft',
            'created_by' => $this->personalia->id,
        ]);

        $payslipService = app(PayslipService::class);
        $payslip = $payslipService->createPayslip($period, [
            'employee_id' => $employee->id,
            'items' => [
                [
                    'name' => 'Gaji Pokok',
                    'component_type' => 'fixed',
                    'category' => 'earning',
                    'amount' => 6000000,
                ],
                [
                    'name' => 'Tunjangan Kehadiran',
                    'component_type' => 'fixed',
                    'category' => 'earning',
                    'amount' => 500000,
                ],
                [
                    'name' => 'Perjalanan Dinas',
                    'component_type' => 'fixed',
                    'category' => 'earning',
                    'amount' => 750000,
                ],
                [
                    'name' => 'BPJS Ketenagakerjaan',
                    'component_type' => 'fixed',
                    'category' => 'deduction',
                    'amount' => 180000,
                ],
            ],
        ]);

        $pdfService = app(PayslipPdfService::class);
        $pdfService->generatePdf($payslip);
        $pdfContent = \Illuminate\Support\Facades\Storage::disk('local')->get($payslip->pdf_path);

        $this->assertNotEmpty($pdfContent);

        // 1. Verify MediaBox is 110mm x 95mm (311.81 x 269.29 pt)
        $this->assertMatchesRegularExpression(
            '/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+311\.81[0-9]*\s+269\.29[0-9]*\s*\]/',
            $pdfContent,
            'PDF physical size must be 110 mm x 95 mm (311.81 x 269.29 pt)'
        );

        // 2. Verify Page Count is strictly 1
        $this->assertMatchesRegularExpression(
            '/\/Count\s+1\b/',
            $pdfContent,
            'PDF must be strictly 1 page'
        );

        // 3. Verify text content in rendered view
        $html = view('pdf.payslip', ['payslip' => $payslip])->render();
        $this->assertStringContainsString('CV. BINA PUSTAKA', $html);
        $this->assertStringContainsString('SLIP GAJI KARYAWAN', $html);
        $this->assertStringContainsString('Muhammad Rifqi Ramadhan', $html);
        $this->assertStringContainsString('Staff Administrasi', $html);
        $this->assertStringContainsString('Personalia', $html);
    }
}
