<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Jobs\SendPayslipEmailJob;
use App\Jobs\SendPayslipWhatsAppJob;
use App\Models\IntegrationSetting;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Queue email delivery for an individual finalized payslip.
     */
    public function queueEmailDelivery(Payslip $payslip, ?User $actor = null): PayslipDelivery
    {
        if ($payslip->status !== PayslipStatus::FINALIZED->value) {
            throw new DomainException("Hanya slip gaji yang telah berstatus 'Final' yang dapat dikirimkan ke karyawan.");
        }

        $recipientEmail = trim((string) $payslip->email_snapshot);
        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException("Karyawan {$payslip->employee_name_snapshot} tidak memiliki alamat email yang valid pada data snapshot slip gaji.");
        }

        $idempotencyKey = "email_{$payslip->id}_{$payslip->payroll_period_id}_".md5(strtolower($recipientEmail));

        return DB::transaction(function () use ($payslip, $recipientEmail, $idempotencyKey, $actor) {
            $delivery = PayslipDelivery::where('payslip_id', $payslip->id)
                ->where('channel', DeliveryChannel::EMAIL->value)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $delivery = PayslipDelivery::create([
                    'payslip_id' => $payslip->id,
                    'channel' => DeliveryChannel::EMAIL->value,
                    'recipient' => $recipientEmail,
                    'status' => DeliveryStatus::QUEUED->value,
                    'idempotency_key' => $idempotencyKey,
                    'attempt_count' => 0,
                ]);
            }

            // If already queued, processing, or successfully sent, do not re-dispatch to ensure idempotency
            if (! $delivery->wasRecentlyCreated && in_array($delivery->status, [
                DeliveryStatus::QUEUED->value,
                DeliveryStatus::PROCESSING->value,
                DeliveryStatus::SENT->value,
                DeliveryStatus::DELIVERED->value,
            ])) {
                return $delivery;
            }

            $delivery->update([
                'status' => DeliveryStatus::QUEUED->value,
                'recipient' => $recipientEmail,
            ]);

            SendPayslipEmailJob::dispatch($delivery->id);

            $this->auditLogService->log(
                action: 'payslip.delivery_queued',
                subject: $delivery,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'channel' => 'email',
                    'recipient' => $recipientEmail,
                    'queued_by' => $actor?->name ?? 'Sistem',
                ]
            );

            return $delivery;
        });
    }

    /**
     * Queue WhatsApp delivery for an individual finalized payslip.
     */
    public function queueWhatsAppDelivery(Payslip $payslip, ?User $actor = null, int $delaySeconds = 0): PayslipDelivery
    {
        if ($payslip->status !== PayslipStatus::FINALIZED->value) {
            throw new DomainException("Hanya slip gaji yang telah berstatus 'Final' yang dapat dikirimkan ke karyawan.");
        }

        $rawPhone = trim((string) ($payslip->phone_snapshot ?? $payslip->employee?->phone));
        if (empty($rawPhone) || ! PhoneNumberNormalizer::isValid($rawPhone)) {
            throw new DomainException("Karyawan {$payslip->employee_name_snapshot} tidak memiliki nomor WhatsApp yang valid pada data snapshot slip gaji.");
        }

        $normalizedPhone = PhoneNumberNormalizer::normalize($rawPhone);
        $idempotencyKey = "wa_{$payslip->id}_{$payslip->payroll_period_id}_".md5($normalizedPhone);

        return DB::transaction(function () use ($payslip, $normalizedPhone, $idempotencyKey, $actor, $delaySeconds) {
            $delivery = PayslipDelivery::where('payslip_id', $payslip->id)
                ->where('channel', DeliveryChannel::WHATSAPP->value)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $delivery = PayslipDelivery::create([
                    'payslip_id' => $payslip->id,
                    'channel' => DeliveryChannel::WHATSAPP->value,
                    'recipient' => $normalizedPhone,
                    'status' => DeliveryStatus::QUEUED->value,
                    'idempotency_key' => $idempotencyKey,
                    'attempt_count' => 0,
                ]);
            }

            // If already queued, processing, or successfully sent, do not re-dispatch to ensure idempotency
            if (! $delivery->wasRecentlyCreated && in_array($delivery->status, [
                DeliveryStatus::QUEUED->value,
                DeliveryStatus::PROCESSING->value,
                DeliveryStatus::SENT->value,
                DeliveryStatus::DELIVERED->value,
            ])) {
                return $delivery;
            }

            $delivery->update([
                'status' => DeliveryStatus::QUEUED->value,
                'recipient' => $normalizedPhone,
            ]);

            $job = SendPayslipWhatsAppJob::dispatch($delivery->id);
            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($delaySeconds));
            }

            $this->auditLogService->log(
                action: 'payslip.delivery_queued',
                subject: $delivery,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'channel' => 'whatsapp',
                    'recipient' => $normalizedPhone,
                    'queued_by' => $actor?->name ?? 'Sistem',
                ]
            );

            return $delivery;
        });
    }

    /**
     * Queue email deliveries for all finalized payslips within an approved/finalized payroll period.
     */
    public function queuePeriodEmailDeliveries(PayrollPeriod $period, ?User $actor = null): array
    {
        if ($period->status !== PayrollPeriodStatus::FINALIZED->value) {
            throw new DomainException('Pengiriman email massal hanya dapat dilakukan pada periode gaji yang telah difinalisasi.');
        }

        $payslips = $period->payslips()
            ->where('status', PayslipStatus::FINALIZED->value)
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($payslips as $payslip) {
            $email = trim((string) $payslip->email_snapshot);
            if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            try {
                $this->queueEmailDelivery($payslip, $actor);
                $queued++;
            } catch (DomainException) {
                $skipped++;
            }
        }

        return [
            'total' => $payslips->count(),
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Queue WhatsApp deliveries for all finalized payslips within an approved/finalized payroll period.
     */
    public function queuePeriodWhatsAppDeliveries(PayrollPeriod $period, ?User $actor = null): array
    {
        if ($period->status !== PayrollPeriodStatus::FINALIZED->value) {
            throw new DomainException('Pengiriman WhatsApp massal hanya dapat dilakukan pada periode gaji yang telah difinalisasi.');
        }

        $payslips = $period->payslips()
            ->where('status', PayslipStatus::FINALIZED->value)
            ->get();

        $queued = 0;
        $skipped = 0;

        $whatsAppSetting = IntegrationSetting::forProvider('whatsapp');
        $sendInterval = (int) ($whatsAppSetting->config_json['send_interval_seconds'] ?? 2);

        foreach ($payslips as $payslip) {
            $rawPhone = trim((string) ($payslip->phone_snapshot ?? $payslip->employee?->phone));
            if (empty($rawPhone) || ! PhoneNumberNormalizer::isValid($rawPhone)) {
                $skipped++;

                continue;
            }

            try {
                $delaySeconds = $queued * $sendInterval;
                $this->queueWhatsAppDelivery($payslip, $actor, $delaySeconds);
                $queued++;
            } catch (DomainException) {
                $skipped++;
            }
        }

        return [
            'total' => $payslips->count(),
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Retry a failed payslip delivery (supports both Email and WhatsApp channels).
     */
    public function retryDelivery(PayslipDelivery $delivery, ?User $actor = null): PayslipDelivery
    {
        if (! $delivery->canRetry()) {
            throw new DomainException("Hanya pengiriman berstatus 'Gagal' yang dapat dikirim ulang (Retry).");
        }

        if ($delivery->payslip->status !== PayslipStatus::FINALIZED->value) {
            throw new DomainException("Slip gaji terkait belum berstatus 'Final'. Pengiriman ulang dibatalkan.");
        }

        return DB::transaction(function () use ($delivery, $actor) {
            $lockedDelivery = PayslipDelivery::where('id', $delivery->id)->lockForUpdate()->firstOrFail();

            $lockedDelivery->update([
                'status' => DeliveryStatus::QUEUED->value,
            ]);

            if ($lockedDelivery->channel === DeliveryChannel::WHATSAPP->value) {
                SendPayslipWhatsAppJob::dispatch($lockedDelivery->id);
            } else {
                SendPayslipEmailJob::dispatch($lockedDelivery->id);
            }

            $this->auditLogService->log(
                action: 'payslip.delivery_retried',
                subject: $lockedDelivery,
                changes: [
                    'slip_number' => $lockedDelivery->payslip->slip_number,
                    'channel' => $lockedDelivery->channel,
                    'recipient' => $lockedDelivery->recipient,
                    'previous_attempts' => $lockedDelivery->attempt_count,
                    'retried_by' => $actor?->name ?? 'Petugas',
                ]
            );

            return $lockedDelivery;
        });
    }

    /**
     * Get preview data for bulk delivery dispatch in a payroll period.
     */
    public function getBulkDeliveryPreview(int $periodId, string $channel = 'both'): array
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->status !== PayrollPeriodStatus::FINALIZED->value) {
            throw new DomainException('Pengiriman massal hanya dapat dilakukan pada periode gaji yang telah difinalisasi.');
        }

        $payslips = Payslip::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', PayslipStatus::FINALIZED->value)
            ->with(['deliveries', 'employee'])
            ->orderBy('employee_name_snapshot')
            ->get();

        $readyEmailCount = 0;
        $readyWhatsAppCount = 0;
        $missingEmailCount = 0;
        $missingWhatsAppCount = 0;
        $sentEmailCount = 0;
        $sentWhatsAppCount = 0;

        $items = $payslips->map(function ($slip) use (
            &$readyEmailCount,
            &$readyWhatsAppCount,
            &$missingEmailCount,
            &$missingWhatsAppCount,
            &$sentEmailCount,
            &$sentWhatsAppCount
        ) {
            $email = trim((string) ($slip->email_snapshot ?? $slip->employee?->email));
            $isEmailValid = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

            $phone = trim((string) ($slip->phone_snapshot ?? $slip->employee?->phone));
            $isPhoneValid = ! empty($phone) && PhoneNumberNormalizer::isValid($phone);
            $normalizedPhone = $isPhoneValid ? PhoneNumberNormalizer::normalize($phone) : null;

            $emailDelivery = $slip->deliveries->firstWhere('channel', DeliveryChannel::EMAIL->value);
            $whatsAppDelivery = $slip->deliveries->firstWhere('channel', DeliveryChannel::WHATSAPP->value);

            $emailStatus = $emailDelivery?->status;
            $whatsAppStatus = $whatsAppDelivery?->status;

            if ($isEmailValid) {
                if ($emailStatus === DeliveryStatus::SENT->value || $emailStatus === DeliveryStatus::DELIVERED->value) {
                    $sentEmailCount++;
                } else {
                    $readyEmailCount++;
                }
            } else {
                $missingEmailCount++;
            }

            if ($isPhoneValid) {
                if ($whatsAppStatus === DeliveryStatus::SENT->value || $whatsAppStatus === DeliveryStatus::DELIVERED->value) {
                    $sentWhatsAppCount++;
                } else {
                    $readyWhatsAppCount++;
                }
            } else {
                $missingWhatsAppCount++;
            }

            return [
                'payslip_id' => $slip->id,
                'slip_number' => $slip->slip_number,
                'employee_id' => $slip->employee_id,
                'employee_number' => $slip->employee_number_snapshot ?? $slip->employee?->employee_number,
                'employee_name' => $slip->employee_name_snapshot ?? $slip->employee?->full_name,
                'department' => $slip->department_snapshot ?? $slip->employee?->department,
                'position' => $slip->position_snapshot ?? $slip->employee?->position,
                'email' => $email,
                'is_email_valid' => $isEmailValid,
                'email_delivery_status' => $emailStatus,
                'phone' => $phone,
                'phone_normalized' => $normalizedPhone,
                'is_phone_valid' => $isPhoneValid,
                'whatsapp_delivery_status' => $whatsAppStatus,
                'net_salary' => $slip->net_salary,
            ];
        });

        return [
            'period' => [
                'id' => $period->id,
                'period_code' => $period->period_code,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'status' => $period->status,
            ],
            'channel' => $channel,
            'summary' => [
                'total_payslips' => $payslips->count(),
                'ready_email_count' => $readyEmailCount,
                'ready_whatsapp_count' => $readyWhatsAppCount,
                'missing_email_count' => $missingEmailCount,
                'missing_whatsapp_count' => $missingWhatsAppCount,
                'sent_email_count' => $sentEmailCount,
                'sent_whatsapp_count' => $sentWhatsAppCount,
            ],
            'recipients' => $items,
        ];
    }

    /**
     * Dispatch bulk deliveries for selected payslips across chosen channels.
     */
    public function queueBulkDelivery(
        int $periodId,
        string $channel,
        array $payslipIds,
        ?User $actor = null
    ): array {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->status !== PayrollPeriodStatus::FINALIZED->value) {
            throw new DomainException('Pengiriman massal hanya dapat dilakukan pada periode gaji yang telah difinalisasi.');
        }

        $payslips = Payslip::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', PayslipStatus::FINALIZED->value)
            ->whereIn('id', $payslipIds)
            ->get();

        if ($payslips->isEmpty()) {
            throw new DomainException('Tidak ada slip gaji yang dipilih untuk pengiriman.');
        }

        $emailQueued = 0;
        $whatsAppQueued = 0;
        $skipped = 0;

        $whatsAppSetting = IntegrationSetting::forProvider('whatsapp');
        $sendInterval = (int) ($whatsAppSetting->config_json['send_interval_seconds'] ?? 2);

        foreach ($payslips as $payslip) {
            // Email dispatch
            if ($channel === 'email' || $channel === 'both') {
                $email = trim((string) ($payslip->email_snapshot ?? $payslip->employee?->email));
                if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $this->queueEmailDelivery($payslip, $actor);
                        $emailQueued++;
                    } catch (DomainException) {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }

            // WhatsApp dispatch
            if ($channel === 'whatsapp' || $channel === 'both') {
                $phone = trim((string) ($payslip->phone_snapshot ?? $payslip->employee?->phone));
                if (! empty($phone) && PhoneNumberNormalizer::isValid($phone)) {
                    try {
                        $delaySeconds = $whatsAppQueued * $sendInterval;
                        $this->queueWhatsAppDelivery($payslip, $actor, $delaySeconds);
                        $whatsAppQueued++;
                    } catch (DomainException) {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }
        }

        $this->auditLogService->log(
            action: 'deliveries.bulk_dispatched',
            subject: $period,
            changes: [
                'period_code' => $period->period_code,
                'channel' => $channel,
                'total_selected' => count($payslipIds),
                'email_queued' => $emailQueued,
                'whatsapp_queued' => $whatsAppQueued,
                'skipped' => $skipped,
                'dispatched_by' => $actor?->name ?? 'Petugas',
            ]
        );

        return [
            'total_slips' => $payslips->count(),
            'email_queued' => $emailQueued,
            'whatsapp_queued' => $whatsAppQueued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Selectively retry all failed deliveries matching optional period and channel filters.
     */
    public function retryFailedDeliveries(
        ?int $periodId = null,
        ?string $channel = null,
        ?User $actor = null
    ): int {
        $query = PayslipDelivery::query()
            ->where('status', DeliveryStatus::FAILED->value)
            ->whereHas('payslip', function ($q) {
                $q->where('status', PayslipStatus::FINALIZED->value);
            });

        if ($periodId) {
            $query->whereHas('payslip', function ($q) use ($periodId) {
                $q->where('payroll_period_id', $periodId);
            });
        }

        if ($channel && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        $failedDeliveries = $query->with('payslip')->get();
        $retriedCount = 0;

        foreach ($failedDeliveries as $delivery) {
            try {
                $this->retryDelivery($delivery, $actor);
                $retriedCount++;
            } catch (DomainException) {
                // Skip if unable to retry
            }
        }

        if ($retriedCount > 0) {
            $this->auditLogService->log(
                action: 'deliveries.bulk_retried',
                subject: null,
                changes: [
                    'retried_count' => $retriedCount,
                    'period_id' => $periodId,
                    'channel' => $channel,
                    'retried_by' => $actor?->name ?? 'Petugas',
                ]
            );
        }

        return $retriedCount;
    }
}
