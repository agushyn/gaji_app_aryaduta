<?php

namespace Tests\Feature;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Jobs\SendPayslipEmailJob;
use App\Mail\PayslipEmail;
use App\Mail\SmtpTestPingEmail;
use App\Models\DeliveryAttempt;
use App\Models\Employee;
use App\Models\IntegrationSetting;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DeliveryService;
use App\Services\PayslipPdfService;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RbacSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected Employee $employee;

    protected PayrollPeriod $period;

    protected Payslip $finalizedPayslip;

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

        // User without delivery / settings permissions
        $plainRole = Role::create([
            'name' => 'plain_staff',
            'label' => 'Plain Staff',
            'description' => 'Staff without permissions',
        ]);
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->roles()->attach($plainRole);

        // Employee with valid email
        $this->employee = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff Logistik',
            'email' => 'budi.santoso@example.com',
            'phone' => '+6281234567890',
            'hire_date' => '2023-01-15',
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

        $gajiPokok = PayComponent::where('code', 'GP')->first();

        // Finalized payslip
        $this->finalizedPayslip = Payslip::create([
            'slip_number' => 'SLIP-202609-001',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => PayslipStatus::FINALIZED->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_name_snapshot' => $this->employee->full_name,
            'employee_number_snapshot' => $this->employee->employee_number,
            'department_snapshot' => $this->employee->department,
            'position_snapshot' => $this->employee->position,
            'email_snapshot' => $this->employee->email,
            'phone_snapshot' => $this->employee->phone,
            'basic_salary' => 5000000.00,
            'total_earnings' => 5000000.00,
            'total_deductions' => 0.00,
            'net_salary' => 5000000.00,
            'created_by' => $this->personalia->id,
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
            'finalized_by' => $this->personalia->id,
            'finalized_at' => now(),
            'pdf_path' => 'payslips/2026-09/slip_SLIP-202609-001.pdf',
            'pdf_hash' => hash('sha256', 'dummy-pdf-content'),
        ]);

        // Generate dummy storage file for PDF
        Storage::disk('local')->put($this->finalizedPayslip->pdf_path, 'PDF_TEST_CONTENT');
    }

    public function test_unfinalized_payslip_email_dispatch_is_rejected(): void
    {
        Queue::fake();

        // Create second employee for draft payslip
        $emp2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Siti Rahma',
            'department' => 'Keuangan',
            'position' => 'Staff Accounting',
            'email' => 'siti.rahma@example.com',
            'phone' => '+6281234567899',
            'hire_date' => '2023-02-01',
            'employment_status' => 'active',
        ]);

        // Create draft payslip
        $draftPayslip = Payslip::create([
            'slip_number' => 'SLIP-202609-002',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $emp2->id,
            'status' => PayslipStatus::DRAFT->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_name_snapshot' => $emp2->full_name,
            'employee_number_snapshot' => $emp2->employee_number,
            'department_snapshot' => $emp2->department,
            'position_snapshot' => $emp2->position,
            'basic_salary' => 4000000,
            'total_earnings' => 4000000,
            'total_deductions' => 0,
            'net_salary' => 4000000,
            'email_snapshot' => 'siti.rahma@example.com',
            'created_by' => $this->personalia->id,
        ]);

        $response = $this->actingAs($this->personalia)
            ->post(route('payslips.deliveries.email', $draftPayslip->id));

        $response->assertSessionHas('error');
        Queue::assertNotPushed(SendPayslipEmailJob::class);
        $this->assertDatabaseMissing('payslip_deliveries', [
            'payslip_id' => $draftPayslip->id,
        ]);
    }

    public function test_finalized_payslip_email_delivery_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->personalia)
            ->post(route('payslips.deliveries.email', $this->finalizedPayslip->id));

        $response->assertSessionHas('success');

        Queue::assertPushed(SendPayslipEmailJob::class, function ($job) {
            $delivery = PayslipDelivery::find($job->deliveryId);

            return $delivery && $delivery->payslip_id === $this->finalizedPayslip->id;
        });

        $this->assertDatabaseHas('payslip_deliveries', [
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'budi.santoso@example.com',
            'status' => DeliveryStatus::QUEUED->value,
        ]);
    }

    public function test_job_sends_email_with_pdf_attachment_and_updates_status_to_sent(): void
    {
        Mail::fake();

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => $this->finalizedPayslip->email_snapshot,
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'test_key_001',
            'attempt_count' => 0,
        ]);

        $job = new SendPayslipEmailJob($delivery->id);
        $job->handle(
            app(PayslipPdfService::class),
            app(AuditLogService::class)
        );

        // Assert mail was sent to recipient with mailable
        Mail::assertSent(PayslipEmail::class, function (PayslipEmail $mail) {
            return $mail->hasTo('budi.santoso@example.com')
                && $mail->payslip->id === $this->finalizedPayslip->id;
        });

        // Delivery marked as sent
        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::SENT->value, $delivery->status);
        $this->assertEquals(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->sent_at);

        // Attempt record created with sent status
        $this->assertDatabaseHas('delivery_attempts', [
            'payslip_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => DeliveryStatus::SENT->value,
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_dispatch(): void
    {
        Queue::fake();

        // Create already sent delivery
        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => $this->finalizedPayslip->email_snapshot,
            'status' => DeliveryStatus::SENT->value,
            'idempotency_key' => "email_{$this->finalizedPayslip->id}_{$this->finalizedPayslip->payroll_period_id}_".md5('budi.santoso@example.com'),
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        $service = app(DeliveryService::class);
        $result = $service->queueEmailDelivery($this->finalizedPayslip, $this->personalia);

        // Must not re-queue job
        Queue::assertNotPushed(SendPayslipEmailJob::class);
        $this->assertEquals($delivery->id, $result->id);
        $this->assertEquals(DeliveryStatus::SENT->value, $result->status);
    }

    public function test_job_records_attempt_and_sanitizes_errors_on_smtp_failure(): void
    {
        // Mock mail failure
        Mail::shouldReceive('mailer->to->send')
            ->andThrow(new Exception('Connection failed: password=SuperSecretPassword123&api_key=XYZ987654321'));

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => $this->finalizedPayslip->email_snapshot,
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'test_key_fail_001',
            'attempt_count' => 0,
        ]);

        $job = new SendPayslipEmailJob($delivery->id);

        try {
            $job->handle(
                app(PayslipPdfService::class),
                app(AuditLogService::class)
            );
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Connection failed', $e->getMessage());
        }

        $delivery->refresh();
        $this->assertEquals(1, $delivery->attempt_count);

        // Check sanitized error in delivery_attempts table
        $attempt = DeliveryAttempt::where('payslip_delivery_id', $delivery->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals(DeliveryStatus::FAILED->value, $attempt->status);
        $this->assertStringNotContainsString('SuperSecretPassword123', $attempt->error_summary);
        $this->assertStringContainsString('password=***', $attempt->error_summary);
        $this->assertStringNotContainsString('XYZ987654321', $attempt->error_summary);
        $this->assertStringContainsString('api_key=***', $attempt->error_summary);
    }

    public function test_retry_delivery_increments_attempt_count_without_duplicating_delivery_record(): void
    {
        Queue::fake();

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => $this->finalizedPayslip->email_snapshot,
            'status' => DeliveryStatus::FAILED->value,
            'idempotency_key' => 'test_retry_001',
            'attempt_count' => 1,
            'last_error_code' => 'SMTP_ERROR',
            'last_error_summary' => 'Koneksi timeout',
        ]);

        $response = $this->actingAs($this->personalia)
            ->post(route('deliveries.retry', $delivery->id));

        $response->assertSessionHas('success');

        Queue::assertPushed(SendPayslipEmailJob::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });

        // Ensure no new delivery record was created (count remains 1)
        $this->assertEquals(1, PayslipDelivery::where('payslip_id', $this->finalizedPayslip->id)->count());

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::QUEUED->value, $delivery->status);
    }

    public function test_batch_period_email_delivery_queues_all_finalized_payslips(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-periods.deliveries.email', $this->period->id));

        $response->assertSessionHas('success');

        Queue::assertPushed(SendPayslipEmailJob::class);
        $this->assertDatabaseHas('payslip_deliveries', [
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => 'budi.santoso@example.com',
            'status' => DeliveryStatus::QUEUED->value,
        ]);
    }

    public function test_email_settings_mask_secret_and_do_not_expose_password_to_frontend(): void
    {
        // Store encrypted SMTP password in database
        IntegrationSetting::updateOrCreate(
            ['provider' => 'smtp'],
            [
                'is_active' => true,
                'endpoint' => 'smtp.mailtrap.io',
                'sender_identifier' => 'noreply@binapustaka.com',
                'secret_encrypted' => Crypt::encryptString('VerySecretPassword999'),
                'config_json' => [
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'test_user',
                    'from_name' => 'CV. Bina Pustaka',
                ],
            ]
        );

        $response = $this->actingAs($this->superAdmin)
            ->get(route('settings.email.edit'));

        $response->assertOk();

        // Ensure plaintext password is NOT in response content
        $response->assertDontSee('VerySecretPassword999');

        // Check Inertia prop
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Email')
            ->where('config.has_secret', true)
            ->where('config.endpoint', 'smtp.mailtrap.io')
            ->missing('config.password')
        );
    }

    public function test_test_connection_sends_benign_ping_without_payroll_data(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('settings.email.test'), [
                'recipient' => 'test.target@example.com',
            ]);

        $response->assertSessionHas('success');

        // Check that sent mail does NOT contain salary or employee data
        Mail::assertSent(SmtpTestPingEmail::class, function ($mail) {
            return $mail->hasTo('test.target@example.com');
        });

        // Integration settings updated with test status
        $setting = IntegrationSetting::forProvider('smtp');
        $this->assertNotNull($setting->last_tested_at);
        $this->assertEquals('success', $setting->last_test_status);
    }

    public function test_rbac_permissions_enforced_for_deliveries_and_settings(): void
    {
        // Unauthorized user cannot view deliveries
        $this->actingAs($this->unauthorizedUser)
            ->get(route('deliveries.index'))
            ->assertForbidden();

        // Unauthorized user cannot dispatch email
        $this->actingAs($this->unauthorizedUser)
            ->post(route('payslips.deliveries.email', $this->finalizedPayslip->id))
            ->assertForbidden();

        // Unauthorized user cannot view email settings
        $this->actingAs($this->unauthorizedUser)
            ->get(route('settings.email.edit'))
            ->assertForbidden();

        // Auditor can view deliveries but cannot send or retry
        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::EMAIL->value,
            'recipient' => $this->finalizedPayslip->email_snapshot,
            'status' => DeliveryStatus::FAILED->value,
            'idempotency_key' => 'auditor_test_key',
            'attempt_count' => 1,
        ]);

        $this->actingAs($this->auditor)
            ->get(route('deliveries.index'))
            ->assertOk();

        $this->actingAs($this->auditor)
            ->post(route('payslips.deliveries.email', $this->finalizedPayslip->id))
            ->assertForbidden();

        $this->actingAs($this->auditor)
            ->post(route('deliveries.retry', $delivery->id))
            ->assertForbidden();
    }
}
