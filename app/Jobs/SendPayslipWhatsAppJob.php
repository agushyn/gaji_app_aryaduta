<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\PayslipStatus;
use App\Models\PayslipDelivery;
use App\Services\AuditLogService;
use App\Services\PayslipPdfService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\PhoneNumberNormalizer;
use DomainException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendPayslipWhatsAppJob implements ShouldQueue
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
    public function handle(
        PayslipPdfService $pdfService,
        AuditLogService $auditLogService,
        WhatsAppManager $whatsAppManager
    ): void {
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
                'last_error_summary' => 'Slip gaji belum berstatus final, pengiriman WhatsApp dibatalkan demi kepatuhan audit.',
            ]);

            throw new DomainException("Slip gaji {$payslip->slip_number} belum difinalisasi. Pengiriman WhatsApp ditolak.");
        }

        // Validate recipient phone format using PhoneNumberNormalizer
        if (empty($delivery->recipient) || ! PhoneNumberNormalizer::isValid($delivery->recipient)) {
            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'INVALID_RECIPIENT_PHONE',
                'last_error_summary' => 'Nomor WhatsApp penerima tidak valid atau tidak sesuai standar nomor ponsel Indonesia.',
            ]);

            return;
        }

        // Rate limiting check: if carrier / gateway throttled, back off
        if (! $whatsAppManager->checkRateLimit()) {
            $this->release(30);

            return;
        }

        $normalizedPhone = PhoneNumberNormalizer::normalize($delivery->recipient);

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

        $gateway = $whatsAppManager->getActiveGateway();

        // Check if gateway supports document delivery
        if (! $gateway->supportsDocumentDelivery()) {
            $errorSummary = "Gateway WhatsApp {$gateway->getName()} tidak mendukung pengiriman dokumen berkas PDF.";
            $attempt->update([
                'status' => DeliveryStatus::FAILED->value,
                'error_code' => 'DOCUMENT_NOT_SUPPORTED',
                'error_summary' => $errorSummary,
                'finished_at' => now(),
            ]);

            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'DOCUMENT_NOT_SUPPORTED',
                'last_error_summary' => $errorSummary,
            ]);

            return;
        }

        try {
            // Retrieve PDF content
            $pdfContent = $pdfService->getPdfContent($payslip);
            $safeSlip = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip->slip_number);
            $filename = "Slip_Gaji_{$safeSlip}.pdf";

            // Standardized caption without salary numbers (to protect privacy on notification screens)
            $companyName = $payslip->company_name_snapshot ?? 'CV. Bina Pustaka';
            $periodMonth = $payslip->period_month_snapshot ?? 'Bulan Ini';
            $caption = "Yth. {$payslip->employee_name_snapshot}, terlampir dokumen resmi Slip Gaji Anda untuk periode {$periodMonth} ({$companyName}). Nomor slip: {$payslip->slip_number}. Dokumen dilindungi stempel integritas SHA-256.";

            // Generate short-lived expiring HMAC signed URL for external gateways supporting URL mode
            $temporarySignedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'payslips.signed-pdf',
                now()->addMinutes(30),
                ['payslip' => $payslip->id]
            );

            $result = $gateway->sendDocument(
                recipient: $normalizedPhone,
                pdfContent: $pdfContent,
                filename: $filename,
                caption: $caption,
                idempotencyKey: $delivery->idempotency_key,
                documentUrl: $temporarySignedUrl
            );

            if ($result->success) {
                // Record success (status SENT, not DELIVERED until callback proof)
                $attempt->update([
                    'status' => $result->status->value,
                    'provider_message_id' => $result->providerMessageId,
                    'finished_at' => now(),
                ]);

                $delivery->update([
                    'status' => $result->status->value,
                    'provider_message_id' => $result->providerMessageId,
                    'sent_at' => now(),
                    'last_error_code' => null,
                    'last_error_summary' => null,
                ]);

                $auditLogService->log(
                    action: 'payslip.whatsapp_sent',
                    subject: $delivery,
                    changes: [
                        'slip_number' => $payslip->slip_number,
                        'recipient' => $normalizedPhone,
                        'attempt' => $attemptNumber,
                        'provider' => $gateway->getName(),
                        'message_id' => $result->providerMessageId,
                    ]
                );
            } else {
                $sanitizedError = $this->sanitizeError($result->errorMessage ?? 'Pengiriman ditolak oleh gateway WhatsApp.');
                $isPermanent = $this->isPermanentError($result->errorCode);
                $isLastTry = $isPermanent || ($this->attempts() >= $this->tries) || ($attemptNumber >= $this->tries);

                $attempt->update([
                    'status' => DeliveryStatus::FAILED->value,
                    'error_code' => $result->errorCode ?? 'GATEWAY_ERROR',
                    'error_summary' => $sanitizedError,
                    'finished_at' => now(),
                ]);

                $delivery->update([
                    'status' => $isLastTry ? DeliveryStatus::FAILED->value : DeliveryStatus::PROCESSING->value,
                    'last_error_code' => $result->errorCode ?? 'GATEWAY_ERROR',
                    'last_error_summary' => $sanitizedError,
                ]);

                $auditLogService->log(
                    action: 'payslip.whatsapp_failed',
                    subject: $delivery,
                    changes: [
                        'slip_number' => $payslip->slip_number,
                        'recipient' => $normalizedPhone,
                        'attempt' => $attemptNumber,
                        'error_code' => $result->errorCode,
                        'error_summary' => $sanitizedError,
                    ]
                );

                if (! $isLastTry) {
                    throw new Exception("WhatsApp delivery failed: {$sanitizedError}");
                }
            }
        } catch (Throwable $e) {
            $sanitizedError = $this->sanitizeError($e->getMessage());
            $isLastTry = ($this->attempts() >= $this->tries) || ($attemptNumber >= $this->tries);

            $attempt->update([
                'status' => DeliveryStatus::FAILED->value,
                'error_code' => 'EXCEPTION',
                'error_summary' => $sanitizedError,
                'finished_at' => now(),
            ]);

            $delivery->update([
                'status' => $isLastTry ? DeliveryStatus::FAILED->value : DeliveryStatus::PROCESSING->value,
                'last_error_code' => 'EXCEPTION',
                'last_error_summary' => $sanitizedError,
            ]);

            $auditLogService->log(
                action: 'payslip.whatsapp_failed',
                subject: $delivery,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'recipient' => $normalizedPhone,
                    'attempt' => $attemptNumber,
                    'error_code' => 'EXCEPTION',
                    'error_summary' => $sanitizedError,
                ]
            );

            throw $e;
        }
    }

    /**
     * Handle job failure when retries are exhausted.
     */
    public function failed(?Throwable $exception = null): void
    {
        $delivery = PayslipDelivery::find($this->deliveryId);
        if ($delivery && $delivery->status !== DeliveryStatus::SENT->value && $delivery->status !== DeliveryStatus::DELIVERED->value) {
            $delivery->update([
                'status' => DeliveryStatus::FAILED->value,
                'last_error_code' => 'MAX_RETRIES_EXCEEDED',
                'last_error_summary' => $exception ? $this->sanitizeError($exception->getMessage()) : 'Batas percobaan pengiriman WhatsApp telah tercapai.',
            ]);
        }
    }

    /**
     * Determine if the gateway error code represents a permanent failure that should not be retried.
     */
    protected function isPermanentError(?string $errorCode): bool
    {
        $permanentCodes = [
            'AUTH_FAILED',
            'INVALID_API_KEY',
            'NOT_CONFIGURED',
            'CONFIG_ERROR',
            'DOCUMENT_NOT_SUPPORTED',
            'INVALID_RECIPIENT_PHONE',
            'NOT_REGISTERED',
            'NOT_FINALIZED',
            'INVALID_PAYLOAD',
        ];

        return in_array($errorCode, $permanentCodes, true);
    }

    /**
     * Sanitize error message to prevent leaking tokens or passwords into DB.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        $sanitized = preg_replace('/(bearer|token|secret|password|key|auth)[:=\s]+[^\s;&",]+/i', '$1=***', $rawMessage);
        $sanitized = preg_replace('/[a-zA-Z0-9_\-]{32,}/', '***', $sanitized ?? '');

        return mb_substr(trim($sanitized ?? ''), 0, 500);
    }
}
