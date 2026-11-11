<?php

namespace App\Services\Security;

use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AuditLogService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityPayrollExportService
{
    public function __construct(
        protected SecurityPayrollReportService $reportService,
        protected SecurityReconciliationService $reconciliationService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Sanitize a cell value to prevent CSV Formula Injection (CWE-1236).
     *
     * Prepends a single quote (') to strings that begin with =, +, -, @, tab, or carriage return,
     * while preserving legitimate numeric values.
     */
    public static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // Check if raw string begins with formula triggers including tab and carriage return
        $firstRawChar = $value[0];
        if (in_array($firstRawChar, ["\t", "\r"], true)) {
            return "'".$value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        // If it's a valid integer or decimal (e.g. "-15.50" or "+100"), preserve as numeric
        if (is_numeric($trimmed)) {
            return $value;
        }

        $firstChar = $trimmed[0];
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Export report data as XLSX or CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(string $type, PayrollPeriod $period, string $format, array $filters = [], ?User $actor = null): StreamedResponse
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        // Log export activity in audit trail
        $this->auditLogService->log(
            action: 'security_payroll.exported',
            subject: $period,
            changes: [
                'type' => $type,
                'format' => $format,
                'period_code' => $period->period_code,
                'filters' => $filters,
                'exported_by' => $actor?->name ?? 'Sistem',
            ]
        );

        $data = $this->resolveReportData($type, $period, $filters);
        $headers = $data['headers'];
        $rows = $data['rows'];
        $title = $data['title'];
        $filenameBase = $data['filename_base'].'_'.$period->period_code.'_'.date('Ymd_His');

        if ($format === 'csv') {
            return $this->streamCsv($filenameBase.'.csv', $headers, $rows);
        }

        return $this->streamXlsx($filenameBase.'.xlsx', $title, $headers, $rows);
    }

    /**
     * Resolve report data structure based on report type.
     *
     * @param  array<string, mixed>  $filters
     * @return array{title: string, filename_base: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    protected function resolveReportData(string $type, PayrollPeriod $period, array $filters): array
    {
        return match ($type) {
            'employee-detail', 'detail' => $this->prepareEmployeeDetailData($period, $filters),
            'shortage' => $this->prepareShortageData($period, $filters),
            'overtime' => $this->prepareOvertimeData($period, $filters),
            'reconciliation' => $this->prepareReconciliationData($period, $filters),
            'audit' => $this->prepareAuditData($period, $filters),
            default => $this->preparePeriodSummaryData($period, $filters),
        };
    }

    protected function preparePeriodSummaryData(PayrollPeriod $period, array $filters): array
    {
        $summary = $this->reportService->getPeriodSummaryReport($filters);
        $headers = [
            'Periode Payroll',
            'Rentang Tanggal',
            'Hari Periode',
            'Status',
            'Jumlah Security',
            'Total Jam Lembur Reguler',
            'Total Biaya Lembur Reguler',
            'Total Jam Lembur Libur',
            'Total Biaya Lembur Libur',
            'Total Biaya Lembur',
            'Total Penghasilan',
            'Total Potongan',
            'Take Home Pay (THP)',
            'Karyawan Kurang Jam',
        ];

        $rows = [];
        foreach ($summary as $s) {
            $rows[] = [
                $s['period_code'],
                $s['start_date'].' - '.$s['end_date'],
                $s['period_days'],
                $s['status_label'],
                $s['total_employees'],
                $s['total_regular_overtime_hours'],
                $s['total_regular_overtime_cost'],
                $s['total_off_holiday_overtime_hours'],
                $s['total_off_holiday_overtime_cost'],
                $s['total_overtime_cost'],
                $s['total_earnings'],
                $s['total_deductions'],
                $s['total_net_salary'],
                $s['shortage_count'],
            ];
        }

        return [
            'title' => 'RINGKASAN PAYROLL SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_ringkasan_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function prepareEmployeeDetailData(PayrollPeriod $period, array $filters): array
    {
        $details = $this->reportService->getEmployeeDetailReport($period, $filters);
        $headers = [
            'NIK',
            'Nama Karyawan',
            'Kantor',
            'Departemen',
            'Hari Masuk',
            'Hari OFF',
            'Sakit',
            'Izin',
            'Cuti',
            'Alfa',
            'Jam Hadir',
            'Target Jam',
            'Kurang Jam Berjalan',
            'Carry Forward Lalu',
            'Total Kurang Jam',
            'Hari Lembur Reguler',
            'Jam Lembur Reguler',
            'Jam Bersih Reguler',
            'Biaya Lembur Reguler',
            'Hari Lembur Libur',
            'Jam Lembur Libur',
            'Jam Bersih Libur',
            'Biaya Lembur Libur',
            'Total Lembur',
            'Total Penghasilan',
            'Total Potongan',
            'Take Home Pay (THP)',
            'Status Slip',
        ];

        $rows = [];
        foreach ($details as $d) {
            $rows[] = [
                $d['employee_number'],
                $d['employee_name'],
                $d['office'],
                $d['department'],
                $d['present_days'],
                $d['off_days'],
                $d['sick_days'],
                $d['permission_days'],
                $d['leave_days'],
                $d['absent_days'],
                $d['attendance_hours'],
                $d['target_hours'],
                $d['current_shortage'],
                $d['previous_shortage'],
                $d['total_shortage'],
                $d['regular_overtime_days'],
                $d['regular_overtime_hours'],
                $d['regular_net_overtime_hours'],
                $d['regular_overtime_amount'],
                $d['off_holiday_overtime_days'],
                $d['off_holiday_overtime_hours'],
                $d['off_holiday_net_overtime_hours'],
                $d['off_holiday_overtime_amount'],
                $d['total_overtime_amount'],
                $d['total_earnings'],
                $d['total_deductions'],
                $d['net_salary'],
                $d['payslip_status_label'],
            ];
        }

        return [
            'title' => 'RINCIAN DETAIL PAYROLL SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_detail_karyawan_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function prepareShortageData(PayrollPeriod $period, array $filters): array
    {
        $shortages = $this->reportService->getShortageReport($period, $filters);
        $headers = [
            'NIK',
            'Nama Karyawan',
            'Kantor',
            'Periode',
            'Target Jam',
            'Jam Hadir Aktual',
            'Kurang Jam Periode',
            'Carry Forward Lalu',
            'Sumber Periode Lalu',
            'Total Kurang Jam',
            'Kompensasi Lembur (Offset)',
            'Sisa Kurang Jam',
            'Status',
        ];

        $rows = [];
        foreach ($shortages as $s) {
            $rows[] = [
                $s['employee_number'],
                $s['employee_name'],
                $s['office'],
                $s['period_code'],
                $s['target_hours'],
                $s['attendance_hours'],
                $s['current_shortage'],
                $s['previous_shortage'],
                $s['source_period'],
                $s['total_shortage'],
                $s['shortage_offset'],
                $s['remaining_shortage'],
                $s['status'],
            ];
        }

        return [
            'title' => 'LAPORAN KEKURANGAN JAM DAN KOMPENSASI SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_shortage_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function prepareOvertimeData(PayrollPeriod $period, array $filters): array
    {
        $overtime = $this->reportService->getOvertimeReport($period, $filters);
        $headers = [
            'NIK',
            'Nama Karyawan',
            'Kantor',
            'Periode',
            'Kategori Lembur',
            'Hari Lembur',
            'Jam Input Lembur',
            'Potongan Istirahat',
            'Jam Bersih Lembur',
            'Upah / Jam',
            'Nominal Lembur',
            'Status Tarif',
        ];

        $rows = [];
        foreach ($overtime as $o) {
            $rows[] = [
                $o['employee_number'],
                $o['employee_name'],
                $o['office'],
                $o['period_code'],
                $o['category_label'],
                $o['overtime_days'],
                $o['input_hours'],
                $o['break_deduction'],
                $o['net_hours'],
                $o['hourly_rate'],
                $o['amount'],
                $o['status'],
            ];
        }

        return [
            'title' => 'LAPORAN LEMBUR SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_overtime_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function prepareReconciliationData(PayrollPeriod $period, array $filters): array
    {
        $reconciliation = $this->reconciliationService->reconcilePeriod($period, $filters);
        $headers = [
            'NIK',
            'Nama Karyawan',
            'Departemen',
            'Kantor',
            'Pemeriksaan',
            'Nilai Sumber',
            'Nilai Target / Pembanding',
            'Selisih',
            'Status',
            'Petunjuk / Keterangan',
        ];

        $rows = [];
        foreach ($reconciliation['results'] as $res) {
            foreach ($res['checks'] as $c) {
                $rows[] = [
                    $res['employee_number'],
                    $res['employee_name'],
                    $res['department'],
                    $res['office'],
                    $c['name'],
                    $c['source_value'],
                    $c['target_value'],
                    $c['difference'],
                    $c['status'],
                    $c['guidance'],
                ];
            }
        }

        return [
            'title' => 'HASIL REKONSILIASI PAYROLL SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_rekonsiliasi_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function prepareAuditData(PayrollPeriod $period, array $filters): array
    {
        $events = $this->reportService->getAuditHistoryReport($period, $filters);
        $headers = [
            'Waktu',
            'Tipe',
            'Aksi / Event',
            'Aktor',
            'Detail / Komentar',
        ];

        $rows = [];
        foreach ($events as $e) {
            $rows[] = [
                $e['created_at'],
                $e['type'],
                $e['action_label'],
                $e['actor_name'],
                $e['comment'],
            ];
        }

        return [
            'title' => 'HISTORI AUDIT PAYROLL SECURITY PERIODE '.$period->period_code,
            'filename_base' => 'laporan_audit_security',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Stream CSV with UTF-8 BOM and formula injection protection.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write Header
            fputcsv($handle, array_map([self::class, 'sanitizeCell'], $headers));

            // Write Rows with CWE-1236 Sanitization
            foreach ($rows as $row) {
                $sanitizedRow = array_map([self::class, 'sanitizeCell'], $row);
                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $responseHeaders);
    }

    /**
     * Stream styled XLSX workbook using PhpOffice PhpSpreadsheet.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function streamXlsx(string $filename, string $title, array $headers, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');

        // Document Title Header
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A2', 'Diekspor pada: '.now()->translatedFormat('d F Y H:i:s'));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        $headerRow = 4;
        $colIndex = 1;

        foreach ($headers as $h) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter.$headerRow, $h);
            $sheet->getStyle($colLetter.$headerRow)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $colIndex++;
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A'.$headerRow.':'.$lastColLetter.$headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('1E293B');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $currentRow = 5;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $cellValue) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter.$currentRow, $cellValue);

                if (is_numeric($cellValue) && ! str_starts_with((string) $headers[$colIndex - 1], 'NIK') && ! str_starts_with((string) $headers[$colIndex - 1], 'No')) {
                    if (str_contains(strtolower($headers[$colIndex - 1]), 'biaya') || str_contains(strtolower($headers[$colIndex - 1]), 'nominal') || str_contains(strtolower($headers[$colIndex - 1]), 'upah') || str_contains(strtolower($headers[$colIndex - 1]), 'penghasilan') || str_contains(strtolower($headers[$colIndex - 1]), 'potongan') || str_contains(strtolower($headers[$colIndex - 1]), 'thp')) {
                        $sheet->getStyle($colLetter.$currentRow)->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                $colIndex++;
            }
            $currentRow++;
        }

        // Apply grid borders
        $tableRange = 'A'.$headerRow.':'.$lastColLetter.($currentRow - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

        // Auto-fit column widths
        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0, must-revalidate',
                'Pragma' => 'public',
            ]
        );
    }
}
