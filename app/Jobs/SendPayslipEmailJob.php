<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\PayslipStatus;
use App\Mail\PayslipEmail;
use App\Models\IntegrationSetting;
use App\Models\PayslipDelivery;
use App\Services\AuditLogService;
use App\Services\PayslipPdfService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPayslipEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $deliveryId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PayslipPdfService $pdfService, AuditLogService $auditLogService): void
    {
        $delivery = PayslipDelivery::with('payslip')->find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $payslip = $delivery->payslip;

        // Idempotency: do not re-send if already marked as sent or delivered
        if ($delivery->isSent()) {
            return;
        }

        // Strict rule: do not send if payslip is not finalized
        if ($payslip->status !== PayslipStatus::FINALIZED->value) {
            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'NOT_FINALIZED',
                'last_error_summary' => 'Slip gaji belum berstatus final, pengiriman dibatalkan demi kepatuhan audit.',
            ]);

            throw new DomainException("Slip gaji {$payslip->slip_number} belum difinalisasi. Pengiriman email ditolak.");
        }

        // Validate recipient email format
        if (empty($delivery->recipient) || ! filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) {
            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'INVALID_RECIPIENT_EMAIL',
                'last_error_summary' => 'Format alamat email penerima tidak valid atau kosong.',
            ]);

            return;
        }

        // Register new attempt record
        $attemptNumber = $delivery->attempt_count + 1;
        $delivery->update([
            'status' => DeliveryStatus::PROCESSING->value,
            'attempt_count' => $attemptNumber,
            'last_attempt_at' => now(),
        ]);

        $attempt = $delivery->attempts()->create([
            'attempt_number' => $attemptNumber,
            'status' => DeliveryStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        try {
            // Apply dynamic SMTP if configured and active
            $this->applyCustomSmtpConfig();

            // Retrieve PDF content
            $pdfContent = $pdfService->getPdfContent($payslip);

            // Send individual email (Never CC/BCC)
            Mail::mailer('smtp')
                ->to($delivery->recipient)
                ->send(new PayslipEmail($payslip, $pdfContent));

            // Record success
            $attempt->update([
                'status' => DeliveryStatus::SENT->value,
                'finished_at' => now(),
            ]);

            $delivery->update([
                'status' => DeliveryStatus::SENT->value,
                'sent_at' => now(),
                'last_error_code' => null,
                'last_error_summary' => null,
            ]);

            // Audit log without email body, salary, password, or attachments
            $auditLogService->log(
                action: 'payslip.email_sent',
                subject: $delivery,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'recipient' => $delivery->recipient,
                    'attempt' => $attemptNumber,
                    'status' => 'sent',
                ]
            );
        } catch (Throwable $e) {
            $sanitizedError = $this->sanitizeError($e->getMessage());

            $isLastTry = ($this->attempts() >= $this->tries) || ($attemptNumber >= $this->tries);

            $attempt->update([
                'status' => DeliveryStatus::FAILED->value,
                'error_code' => 'SMTP_ERROR',
                'error_summary' => $sanitizedError,
                'finished_at' => now(),
            ]);

            $delivery->update([
                'status' => $isLastTry ? DeliveryStatus::FAILED->value : DeliveryStatus::PROCESSING->value,
                'last_error_code' => 'SMTP_ERROR',
                'last_error_summary' => $sanitizedError,
            ]);

            $auditLogService->log(
                action: 'payslip.email_failed',
                subject: $delivery,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'recipient' => $delivery->recipient,
                    'attempt' => $attemptNumber,
                    'error_code' => 'SMTP_ERROR',
                    'error_summary' => $sanitizedError,
                ]
            );

            throw $e;
        }
    }

    /**
     * Handle a job failure after exhausting all retry attempts.
     */
    public function failed(?Throwable $exception = null): void
    {
        $delivery = PayslipDelivery::find($this->deliveryId);
        if ($delivery && $delivery->status !== DeliveryStatus::SENT->value) {
            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'MAX_RETRIES_EXCEEDED',
                'last_error_summary' => $exception ? $this->sanitizeError($exception->getMessage()) : 'Batas maksimal percobaan pengiriman telah tercapai.',
            ]);
        }
    }

    /**
     * Apply custom database-stored SMTP settings dynamically if active.
     */
    protected function applyCustomSmtpConfig(): void
    {
        $setting = IntegrationSetting::forProvider('smtp');

        if (! $setting || ! $setting->is_active || empty($setting->endpoint)) {
            return;
        }

        $config = $setting->config_json ?? [];
        $secret = $setting->getDecryptedSecret();

        Config::set('mail.mailers.smtp.host', $setting->endpoint);
        Config::set('mail.mailers.smtp.port', (int) ($config['port'] ?? 587));
        Config::set('mail.mailers.smtp.encryption', $config['encryption'] ?? 'tls');
        Config::set('mail.mailers.smtp.username', $config['username'] ?? null);

        if (! empty($secret)) {
            Config::set('mail.mailers.smtp.password', $secret);
        }

        if (! empty($setting->sender_identifier)) {
            Config::set('mail.from.address', $setting->sender_identifier);
        }

        if (! empty($config['from_name'])) {
            Config::set('mail.from.name', $config['from_name']);
        }

        Mail::purge('smtp');
    }

    /**
     * Sanitize error message to prevent leaking passwords, tokens, or credentials into DB logs.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        // Redact potential password / secret substrings
        $sanitized = preg_replace('/password=[^\s;&]+/i', 'password=***', $rawMessage);
        $sanitized = preg_replace('/pass=[^\s;&]+/i', 'pass=***', $sanitized);
        $sanitized = preg_replace('/secret=[^\s;&]+/i', 'secret=***', $sanitized);
        $sanitized = preg_replace('/api[-_]?key=[^\s;&]+/i', 'api_key=***', $sanitized);
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i', 'Bearer ***', $sanitized);

        return mb_substr(trim($sanitized), 0, 500);
    }
}
