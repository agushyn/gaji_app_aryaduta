<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Models\PayslipDelivery;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Retrieve aggregated statistics and filtered delivery listings for reporting.
     */
    public function getDeliveryReport(array $filters, ?User $viewer = null, int $perPage = 20): array
    {
        $canViewFinancial = $viewer !== null && $viewer->hasPermission('payslips.financial-view');

        $query = $this->buildFilteredQuery($filters);

        // Calculate summary counts on filtered set
        $totalCount = (clone $query)->count();
        $deliveredCount = (clone $query)->where('payslip_deliveries.status', DeliveryStatus::DELIVERED->value)->count();
        $sentCount = (clone $query)->where('payslip_deliveries.status', DeliveryStatus::SENT->value)->count();
        $failedCount = (clone $query)->where('payslip_deliveries.status', DeliveryStatus::FAILED->value)->count();
        $queuedCount = (clone $query)->whereIn('payslip_deliveries.status', [DeliveryStatus::QUEUED->value, DeliveryStatus::PROCESSING->value])->count();

        $emailCount = (clone $query)->where('payslip_deliveries.channel', DeliveryChannel::EMAIL->value)->count();
        $whatsAppCount = (clone $query)->where('payslip_deliveries.channel', DeliveryChannel::WHATSAPP->value)->count();

        $successfulDeliveries = $deliveredCount + $sentCount;
        $successRate = $totalCount > 0 ? round(($successfulDeliveries / $totalCount) * 100, 1) : 0;

        // Paginate results on an isolated cloned query
        $paginated = (clone $query)->with([
            'payslip:id,slip_number,payroll_period_id,employee_name_snapshot,employee_number_snapshot,department_snapshot,position_snapshot,net_salary',
            'payslip.payrollPeriod:id,period_code,start_date,end_date',
        ])
            ->latest('payslip_deliveries.updated_at')
            ->paginate($perPage)
            ->withQueryString();

        // Transform collection to mask net_salary if viewer is unauthorized
        $paginated->getCollection()->transform(function ($delivery) use ($canViewFinancial) {
            $slip = $delivery->payslip;
            if ($slip) {
                $slip->display_salary = $canViewFinancial
                    ? 'Rp '.number_format((float) $slip->net_salary, 0, ',', '.')
                    : '[TERBATAS]';
            }

            return $delivery;
        });

        // Department breakdown
        $departments = (clone $query)
            ->reorder()
            ->join('payslips', 'payslip_deliveries.payslip_id', '=', 'payslips.id')
            ->selectRaw('COALESCE(payslips.department_snapshot, "Tanpa Departemen") as department, count(*) as count')
            ->groupBy('department')
            ->orderByDesc('count')
            ->pluck('count', 'department')
            ->toArray();

        return [
            'summary' => [
                'total_deliveries' => $totalCount,
                'delivered' => $deliveredCount,
                'sent' => $sentCount,
                'failed' => $failedCount,
                'queued' => $queuedCount,
                'email_total' => $emailCount,
                'whatsapp_total' => $whatsAppCount,
                'success_rate' => $successRate,
            ],
            'departments' => $departments,
            'deliveries' => $paginated,
            'can_view_financial' => $canViewFinancial,
        ];
    }

    /**
     * Export delivery records as a streamed CSV file with role-based masking.
     */
    public function exportCsv(array $filters, ?User $viewer = null): StreamedResponse
    {
        $canViewFinancial = $viewer !== null && $viewer->hasPermission('payslips.financial-view');

        $query = $this->buildFilteredQuery($filters)->with([
            'payslip:id,slip_number,payroll_period_id,employee_name_snapshot,employee_number_snapshot,department_snapshot,position_snapshot,net_salary',
            'payslip.payrollPeriod:id,period_code',
        ])->latest('updated_at');

        $filename = 'laporan_pengiriman_slip_gaji_'.date('Ymd_His').'.csv';

        $this->auditLogService->log(
            action: 'reports.delivery_exported',
            subject: null,
            changes: [
                'filters' => $filters,
                'financial_included' => $canViewFinancial,
                'exported_by' => $viewer?->name ?? 'Petugas',
            ]
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query, $canViewFinancial) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header Row
            fputcsv($handle, [
                'No. Slip Gaji',
                'Periode',
                'NIK Karyawan',
                'Nama Karyawan',
                'Departemen',
                'Jabatan',
                'Kanal',
                'Tujuan Penerima',
                'Status Pengiriman',
                'Jumlah Percobaan',
                'Take Home Pay',
                'Waktu Terkirim',
                'Pesan Kesalahan Terakhir',
            ]);

            $query->chunk(250, function ($deliveries) use ($handle, $canViewFinancial) {
                foreach ($deliveries as $d) {
                    $slip = $d->payslip;
                    $salaryValue = $canViewFinancial
                        ? ($slip ? (float) $slip->net_salary : 0)
                        : '[TERBATAS]';

                    fputcsv($handle, [
                        $slip?->slip_number ?? '-',
                        $slip?->payrollPeriod?->period_code ?? '-',
                        $slip?->employee_number_snapshot ?? '-',
                        $slip?->employee_name_snapshot ?? '-',
                        $slip?->department_snapshot ?? '-',
                        $slip?->position_snapshot ?? '-',
                        strtoupper($d->channel),
                        $d->recipient,
                        strtoupper($d->status),
                        $d->attempt_count,
                        $salaryValue,
                        $d->sent_at ? $d->sent_at->format('Y-m-d H:i:s') : '-',
                        $d->last_error_summary ?? '-',
                    ]);
                }
            });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Build the filtered Eloquent query for deliveries.
     */
    protected function buildFilteredQuery(array $filters)
    {
        $query = PayslipDelivery::query();

        if (! empty($filters['channel']) && $filters['channel'] !== 'all') {
            $query->where('payslip_deliveries.channel', $filters['channel']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('payslip_deliveries.status', $filters['status']);
        }

        if (! empty($filters['period_id']) && $filters['period_id'] !== 'all') {
            $query->whereHas('payslip', function ($q) use ($filters) {
                $q->where('payroll_period_id', $filters['period_id']);
            });
        }

        if (! empty($filters['department']) && $filters['department'] !== 'all') {
            $query->whereHas('payslip', function ($q) use ($filters) {
                $q->where('department_snapshot', $filters['department']);
            });
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('payslip_deliveries.recipient', 'like', "%{$search}%")
                    ->orWhereHas('payslip', function ($sq) use ($search) {
                        $sq->where('employee_name_snapshot', 'like', "%{$search}%")
                            ->orWhere('employee_number_snapshot', 'like', "%{$search}%")
                            ->orWhere('slip_number', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
