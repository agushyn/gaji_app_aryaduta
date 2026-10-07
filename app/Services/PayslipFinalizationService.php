<?php

namespace App\Services;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\CompanySetting;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayslipFinalizationService
{
    public function __construct(
        protected PayslipPdfService $pdfService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Finalize an entire approved payroll period and all its approved payslips.
     * Generates immutable PDFs and computes SHA-256 for all payslips.
     */
    public function finalizePeriod(PayrollPeriod $period, User $finalizer): PayrollPeriod
    {
        // Idempotency check
        if ($period->status === PayrollPeriodStatus::FINALIZED->value) {
            return $period->load(['payslips', 'finalizer']);
        }

        if ($period->status !== PayrollPeriodStatus::APPROVED->value) {
            throw new DomainException("Periode penggajian tidak dapat difinalisasi karena belum disetujui (Status saat ini: {$period->status_label}).");
        }

        // Verify that all payslips in this period are approved or already finalized
        $unapprovedCount = $period->payslips()
            ->whereNotIn('status', [PayslipStatus::APPROVED->value, PayslipStatus::FINALIZED->value])
            ->count();

        if ($unapprovedCount > 0) {
            throw new DomainException("Tidak dapat memfinalisasi periode: Terdapat {$unapprovedCount} slip gaji yang belum berstatus 'Disetujui'.");
        }

        return DB::transaction(function () use ($period, $finalizer) {
            $lockedPeriod = PayrollPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->status === PayrollPeriodStatus::FINALIZED->value) {
                return $lockedPeriod->load(['payslips', 'finalizer']);
            }

            $now = now();

            // Fetch and lock all approved payslips
            $payslips = $lockedPeriod->payslips()
                ->where('status', PayslipStatus::APPROVED->value)
                ->lockForUpdate()
                ->get();

            foreach ($payslips as $payslip) {
                $this->ensureSnapshotsComplete($payslip);

                $payslip->update([
                    'status' => PayslipStatus::FINALIZED->value,
                    'finalized_by' => $finalizer->id,
                    'finalized_at' => $now,
                ]);

                // Generate PDF and calculate SHA-256
                $this->pdfService->generatePdf($payslip);

                $this->auditLogService->log(
                    action: 'payslip.finalized',
                    subject: $payslip,
                    changes: [
                        'slip_number' => $payslip->slip_number,
                        'status' => 'finalized',
                        'finalized_by' => $finalizer->name,
                        'pdf_sha256' => $payslip->pdf_sha256,
                    ]
                );
            }

            $lockedPeriod->update([
                'status' => PayrollPeriodStatus::FINALIZED->value,
                'finalized_by' => $finalizer->id,
                'finalized_at' => $now,
            ]);

            $this->auditLogService->log(
                action: 'payroll_period.finalized',
                subject: $lockedPeriod,
                changes: [
                    'period_code' => $lockedPeriod->period_code,
                    'status' => 'finalized',
                    'finalized_by' => $finalizer->name,
                    'finalized_slips_count' => $payslips->count(),
                ]
            );

            return $lockedPeriod->fresh(['payslips', 'finalizer']);
        });
    }

    /**
     * Finalize an individual approved payslip.
     * Generates immutable PDF and computes SHA-256 checksum.
     */
    public function finalizePayslip(Payslip $payslip, User $finalizer): Payslip
    {
        // Idempotency check: if already finalized, ensure PDF exists and return safely
        if ($payslip->status === PayslipStatus::FINALIZED->value) {
            if (! $payslip->pdf_path || ! Storage::disk('local')->exists($payslip->pdf_path)) {
                $this->pdfService->generatePdf($payslip);
            }

            return $payslip->fresh(['employee', 'payrollPeriod', 'finalizer']);
        }

        if ($payslip->status !== PayslipStatus::APPROVED->value) {
            throw new DomainException("Slip gaji tidak dapat difinalisasi karena belum disetujui (Status saat ini: {$payslip->status_label}).");
        }

        return DB::transaction(function () use ($payslip, $finalizer) {
            $lockedPayslip = Payslip::where('id', $payslip->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayslip->status === PayslipStatus::FINALIZED->value) {
                if (! $lockedPayslip->pdf_path || ! Storage::disk('local')->exists($lockedPayslip->pdf_path)) {
                    $this->pdfService->generatePdf($lockedPayslip);
                }

                return $lockedPayslip->load(['employee', 'payrollPeriod', 'finalizer']);
            }

            if ($lockedPayslip->status !== PayslipStatus::APPROVED->value) {
                throw new DomainException("Slip gaji tidak dapat difinalisasi karena belum disetujui (Status saat ini: {$lockedPayslip->status_label}).");
            }

            $this->ensureSnapshotsComplete($lockedPayslip);

            $now = now();

            $lockedPayslip->update([
                'status' => PayslipStatus::FINALIZED->value,
                'finalized_by' => $finalizer->id,
                'finalized_at' => $now,
            ]);

            // Generate PDF and calculate SHA-256
            $this->pdfService->generatePdf($lockedPayslip);

            $this->auditLogService->log(
                action: 'payslip.finalized',
                subject: $lockedPayslip,
                changes: [
                    'slip_number' => $lockedPayslip->slip_number,
                    'status' => 'finalized',
                    'finalized_by' => $finalizer->name,
                    'pdf_sha256' => $lockedPayslip->pdf_sha256,
                ]
            );

            return $lockedPayslip->fresh(['employee', 'payrollPeriod', 'finalizer']);
        });
    }

