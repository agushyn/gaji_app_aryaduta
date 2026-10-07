<?php

namespace Tests\Feature;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Jobs\SendPayslipEmailJob;
use App\Jobs\SendPayslipWhatsAppJob;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkDeliveryAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected PayrollPeriod $period;

    protected Employee $employee1;

    protected Employee $employee2;

    protected Employee $employee3;

    protected Payslip $payslip1;

    protected Payslip $payslip2;

    protected Payslip $payslip3;

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

        // Plain role without permissions
        $plainRole = Role::create([
            'name' => 'plain_staff',
            'label' => 'Plain Staff',
            'description' => 'Staff without permissions',
        ]);
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->roles()->attach($plainRole);

        // Create 3 employees with various contact validities
        // Employee 1: valid email and valid WhatsApp
        $this->employee1 = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff Logistik',
            'email' => 'budi.santoso@example.com',
            'phone' => '+6281234567890',
            'hire_date' => '2023-01-15',
            'employment_status' => 'active',
        ]);

        // Employee 2: valid email, but missing/invalid phone
        $this->employee2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Siti Rahma',
            'department' => 'Keuangan',
            'position' => 'Staff Accounting',
            'email' => 'siti.rahma@example.com',
            'phone' => 'invalid-phone-string',
            'hire_date' => '2023-03-01',
            'employment_status' => 'active',
        ]);

        // Employee 3: invalid email, but valid phone
        $this->employee3 = Employee::create([
            'employee_number' => 'EMP-003',
            'full_name' => 'Agus Hidayat',
            'department' => 'Operasional',
            'position' => 'Koordinator Lapangan',
            'email' => 'not-an-email',
            'phone' => '+6289876543210',
            'hire_date' => '2023-05-10',
            'employment_status' => 'active',
        ]);

        // Finalized payroll period
        $this->period = PayrollPeriod::create([
            'period_code' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => PayrollPeriodStatus::FINALIZED->value,
            'is_current' => true,
            'created_by' => $this->personalia->id,
        ]);

        // Create dummy PDF
        Storage::disk('local')->put('payslips/2026-09/dummy.pdf', '%PDF-1.4 Fake Content');

        // Create 3 finalized payslips
        $this->payslip1 = Payslip::create([
            'slip_number' => 'SLIP-202609-001',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee1->id,
            'status' => PayslipStatus::FINALIZED->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_number_snapshot' => $this->employee1->employee_number,
            'employee_name_snapshot' => $this->employee1->full_name,
            'department_snapshot' => $this->employee1->department,
            'position_snapshot' => $this->employee1->position,
            'email_snapshot' => $this->employee1->email,
            'phone_snapshot' => $this->employee1->phone,
            'basic_salary' => 5000000.00,
            'gaji_pokok' => 5000000.00,
            'total_earnings' => 5000000.00,
            'total_deductions' => 0.00,
            'net_salary' => 5000000.00,
            'currency' => 'IDR',
            'created_by' => $this->personalia->id,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
            'finalized_by' => $this->personalia->id,
            'finalized_at' => now(),
            'pdf_path' => 'payslips/2026-09/dummy.pdf',
            'pdf_hash' => hash('sha256', '%PDF-1.4 Fake Content'),
        ]);

        $this->payslip2 = Payslip::create([
            'slip_number' => 'SLIP-202609-002',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee2->id,
            'status' => PayslipStatus::FINALIZED->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_number_snapshot' => $this->employee2->employee_number,
            'employee_name_snapshot' => $this->employee2->full_name,
            'department_snapshot' => $this->employee2->department,
            'position_snapshot' => $this->employee2->position,
            'email_snapshot' => $this->employee2->email,
            'phone_snapshot' => $this->employee2->phone,
            'basic_salary' => 6000000.00,
            'gaji_pokok' => 6000000.00,
            'total_earnings' => 6000000.00,
            'total_deductions' => 0.00,
            'net_salary' => 6000000.00,
            'currency' => 'IDR',
            'created_by' => $this->personalia->id,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
            'finalized_by' => $this->personalia->id,
            'finalized_at' => now(),
            'pdf_path' => 'payslips/2026-09/dummy.pdf',
            'pdf_hash' => hash('sha256', '%PDF-1.4 Fake Content'),
        ]);

        $this->payslip3 = Payslip::create([
            'slip_number' => 'SLIP-202609-003',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee3->id,
            'status' => PayslipStatus::FINALIZED->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_number_snapshot' => $this->employee3->employee_number,
            'employee_name_snapshot' => $this->employee3->full_name,
            'department_snapshot' => $this->employee3->department,
            'position_snapshot' => $this->employee3->position,
            'email_snapshot' => $this->employee3->email,
            'phone_snapshot' => $this->employee3->phone,
            'basic_salary' => 4500000.00,
            'gaji_pokok' => 4500000.00,
            'total_earnings' => 4500000.00,
            'total_deductions' => 0.00,
            'net_salary' => 4500000.00,
            'currency' => 'IDR',
            'created_by' => $this->personalia->id,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
            'finalized_by' => $this->personalia->id,
            'finalized_at' => now(),
            'pdf_path' => 'payslips/2026-09/dummy.pdf',
            'pdf_hash' => hash('sha256', '%PDF-1.4 Fake Content'),
        ]);
    }

    public function test_cannot_access_bulk_delivery_without_permission(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $response = $this->get(route('deliveries.bulk.create'));
        $response->assertStatus(403);
    }

    public function test_bulk_delivery_page_renders_with_finalized_periods(): void
    {
        $this->actingAs($this->personalia);

        $response = $this->get(route('deliveries.bulk.create'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Deliveries/Bulk')
            ->has('periods')
            ->has('preview')
        );
    }

    public function test_bulk_preview_endpoint_returns_accurate_recipient_data(): void
    {
        $this->actingAs($this->personalia);

        $response = $this->postJson(route('deliveries.bulk.preview'), [
            'period_id' => $this->period->id,
            'channel' => 'both',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'summary' => [
                'total_payslips' => 3,
                'ready_email_count' => 2,     // emp1 and emp2 have valid email
                'ready_whatsapp_count' => 2,  // emp1 and emp3 have valid phone
                'missing_email_count' => 1,   // emp3 has invalid email
                'missing_whatsapp_count' => 1, // emp2 has invalid phone
            ],
        ]);
    }

    public function test_bulk_dispatch_creates_separate_jobs_per_slip_and_channel(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        $response = $this->post(route('deliveries.bulk.store'), [
            'period_id' => $this->period->id,
            'channel' => 'both',
            'payslip_ids' => [$this->payslip1->id, $this->payslip2->id, $this->payslip3->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Email jobs: payslip1 and payslip2 have valid email (payslip3 skipped)
        Queue::assertPushed(SendPayslipEmailJob::class, 2);

        // WhatsApp jobs: payslip1 and payslip3 have valid phone (payslip2 skipped)
        Queue::assertPushed(SendPayslipWhatsAppJob::class, 2);

        // Check deliveries table
        $this->assertDatabaseHas('payslip_deliveries', [
            'payslip_id' => $this->payslip1->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'status' => DeliveryStatus::QUEUED->value,
        ]);

        $this->assertDatabaseHas('payslip_deliveries', [
            'payslip_id' => $this->payslip1->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'status' => DeliveryStatus::QUEUED->value,
        ]);
    }

    public function test_bulk_dispatch_channel_isolation(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        // Dispatch email only
        $this->post(route('deliveries.bulk.store'), [
            'period_id' => $this->period->id,
            'channel' => 'email',
            'payslip_ids' => [$this->payslip1->id],
        ]);

        Queue::assertPushed(SendPayslipEmailJob::class, 1);
        Queue::assertNotPushed(SendPayslipWhatsAppJob::class);
    }

    public function test_selective_retry_failed_deliveries_idempotently(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        // Create 1 sent delivery (must NOT be retried)
        $sentDelivery = PayslipDelivery::create([
            'payslip_id' => $this->payslip1->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'budi.santoso@example.com',
            'status' => DeliveryStatus::SENT->value,
            'idempotency_key' => 'key_sent_001',
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        // Create 2 failed deliveries (must be retried)
        $failedDelivery1 = PayslipDelivery::create([
            'payslip_id' => $this->payslip2->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'siti.rahma@example.com',
            'status' => DeliveryStatus::FAILED->value,
            'idempotency_key' => 'key_failed_001',
            'attempt_count' => 1,
            'last_error_code' => 'SMTP_ERROR',
        ]);

        $failedDelivery2 = PayslipDelivery::create([
            'payslip_id' => $this->payslip3->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6289876543210',
            'status' => DeliveryStatus::FAILED->value,
            'idempotency_key' => 'key_failed_002',
            'attempt_count' => 1,
            'last_error_code' => 'GATEWAY_TIMEOUT',
        ]);

        $response = $this->post(route('deliveries.retry-failed'), [
            'period_id' => $this->period->id,
            'channel' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Sent delivery remains untouched
        $sentDelivery->refresh();
        $this->assertEquals(DeliveryStatus::SENT->value, $sentDelivery->status);

        // Failed deliveries reset to queued
        $failedDelivery1->refresh();
        $this->assertEquals(DeliveryStatus::QUEUED->value, $failedDelivery1->status);

        $failedDelivery2->refresh();
        $this->assertEquals(DeliveryStatus::QUEUED->value, $failedDelivery2->status);

        // 1 email job and 1 whatsapp job pushed
        Queue::assertPushed(SendPayslipEmailJob::class, 1);
        Queue::assertPushed(SendPayslipWhatsAppJob::class, 1);
    }

    public function test_delivery_reports_page_accessible_with_permission(): void
    {
        $this->actingAs($this->auditor);

        $response = $this->get(route('reports.deliveries.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Deliveries')
            ->has('summary')
            ->has('deliveries')
            ->where('canViewFinancial', false) // Auditor has no financial view permission
        );
    }

    public function test_delivery_reports_filters_by_parameters(): void
    {
        $this->actingAs($this->personalia);

        // Create delivered record
        PayslipDelivery::create([
            'payslip_id' => $this->payslip1->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'budi.santoso@example.com',
            'status' => DeliveryStatus::DELIVERED->value,
            'idempotency_key' => 'rpt_del_001',
            'attempt_count' => 1,
            'delivered_at' => now(),
        ]);

        // Create failed record
        PayslipDelivery::create([
            'payslip_id' => $this->payslip2->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6289876543210',
            'status' => DeliveryStatus::FAILED->value,
            'idempotency_key' => 'rpt_del_002',
            'attempt_count' => 1,
            'last_error_summary' => 'Invalid number',
        ]);

        // Filter by channel: email
        $response = $this->get(route('reports.deliveries.index', ['channel' => 'email']));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('summary.email_total', 1)
            ->where('summary.whatsapp_total', 0)
        );

        // Filter by status: failed
        $responseFailed = $this->get(route('reports.deliveries.index', ['status' => 'failed']));
        $responseFailed->assertStatus(200);
        $responseFailed->assertInertia(fn ($page) => $page
            ->where('summary.failed', 1)
        );
    }

    public function test_nominal_export_is_restricted_without_financial_permission(): void
    {
        // Setup a delivery record
        PayslipDelivery::create([
            'payslip_id' => $this->payslip1->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'budi.santoso@example.com',
            'status' => DeliveryStatus::SENT->value,
            'idempotency_key' => 'export_key_001',
            'attempt_count' => 1,
        ]);

        // 1. Auditor export (NO financial view permission)
        $this->actingAs($this->auditor);
        $auditorResponse = $this->get(route('reports.deliveries.export'));
        $auditorResponse->assertStatus(200);

        $auditorContent = $auditorResponse->streamedContent();
        $this->assertStringContainsString('[TERBATAS]', $auditorContent);
        $this->assertStringNotContainsString('5000000', $auditorContent);

        // 2. Personalia export (WITH financial view permission)
        $this->actingAs($this->personalia);
        $personaliaResponse = $this->get(route('reports.deliveries.export'));
        $personaliaResponse->assertStatus(200);

        $personaliaContent = $personaliaResponse->streamedContent();
        $this->assertStringNotContainsString('[TERBATAS]', $personaliaContent);
        $this->assertStringContainsString('5000000', $personaliaContent);
    }

    public function test_payroll_process_queue_artisan_command_executes_safely(): void
    {
        $exitCode = Artisan::call('payroll:process-queue', [
            '--limit' => 5,
            '--timeout' => 30,
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_audit_logs_record_bulk_operations(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        $this->post(route('deliveries.bulk.store'), [
            'period_id' => $this->period->id,
            'channel' => 'both',
            'payslip_ids' => [$this->payslip1->id],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deliveries.bulk_dispatched',
        ]);
    }
}
