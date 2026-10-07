<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\PayslipDelivery;
use App\Services\AuditLogService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Handle delivery status callback webhook from WhatsApp gateway provider.
     */
    public function handle(Request $request): JsonResponse
    {
        $gateway = $this->whatsAppManager->getActiveGateway();

        // 1. Verify webhook authenticity (signature / secret token)
        if (! $gateway->verifyWebhookSignature($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Autentikasi signature atau token webhook tidak valid.',
            ], 401);
        }

        // 2. Parse incoming payload
        $callbackResult = $gateway->parseWebhookCallback($request);

        if (! $callbackResult || empty($callbackResult->providerMessageId)) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Payload webhook tidak berisi pesan ID yang dapat diproses.',
            ], 200);
        }

        // 3. Find corresponding delivery record idempotently
        $delivery = PayslipDelivery::where('provider_message_id', $callbackResult->providerMessageId)
            ->where('channel', 'whatsapp')
            ->first();

        if (! $delivery) {
            // Message ID might belong to a test ping or an unknown message
            return response()->json([
                'status' => 'ignored',
                'message' => 'Delivery record tidak ditemukan untuk message ID ini.',
                'message_id' => $callbackResult->providerMessageId,
            ], 200);
        }

        // 4. Update status idempotently in database transaction
        DB::transaction(function () use ($delivery, $callbackResult, $gateway) {
            $lockedDelivery = PayslipDelivery::where('id', $delivery->id)->lockForUpdate()->first();

            // Prevent state reversion: if already delivered, do not revert to sent or failed
            if ($lockedDelivery->status === DeliveryStatus::DELIVERED->value && $callbackResult->status !== DeliveryStatus::DELIVERED) {
                return;
            }

            if ($callbackResult->status === DeliveryStatus::DELIVERED) {
                $lockedDelivery->update([
                    'status' => DeliveryStatus::DELIVERED->value,
                    'delivered_at' => now(),
                    'last_error_code' => null,
                    'last_error_summary' => null,
                ]);

                // Record delivery receipt in attempts
                $lockedDelivery->attempts()->create([
                    'attempt_number' => $lockedDelivery->attempt_count + 1,
                    'status' => DeliveryStatus::DELIVERED->value,
                    'provider_message_id' => $callbackResult->providerMessageId,
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                $lockedDelivery->increment('attempt_count');

                $this->auditLogService->log(
                    action: 'payslip.whatsapp_delivered',
                    subject: $lockedDelivery,
                    changes: [
                        'slip_number' => $lockedDelivery->payslip?->slip_number,
                        'recipient' => $lockedDelivery->recipient,
                        'provider' => $gateway->getName(),
                        'message_id' => $callbackResult->providerMessageId,
                        'status' => 'delivered',
                    ]
                );
            } elseif ($callbackResult->status === DeliveryStatus::FAILED) {
                $lockedDelivery->update([
                    'status' => DeliveryStatus::FAILED->value,
                    'last_error_code' => $callbackResult->errorCode ?? 'WEBHOOK_FAILURE',
                    'last_error_summary' => $callbackResult->errorMessage ?? 'Laporan kegagalan dari webhook gateway',
                ]);

                $lockedDelivery->attempts()->create([
                    'attempt_number' => $lockedDelivery->attempt_count + 1,
                    'status' => DeliveryStatus::FAILED->value,
                    'provider_message_id' => $callbackResult->providerMessageId,
                    'error_code' => $callbackResult->errorCode ?? 'WEBHOOK_FAILURE',
                    'error_summary' => $callbackResult->errorMessage ?? 'Laporan kegagalan dari webhook gateway',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                $lockedDelivery->increment('attempt_count');

                $this->auditLogService->log(
                    action: 'payslip.whatsapp_failed',
                    subject: $lockedDelivery,
                    changes: [
                        'slip_number' => $lockedDelivery->payslip?->slip_number,
                        'recipient' => $lockedDelivery->recipient,
                        'provider' => $gateway->getName(),
                        'message_id' => $callbackResult->providerMessageId,
                        'status' => 'failed',
                        'error' => $callbackResult->errorMessage,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'status' => 'processed',
            'delivery_id' => $delivery->id,
            'message_id' => $callbackResult->providerMessageId,
            'new_status' => $callbackResult->status->value,
        ], 200);
    }
}
