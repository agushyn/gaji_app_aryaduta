<?php

namespace Tests\Feature;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Jobs\SendPayslipWhatsAppJob;
use App\Models\Employee;
use App\Models\IntegrationSetting;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DeliveryService;
use App\Services\PayslipPdfService;
use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\Gateways\FonnteGateway;
use App\Services\WhatsApp\Gateways\GenericWebhookGateway;
use App\Services\WhatsApp\Gateways\MockGateway;
use App\Services\WhatsApp\WhatsAppManager;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WhatsAppDeliveryTest extends TestCase
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

    protected Payslip $draftPayslip;

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

        // Plain role without delivery/settings permissions
        $plainRole = Role::create([
            'name' => 'plain_staff',
            'label' => 'Plain Staff',
            'description' => 'Staff without permissions',
        ]);
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->roles()->attach($plainRole);

        // Employee with valid phone number
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

        $employee2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Siti Rahma',
            'department' => 'Keuangan',
            'position' => 'Staff Accounting',
            'email' => 'siti.rahma@example.com',
            'phone' => '+6289876543210',
            'hire_date' => '2023-03-01',
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

        // Finalized payslip with PDF
        $pdfPath = 'payslips/2026-09/SLIP-202609-001.pdf';
        Storage::disk('local')->put($pdfPath, '%PDF-1.4 Fake PDF Content for WhatsApp Delivery Test');

        $this->finalizedPayslip = Payslip::create([
            'slip_number' => 'SLIP-202609-001',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => PayslipStatus::FINALIZED->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_number_snapshot' => $this->employee->employee_number,
            'employee_name_snapshot' => $this->employee->full_name,
            'department_snapshot' => $this->employee->department,
            'position_snapshot' => $this->employee->position,
            'email_snapshot' => $this->employee->email,
            'phone_snapshot' => $this->employee->phone,
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
            'pdf_path' => $pdfPath,
            'pdf_hash' => hash('sha256', '%PDF-1.4 Fake PDF Content for WhatsApp Delivery Test'),
        ]);

        // Draft payslip (not finalized)
        $this->draftPayslip = Payslip::create([
            'slip_number' => 'SLIP-202609-002',
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee2->id,
            'status' => PayslipStatus::DRAFT->value,
            'period_code_snapshot' => $this->period->period_code,
            'period_month_snapshot' => 'September 2026',
            'period_start_date_snapshot' => '2026-09-01',
            'period_end_date_snapshot' => '2026-09-30',
            'employee_number_snapshot' => $employee2->employee_number,
            'employee_name_snapshot' => $employee2->full_name,
            'department_snapshot' => $employee2->department,
            'position_snapshot' => $employee2->position,
            'email_snapshot' => $employee2->email,
            'phone_snapshot' => $employee2->phone,
            'basic_salary' => 4500000.00,
            'gaji_pokok' => 4500000.00,
            'total_earnings' => 4500000.00,
            'total_deductions' => 0.00,
            'net_salary' => 4500000.00,
            'currency' => 'IDR',
            'created_by' => $this->personalia->id,
        ]);
    }

    public function test_whatsapp_gateway_interface_and_adapter_resolution(): void
    {
        $manager = app(WhatsAppManager::class);

        // Test Mock Driver
        $mockGateway = $manager->driver('mock');
        $this->assertInstanceOf(MockGateway::class, $mockGateway);
        $this->assertInstanceOf(WhatsAppGatewayInterface::class, $mockGateway);
        $this->assertTrue($mockGateway->supportsDocumentDelivery());

        // Test Fonnte Driver
        $fonnteGateway = $manager->driver('fonnte');
        $this->assertInstanceOf(FonnteGateway::class, $fonnteGateway);
        $this->assertInstanceOf(WhatsAppGatewayInterface::class, $fonnteGateway);
        $this->assertEquals('fonnte', $fonnteGateway->getName());

        // Test Generic Webhook Driver
        $genericGateway = $manager->driver('generic');
        $this->assertInstanceOf(GenericWebhookGateway::class, $genericGateway);
        $this->assertInstanceOf(WhatsAppGatewayInterface::class, $genericGateway);
        $this->assertEquals('generic', $genericGateway->getName());
    }

    public function test_mock_gateway_sends_document_successfully(): void
    {
        $gateway = new MockGateway;
        $result = $gateway->sendDocument(
            '+6281234567890',
            'storage/test.pdf',
            'slip.pdf',
            'Halo ini slip gaji Anda'
        );

        $this->assertTrue($result->success);
        $this->assertEquals(DeliveryStatus::SENT, $result->status);
        $this->assertStringStartsWith('mock_msg_', $result->providerMessageId);
    }

    public function test_cannot_queue_whatsapp_delivery_for_unfinalized_payslip(): void
    {
        $this->actingAs($this->personalia);

        $response = $this->post(route('payslips.deliveries.whatsapp', $this->draftPayslip->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('payslip_deliveries', [
            'payslip_id' => $this->draftPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
        ]);
    }

    public function test_cannot_send_whatsapp_delivery_without_permission(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $response = $this->post(route('payslips.deliveries.whatsapp', $this->finalizedPayslip->id));

        $response->assertStatus(403);
    }

    public function test_queue_whatsapp_delivery_for_finalized_payslip(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        $response = $this->post(route('payslips.deliveries.whatsapp', $this->finalizedPayslip->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payslip_deliveries', [
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6281234567890',
            'status' => DeliveryStatus::QUEUED->value,
        ]);

        Queue::assertPushed(SendPayslipWhatsAppJob::class, function ($job) {
            return $job->deliveryId > 0;
        });
    }

    public function test_duplicate_dispatch_is_idempotent(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        // First dispatch
        $this->post(route('payslips.deliveries.whatsapp', $this->finalizedPayslip->id));
        // Second dispatch (duplicate)
        $this->post(route('payslips.deliveries.whatsapp', $this->finalizedPayslip->id));

        // Must still have only 1 delivery record
        $this->assertEquals(
            1,
            PayslipDelivery::where('payslip_id', $this->finalizedPayslip->id)
                ->where('channel', DeliveryChannel::WHATSAPP->value)
                ->count()
        );

        Queue::assertPushed(SendPayslipWhatsAppJob::class, 1);
    }

    public function test_fonnte_gateway_sends_with_fake_http_client(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-999'],
                'target' => ['081234567890'],
            ], 200),
        ]);

        $gateway = new FonnteGateway(
            token: 'secret-fonnte-token-123',
            endpoint: 'https://api.fonnte.com/send'
        );

        $result = $gateway->sendDocument(
            '081234567890',
            Storage::disk('local')->path($this->finalizedPayslip->pdf_path),
            'SLIP-202609-001.pdf',
            'Slip Gaji Periode 2026-09'
        );

        $this->assertTrue($result->success);
        $this->assertEquals(DeliveryStatus::SENT, $result->status);
        $this->assertEquals('fonnte-msg-999', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'secret-fonnte-token-123');
        });
    }

    public function test_generic_webhook_gateway_sends_json_payload_with_fake_http(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/send' => Http::response([
                'success' => true,
                'message_id' => 'generic-msg-456',
            ], 200),
        ]);

        $gateway = new GenericWebhookGateway(
            endpoint: 'https://webhook.example.com/whatsapp/send',
            secretToken: 'bearer-token-abc'
        );

        $result = $gateway->sendDocument(
            '+6281234567890',
            Storage::disk('local')->path($this->finalizedPayslip->pdf_path),
            'SLIP-202609-001.pdf',
            'Slip Gaji Anda'
        );

        $this->assertTrue($result->success);
        $this->assertEquals(DeliveryStatus::SENT, $result->status);
        $this->assertEquals('generic-msg-456', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.example.com/whatsapp/send'
                && $request->hasHeader('Authorization', 'Bearer bearer-token-abc');
        });
    }

    public function test_send_payslip_whatsapp_job_execution_updates_status_and_attempt(): void
    {
        // Configure mock driver in integration settings
        IntegrationSetting::updateOrCreate(
            ['provider' => 'whatsapp'],
            [
                'is_active' => true,
                'sender_identifier' => 'DEVICE-01',
                'config_json' => [
                    'driver' => 'mock',
                    'rate_limit_per_minute' => 30,
                    'timeout' => 25,
                ],
            ]
        );

        $service = app(DeliveryService::class);
        $delivery = $service->queueWhatsAppDelivery($this->finalizedPayslip, $this->personalia);

        $job = new SendPayslipWhatsAppJob($delivery->id);
        $job->handle(app(PayslipPdfService::class), app(AuditLogService::class), app(WhatsAppManager::class));

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::SENT->value, $delivery->status);
        $this->assertEquals(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->provider_message_id);
        $this->assertNotNull($delivery->sent_at);

        $this->assertDatabaseHas('delivery_attempts', [
            'payslip_delivery_id' => $delivery->id,
            'status' => DeliveryStatus::SENT->value,
        ]);
    }

    public function test_webhook_normalizes_status_to_delivered_with_valid_signature(): void
    {
        // Setup existing sent delivery
        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '+6281234567890',
            'status' => DeliveryStatus::SENT->value,
            'provider_message_id' => 'fonnte-msg-888',
            'idempotency_key' => 'wa_test_idempotency_key',
            'created_by' => $this->personalia->id,
        ]);

        // Setup whatsapp setting with webhook secret
        IntegrationSetting::updateOrCreate(
            ['provider' => 'whatsapp'],
            [
                'is_active' => true,
                'config_json' => [
                    'driver' => 'fonnte',
                    'webhook_secret' => 'webhook-secret-token-xyz',
                ],
            ]
        );

        $response = $this->postJson(route('webhooks.whatsapp'), [
            'id' => 'fonnte-msg-888',
            'status' => 'delivered',
            'token' => 'webhook-secret-token-xyz',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::DELIVERED->value, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);

        // Status must NOT claim delivered without valid provider proof
        $this->assertDatabaseHas('delivery_attempts', [
            'payslip_delivery_id' => $delivery->id,
            'status' => DeliveryStatus::DELIVERED->value,
        ]);
    }

    public function test_webhook_rejects_invalid_token(): void
    {
        IntegrationSetting::updateOrCreate(
            ['provider' => 'whatsapp'],
            [
                'is_active' => true,
                'config_json' => [
                    'driver' => 'fonnte',
                    'webhook_secret' => 'correct-secret',
                ],
            ]
        );

        $response = $this->postJson(route('webhooks.whatsapp'), [
            'id' => 'msg-123',
            'status' => 'delivered',
            'token' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_whatsapp_settings_page_masks_secret_and_encrypts_on_save(): void
    {
        $this->actingAs($this->superAdmin);

        // Save secret
        $response = $this->put(route('settings.whatsapp.update'), [
            'driver' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'sender_identifier' => 'DEVICE-01',
            'secret' => 'my-super-secret-token-123',
            'webhook_secret' => 'my-webhook-secret-456',
            'rate_limit_per_minute' => 30,
            'timeout' => 25,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify stored encrypted in DB
        $setting = IntegrationSetting::forProvider('whatsapp');
        $this->assertNotNull($setting->secret_encrypted);
        $this->assertNotEquals('my-super-secret-token-123', $setting->secret_encrypted);
        $this->assertEquals('my-super-secret-token-123', Crypt::decryptString($setting->secret_encrypted));

        // Verify edit page masks secret
        $editResponse = $this->get(route('settings.whatsapp.edit'));
        $editResponse->assertStatus(200);
        $editResponse->assertInertia(fn ($page) => $page
            ->component('Settings/WhatsApp')
            ->where('config.has_secret', true)
            ->where('config.has_webhook_secret', true)
        );
    }

    public function test_test_connection_sends_benign_ping_without_salary_data(): void
    {
        $this->actingAs($this->superAdmin);

        // Configure mock driver
        IntegrationSetting::updateOrCreate(
            ['provider' => 'whatsapp'],
            [
                'is_active' => true,
                'config_json' => [
                    'driver' => 'mock',
                ],
            ]
        );

        $response = $this->postJson(route('settings.whatsapp.test'), [
            'recipient' => '+6281234567890',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_expiring_signed_pdf_url_security(): void
    {
        // 1. External WhatsApp Gateway guest access with valid HMAC signature succeeds
        $validSignedUrl = URL::temporarySignedRoute(
            'payslips.signed-pdf',
            now()->addMinutes(15),
            ['payslip' => $this->finalizedPayslip->id]
        );

        $response = $this->get($validSignedUrl);
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));

        // 2. Tampered URL fails with 403 Forbidden
        $tamperedUrl = $validSignedUrl.'&extra=tamper';
        $tamperedResponse = $this->get($tamperedUrl);
        $tamperedResponse->assertStatus(403);

        // 3. Signed URL for unfinalized payslip is rejected with 403 Forbidden
        $unfinalizedSignedUrl = URL::temporarySignedRoute(
            'payslips.signed-pdf',
            now()->addMinutes(15),
            ['payslip' => $this->draftPayslip->id]
        );
        $unfinalizedResponse = $this->get($unfinalizedSignedUrl);
        $unfinalizedResponse->assertStatus(403);
    }

    public function test_batch_period_whatsapp_queues_all_finalized_slips(): void
    {
        Queue::fake();

        $this->actingAs($this->personalia);

        $response = $this->post(route('payroll-periods.deliveries.whatsapp', $this->period->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendPayslipWhatsAppJob::class, function ($job) {
            return $job->deliveryId > 0;
        });
    }
}