    /**
     * Ensure all snapshot fields are completely filled prior to PDF generation.
     */
    protected function ensureSnapshotsComplete(Payslip $payslip): void
    {
        $updates = [];

        if (empty($payslip->company_name_snapshot)) {
            $updates['company_name_snapshot'] = CompanySetting::get('company_name', 'CV. BINA PUSTAKA');
        }
        if (empty($payslip->company_address_snapshot)) {
            $updates['company_address_snapshot'] = CompanySetting::get('company_address', 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.');
        }
        if (empty($payslip->signatory_personalia_name)) {
            $updates['signatory_personalia_name'] = CompanySetting::get('signatory_personalia_name', 'Singgih Sugiarto');
        }
        if (empty($payslip->signatory_personalia_title)) {
            $updates['signatory_personalia_title'] = CompanySetting::get('signatory_personalia_title', 'Personalia');
        }
        if (empty($payslip->signatory_reliever_name)) {
            $updates['signatory_reliever_name'] = CompanySetting::get('signatory_reliever_name', 'Sheren Regiana');
        }
        if (empty($payslip->signatory_reliever_title)) {
            $updates['signatory_reliever_title'] = CompanySetting::get('signatory_reliever_title', 'Yang Menyerahkan');
        }
        if (empty($payslip->signatory_receiver_name)) {
            $updates['signatory_receiver_name'] = $payslip->employee_name_snapshot ?: $payslip->employee?->full_name;
        }

        $period = $payslip->payrollPeriod;
        if ($period) {
            if (empty($payslip->working_days_snapshot)) {
                $updates['working_days_snapshot'] = $period->working_days ?? (int) CompanySetting::get('default_working_days', 25);
            }
            if (empty($payslip->period_month_snapshot)) {
                $updates['period_month_snapshot'] = $period->period_month_label ?? ($period->start_date ? $period->start_date->translatedFormat('F Y') : $period->period_code);
            }
            if (empty($payslip->period_start_date_snapshot)) {
                $updates['period_start_date_snapshot'] = $period->start_date;
            }
            if (empty($payslip->period_end_date_snapshot)) {
                $updates['period_end_date_snapshot'] = $period->end_date;
            }
        }

        // Recalculate gaji_bruto and uang_dinas if they are zero while earnings exist
        if ((float) $payslip->gaji_bruto === 0.0 && (float) $payslip->total_earnings > 0.0) {
            $dutyIncludedInGross = config('payroll.official_duty_included_in_gross', false);
            $officialDutyCodes = config('payroll.official_duty_codes', ['OFFICIAL_DUTY', 'UANG_DINAS', 'PJD', 'PJD_LUAR_KOTA']);

            $bruto = '0.00';
            $dinas = '0.00';
            foreach ($payslip->items as $item) {
                if ($item->item_type === 'earning') {
                    $code = strtoupper($item->item_code_snapshot ?? '');
                    $isDuty = in_array($code, $officialDutyCodes, true) || strtolower($item->category ?? '') === 'official_duty';
                    if ($isDuty) {
                        $dinas = bcadd($dinas, (string) $item->amount, 2);
                        if ($dutyIncludedInGross) {
                            $bruto = bcadd($bruto, (string) $item->amount, 2);
                        }
                    } else {
                        $bruto = bcadd($bruto, (string) $item->amount, 2);
                    }
                }
            }
            $updates['gaji_bruto'] = $bruto;
            $updates['uang_dinas'] = $dinas;
        }

        if (! empty($updates)) {
            $payslip->update($updates);
            $payslip->refresh();
        }
    }
}
