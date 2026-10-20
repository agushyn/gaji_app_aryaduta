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
use App\Services\WhatsApp\Gateways\OpenWaGateway;
use App\Services\WhatsApp\WhatsAppManager;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenWaGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $unauthorizedUser;

    protected Employee $employee1;

    protected Employee $employee2;

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

        $plainRole = Role::create([
            'name' => 'plain_staff',
            'label' => 'Plain Staff',
            'description' => 'Staff without permissions',
        ]);
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->roles()->attach($plainRole);

        $this->employee1 = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff Logistik',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890',
            'hire_date' => '2023-01-15',
            'employment_status' => 'active',
        ]);

        $this->employee2 = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Siti Rahma',
            'department' => 'Keuangan',
            'position' => 'Staff Accounting',
            'email' => 'siti.rahma@example.com',
            'phone' => '089876543210',
            'hire_date' => '2023-03-01',
            'employment_status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'period_code' => 'PR-2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'cutoff_date' => '2026-03-25',
            'payment_date' => '2026-03-28',
            'status' => PayrollPeriodStatus::FINALIZED->value,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->finalizedPayslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee1->id,
            'slip_number' => 'SLIP-2026-03-001',
            'status' => PayslipStatus::FINALIZED->value,
            'employee_name_snapshot' => $this->employee1->full_name,
            'employee_number_snapshot' => $this->employee1->employee_number,
            'phone_snapshot' => $this->employee1->phone,
            'email_snapshot' => $this->employee1->email,
            'company_name_snapshot' => 'CV. Bina Pustaka',
            'period_month_snapshot' => 'Maret 2026',
            'basic_salary' => 5000000,
            'net_salary' => 5200000,
            'pdf_path' => 'payslips/PR-2026-03/SLIP-001.pdf',
            'pdf_sha256' => hash('sha256', 'mock pdf content'),
            'created_by' => $this->superAdmin->id,
        ]);

        $this->draftPayslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee2->id,
            'slip_number' => 'SLIP-2026-03-002',
            'status' => PayslipStatus::DRAFT->value,
            'employee_name_snapshot' => $this->employee2->full_name,
            'employee_number_snapshot' => $this->employee2->employee_number,
            'phone_snapshot' => $this->employee2->phone,
            'basic_salary' => 4500000,
            'net_salary' => 4700000,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create sample PDF file in storage
        Storage::disk('local')->put('payslips/PR-2026-03/SLIP-001.pdf', '%PDF-1.4 sample pdf document content');

        // Setup OpenWA Integration Settings by default
        $this->configureOpenWa();
    }

    protected function configureOpenWa(array $overrides = []): void
    {
        $setting = IntegrationSetting::firstOrNew(['provider' => 'whatsapp']);
        $setting->is_active = true;
        $setting->endpoint = $overrides['endpoint'] ?? 'http://localhost:3000/api';
        $setting->sender_identifier = $overrides['session_id'] ?? 'payroll_session';
        $setting->secret_encrypted = Crypt::encryptString($overrides['api_key'] ?? 'test-openwa-api-key');
        $setting->config_json = array_merge([
            'driver' => 'openwa',
            'session_id' => $overrides['session_id'] ?? 'payroll_session',
            'connection_timeout' => 10,
            'request_timeout' => 30,
            'send_interval_seconds' => 2,
            'max_retries' => 3,
            'document_mode' => 'base64',
            'rate_limit_per_minute' => 30,
            'timeout' => 30,
        ], $overrides);
        $setting->save();
    }

    /**
     * Test saving OpenWA settings and verifying encrypted secret storage.
     */
    public function test_openwa_configuration_can_be_saved_and_secret_is_encrypted(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(route('settings.whatsapp.update'), [
            'driver' => 'openwa',
            'endpoint' => 'http://127.0.0.1:3000/api',
            'session_id' => 'cv_bina_session',
            'secret' => 'super-secret-openwa-token-12345',
            'connection_timeout' => 12,
            'request_timeout' => 45,
            'send_interval_seconds' => 3,
            'max_retries' => 4,
            'document_mode' => 'base64',
            'rate_limit_per_minute' => 20,
            'timeout' => 45,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = IntegrationSetting::forProvider('whatsapp');
        $this->assertEquals('http://127.0.0.1:3000/api', $setting->endpoint);
        $this->assertEquals('cv_bina_session', $setting->sender_identifier);
        $this->assertEquals('openwa', $setting->config_json['driver']);
        $this->assertEquals('cv_bina_session', $setting->config_json['session_id']);
        $this->assertEquals(3, $setting->config_json['send_interval_seconds']);
        $this->assertEquals(4, $setting->config_json['max_retries']);
        $this->assertEquals('base64', $setting->config_json['document_mode']);

        // Verify secret is properly encrypted in DB and decrypts to original
        $this->assertNotEquals('super-secret-openwa-token-12345', $setting->secret_encrypted);
        $this->assertEquals('super-secret-openwa-token-12345', Crypt::decryptString($setting->secret_encrypted));
    }

    /**
     * Test unauthorized users cannot modify WhatsApp settings.
     */
    public function test_unauthorized_user_cannot_save_openwa_configuration(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->put(route('settings.whatsapp.update'), [
            'driver' => 'openwa',
            'endpoint' => 'http://127.0.0.1:3000/api',
            'session_id' => 'hack',
            'secret' => 'hack',
            'rate_limit_per_minute' => 30,
            'timeout' => 25,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test that OpenWA API key is never leaked in Inertia props or HTML.
     */
    public function test_openwa_api_key_is_masked_in_frontend_props(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('settings.whatsapp.edit'));

        $response->assertOk();
        $response->assertDontSee('test-openwa-api-key');

        $props = $response->viewData('page')['props'];
        $config = $props['config'];

        $this->assertEquals('openwa', $config['driver']);
        $this->assertEquals('payroll_session', $config['session_id']);
        $this->assertTrue($config['has_secret']);
        $this->assertArrayNotHasKey('secret', $config);
    }

    /**
     * Test OpenWA session status check endpoint returns success when session is CONNECTED.
     */
    public function test_openwa_session_status_check_connected(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/status' => Http::response([
                'status' => 'CONNECTED',
                'session' => 'payroll_session',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('settings.whatsapp.session-status'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'api_connected' => true,
            'is_ready' => true,
            'session_status' => 'CONNECTED',
        ]);

        $setting = IntegrationSetting::forProvider('whatsapp');
        $this->assertEquals('CONNECTED', $setting->config_json['last_session_status']);
        $this->assertNotNull($setting->config_json['last_session_checked_at']);
    }

    /**
     * Test OpenWA session status check differentiates API connected from session not ready (e.g. PAIRING/QR).
     */
    public function test_openwa_session_status_check_pairing_qr_needed(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/status' => Http::response([
                'status' => 'PAIRING',
                'message' => 'Waiting for QR code scan',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('settings.whatsapp.session-status'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'api_connected' => true,
            'is_ready' => false,
            'session_status' => 'PAIRING',
        ]);
    }

    /**
     * Test OpenWA session status check handles 404 session not found.
     */
    public function test_openwa_session_status_check_session_not_found(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/status' => Http::response(['error' => 'Session not found'], 404),
            'http://localhost:3000/api/sessions' => Http::response([], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('settings.whatsapp.session-status'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'api_connected' => true,
            'is_ready' => false,
            'session_status' => 'NOT_FOUND',
        ]);
    }

    /**
     * Test OpenWA session status check handles 401 invalid API Key.
     */
    public function test_openwa_session_status_check_unauthorized_api_key(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/status' => Http::response([
                'error' => 'Invalid API Key',
            ], 401),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('settings.whatsapp.session-status'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'api_connected' => false,
            'session_status' => 'UNAUTHORIZED',
        ]);
    }

    /**
     * Test sending ping test message via OpenWA with normalized phone and X-API-Key header.
     */
    public function test_openwa_send_test_message_ping_success(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/messages/send-text' => Http::response([
                'response' => 'message-ping-id-123',
                'status' => 'success',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('settings.whatsapp.test'), [
            'recipient' => '081234567890',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3000/api/sessions/payroll_session/messages/send-text'
                && $request->header('X-API-Key')[0] === 'test-openwa-api-key'
                && $request['chatId'] === '6281234567890@c.us'
                && ! str_contains($request['text'], '5.000.000') // Zero payroll data
                && ! str_contains($request['text'], 'Gaji');
        });
    }

    /**
     * Test sending PDF document via OpenWA in Base64 Data URI mode.
     */
    public function test_openwa_send_document_base64_mode_success(): void
    {
        $this->configureOpenWa(['document_mode' => 'base64']);

        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/messages/send-document' => Http::response([
                'response' => 'wamid.HBgL-openwa-doc-001',
                'status' => 'success',
            ], 200),
        ]);

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6281234567890',
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'wa_test_openwa_doc_001',
            'attempt_count' => 0,
        ]);

        $job = new SendPayslipWhatsAppJob($delivery->id);
        $job->handle(
            app(PayslipPdfService::class),
            app(AuditLogService::class),
            app(WhatsAppManager::class)
        );

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::SENT->value, $delivery->status);
        $this->assertEquals('wamid.HBgL-openwa-doc-001', $delivery->provider_message_id);
        $this->assertNotNull($delivery->sent_at);

        Http::assertSent(function ($request) {
            $isCorrectUrl = $request->url() === 'http://localhost:3000/api/sessions/payroll_session/messages/send-document';
            $hasApiKey = $request->header('X-API-Key')[0] === 'test-openwa-api-key';
            $isChatId = $request['chatId'] === '6281234567890@c.us';
            $isBase64 = str_starts_with($request['data'] ?? '', 'data:application/pdf;base64,');
            $isPdfMime = $request['mimetype'] === 'application/pdf';

            return $isCorrectUrl && $hasApiKey && $isChatId && $isBase64 && $isPdfMime;
        });
    }

    /**
     * Test sending PDF document via OpenWA in URL mode with short-lived expiring HMAC signed URL.
     */
    public function test_openwa_send_document_signed_url_mode_success(): void
    {
        $this->configureOpenWa(['document_mode' => 'url']);

        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/messages/send-document' => Http::response([
                'response' => 'wamid.HBgL-openwa-url-002',
                'status' => 'success',
            ], 200),
        ]);

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6281234567890',
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'wa_test_openwa_url_002',
            'attempt_count' => 0,
        ]);

        $job = new SendPayslipWhatsAppJob($delivery->id);
        $job->handle(
            app(PayslipPdfService::class),
            app(AuditLogService::class),
            app(WhatsAppManager::class)
        );

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::SENT->value, $delivery->status);
        $this->assertEquals('wamid.HBgL-openwa-url-002', $delivery->provider_message_id);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3000/api/sessions/payroll_session/messages/send-document'
                && isset($request['url'])
                && str_contains($request['url'], '/payslips/' . $this->finalizedPayslip->id . '/signed-pdf')
                && str_contains($request['url'], 'signature=');
        });
    }

    /**
     * Test unfinalized/draft payslip cannot be sent via OpenWA.
     */
    public function test_openwa_refuses_unfinalized_draft_payslip(): void
    {
        Http::fake();

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->draftPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6289876543210',
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'wa_test_draft_refusal',
            'attempt_count' => 0,
        ]);

        $this->expectException(\DomainException::class);

        $job = new SendPayslipWhatsAppJob($delivery->id);
        $job->handle(
            app(PayslipPdfService::class),
            app(AuditLogService::class),
            app(WhatsAppManager::class)
        );

        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::FAILED->value, $delivery->status);
        $this->assertEquals('NOT_FINALIZED', $delivery->last_error_code);
    }

    /**
     * Test idempotency: sending WhatsApp twice does not dispatch duplicate job or duplicate record.
     */
    public function test_openwa_idempotency_prevents_duplicate_dispatch(): void
    {
        Queue::fake();

        $service = app(DeliveryService::class);

        $delivery1 = $service->queueWhatsAppDelivery($this->finalizedPayslip, $this->superAdmin);
        $delivery2 = $service->queueWhatsAppDelivery($this->finalizedPayslip, $this->superAdmin);

        $this->assertEquals($delivery1->id, $delivery2->id);

        Queue::assertPushed(SendPayslipWhatsAppJob::class, 1);
    }

    /**
     * Test permanent error (e.g. AUTH_FAILED) fails the delivery immediately without endless retries.
     */
    public function test_openwa_permanent_error_does_not_retry(): void
    {
        Http::fake([
            'http://localhost:3000/api/sessions/payroll_session/messages/send-document' => Http::response([
                'error' => 'Unauthorized: Invalid API Key',
            ], 401),
        ]);

        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6281234567890',
            'status' => DeliveryStatus::QUEUED->value,
            'idempotency_key' => 'wa_test_permanent_err',
            'attempt_count' => 0,
        ]);

        $job = new SendPayslipWhatsAppJob($delivery->id);

        // Does NOT throw an exception because AUTH_FAILED is recognized as permanent
        $job->handle(
            app(PayslipPdfService::class),
            app(AuditLogService::class),
            app(WhatsAppManager::class)
        );

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::FAILED->value, $delivery->status);
        $this->assertEquals('AUTH_FAILED', $delivery->last_error_code);
    }

    /**
     * Test bulk WhatsApp delivery applies staggered delays using send_interval_seconds.
     */
    public function test_openwa_bulk_delivery_applies_staggered_send_intervals(): void
    {
        Queue::fake();

        // Finalize second employee's payslip as well
        $this->draftPayslip->update([
            'status' => PayslipStatus::FINALIZED->value,
            'pdf_path' => 'payslips/PR-2026-03/SLIP-002.pdf',
            'pdf_sha256' => hash('sha256', 'mock pdf content 2'),
        ]);

        $service = app(DeliveryService::class);
        $result = $service->queuePeriodWhatsAppDeliveries($this->period, $this->superAdmin);

        $this->assertEquals(2, $result['queued']);
        $this->assertEquals(0, $result['skipped']);

        // Verify that 2 SendPayslipWhatsAppJob were pushed to the queue
        Queue::assertPushed(SendPayslipWhatsAppJob::class, 2);
    }

    /**
     * Test OpenWA webhook report (DLR onAck) updates delivery status from SENT to DELIVERED and READ.
     */
    public function test_openwa_webhook_ack_updates_delivery_status(): void
    {
        $delivery = PayslipDelivery::create([
            'payslip_id' => $this->finalizedPayslip->id,
            'channel' => DeliveryChannel::WHATSAPP->value,
            'recipient' => '6281234567890',
            'status' => DeliveryStatus::SENT->value,
            'provider_message_id' => 'wamid.HBgL-ack-test-001',
            'idempotency_key' => 'wa_test_ack_dlr',
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        // Simulating OpenWA onAck webhook for DELIVERED (ack: 2)
        $response = $this->postJson(route('webhooks.whatsapp'), [
            'event' => 'onAck',
            'data' => [
                'id' => [
                    '_serialized' => 'wamid.HBgL-ack-test-001',
                ],
                'ack' => 2,
            ],
        ]);

        $response->assertOk();

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::DELIVERED->value, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);

        // Simulating OpenWA onAck webhook for message seen/read (ack: 3)
        $responseRead = $this->postJson(route('webhooks.whatsapp'), [
            'event' => 'onAck',
            'data' => [
                'id' => [
                    '_serialized' => 'wamid.HBgL-ack-test-001',
                ],
                'ack' => 3,
            ],
        ]);

        $responseRead->assertOk();

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::DELIVERED->value, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }
}
