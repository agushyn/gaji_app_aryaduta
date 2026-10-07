<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipItem;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportService
{
    /**
     * Export payroll workbook with two sheets: Summary & Staff Pusat.
     */
    public function exportPayrollXlsx(PayrollPeriod $payrollPeriod): StreamedResponse
    {
        $payrollPeriod->load([
            'payslips.employee.office',
            'payslips.employee.position',
            'payslips.employee.division',
            'payslips.employee.department',
            'payslips.items',
            'payslips.office',
        ]);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('CV. ARYA DUTA')
            ->setLastModifiedBy('CV. ARYA DUTA Personalia')
            ->setTitle('Pengajuan Gaji Karyawan')
            ->setSubject('Rekapitulasi dan Rincian Payroll');

        // Sheet 1: Summary
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');

        // Sheet 2: Staff Pusat
        $staffPusatSheet = $spreadsheet->createSheet();
        $staffPusatSheet->setTitle('Staff Pusat');

        // Populate Sheet 2 (Staff Pusat) first so Summary can reference its total rows
        $totalStaffRow = $this->buildStaffPusatSheet($staffPusatSheet, $payrollPeriod);

        // Populate Sheet 1 (Summary) using formula references to Staff Pusat
        $this->buildSummarySheet($summarySheet, $payrollPeriod, $totalStaffRow);

        // Set default active sheet to Summary
        $spreadsheet->setActiveSheetIndex(0);

        $filename = $this->generateFilename($payrollPeriod);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0, must-revalidate',
                'Pragma' => 'public',
            ]
        );
    }

    /**
     * Build the Staff Pusat detail sheet and return the total row index.
     */
    protected function buildStaffPusatSheet(Worksheet $sheet, PayrollPeriod $payrollPeriod): int
    {
        $periodLabel = $this->getPeriodLabel($payrollPeriod);

        // 1. Header titles
        $sheet->setCellValue('A1', 'DAFTAR GAJI KARYAWAN CV. ARYA DUTA');
        $sheet->mergeCells('A1:AN1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Calibri');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A2', 'PERIODE: ' . strtoupper($periodLabel));
        $sheet->mergeCells('A2:AN2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10)->setName('Calibri');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // 2. Table Header Rows (Row 4 & 5)
        // Group Headers (Row 4)
        $sheet->setCellValue('A4', 'NO.');
        $sheet->mergeCells('A4:A5');

        $sheet->setCellValue('B4', 'NAMA KARYAWAN');
        $sheet->mergeCells('B4:B5');

        $sheet->setCellValue('C4', 'KANTOR');
        $sheet->mergeCells('C4:C5');

        $sheet->setCellValue('D4', 'JBT');
        $sheet->mergeCells('D4:D5');

        $sheet->setCellValue('E4', 'KET BPJS');
        $sheet->mergeCells('E4:E5');

        $sheet->setCellValue('F4', 'TGL. MASUK KERJA');
        $sheet->mergeCells('F4:F5');

        $sheet->setCellValue('G4', 'MS KERJA');
        $sheet->mergeCells('G4:G5');

        // Group: PERINCIAN GAJI (H4:Q4)
        $sheet->setCellValue('H4', 'PERINCIAN GAJI');
        $sheet->mergeCells('H4:Q4');
        $sheet->setCellValue('H5', 'GAJI POKOK');
        $sheet->setCellValue('I5', 'TF=%GP');
        $sheet->setCellValue('J5', 'TUNJ. FUNGSIONAL');
        $sheet->setCellValue('K5', 'JABATAN');
        $sheet->setCellValue('L5', 'UANG MAKAN');
        $sheet->setCellValue('M5', 'TRANSPORT');
        $sheet->setCellValue('N5', '%T. KELUARGA');
        $sheet->setCellValue('O5', 'T. KELUARGA');
        $sheet->setCellValue('P5', 'KEHADIRAN');
        $sheet->setCellValue('Q5', 'TUNJANGAN PULSA');

        $sheet->setCellValue('R4', 'GAJI BRUTO');
        $sheet->mergeCells('R4:R5');

        $sheet->setCellValue('S4', 'LEMBUR');
        $sheet->mergeCells('S4:S5');

        $sheet->setCellValue('T4', 'Biaya Kesehatan');
        $sheet->mergeCells('T4:T5');

        $sheet->setCellValue('U4', 'PJD Luar Kota');
        $sheet->mergeCells('U4:U5');

        $sheet->setCellValue('V4', "GAJI BRUTO\n(Gaji, Lembur & Kesehatan)");
        $sheet->mergeCells('V4:V5');

        // Group: ABSENSI (W4:AD4)
        $sheet->setCellValue('W4', 'ABSENSI');
        $sheet->mergeCells('W4:AD4');
        $sheet->setCellValue('W5', 'S');
        $sheet->setCellValue('X5', 'I,C');
        $sheet->setCellValue('Y5', 'A');
        $sheet->setCellValue('Z5', 'SAKIT');
        $sheet->setCellValue('AA5', 'SAKIT KET DOKTER');
        $sheet->setCellValue('AB5', 'IZIN');
        $sheet->setCellValue('AC5', 'CUTI');
        $sheet->setCellValue('AD5', 'ALPA');

        // Group: POTONGAN GAJI (AE4:AL4)
        $sheet->setCellValue('AE4', 'POTONGAN GAJI');
        $sheet->mergeCells('AE4:AL4');
        $sheet->setCellValue('AE5', 'POT. BPJS Kesehatan');
        $sheet->setCellValue('AF5', 'POT. ABSEN');
        $sheet->setCellValue('AG5', 'POT. ALPA');
        $sheet->setCellValue('AH5', 'Potongan BPJS Ketenagakerjaan');
        $sheet->setCellValue('AI5', 'Pinjaman');
        $sheet->setCellValue('AJ5', 'Iuran Koperasi');
        $sheet->setCellValue('AK5', 'Pinjaman Koperasi');
        $sheet->setCellValue('AL5', 'Lain-lain');

        $sheet->setCellValue('AM4', 'TOTAL POTONGAN');
        $sheet->mergeCells('AM4:AM5');

        $sheet->setCellValue('AN4', "GAJI NETTO /\nDITERIMA");
        $sheet->mergeCells('AN4:AN5');

        // Header Styling
        $headerRange = 'A4:AN5';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
                'name' => 'Calibri',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'], // Professional Dark Navy
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        // Specific sub-header background tone (Row 5)
        $sheet->getStyle('A5:AN5')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F5597'], // Steel Navy
            ],
        ]);
        // Keep merged headers coherent
        $sheet->getStyle('A4:G5')->getFill()->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('R4:V5')->getFill()->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('AM4:AN5')->getFill()->getStartColor()->setRGB('1F4E78');

        $sheet->getRowDimension(4)->setRowHeight(24);
        $sheet->getRowDimension(5)->setRowHeight(28);

        // 3. Populate Employee Rows
        $startRow = 6;
        $currentRow = $startRow;
        $payslips = $payrollPeriod->payslips;

        foreach ($payslips as $index => $payslip) {
            $employee = $payslip->employee;
            $itemsMap = $this->extractPayslipItems($payslip);

            $sheet->setCellValue('A' . $currentRow, $index + 1);
            $sheet->setCellValue('B' . $currentRow, $payslip->employee_name_snapshot ?? $employee?->full_name ?? 'Karyawan');
            $sheet->setCellValue('C' . $currentRow, $payslip->office_name_snapshot ?? $payslip->office?->name ?? 'Pusat');
            $sheet->setCellValue('D' . $currentRow, $payslip->position_name_snapshot ?? $payslip->position_snapshot ?? $employee?->position?->name ?? '-');

            // BPJS Note
            $bpjsKet = '-';
            if ($employee?->bpjs_ketenagakerjaan_enabled || ($itemsMap['bpjs_tk'] > 0)) {
                $bpjsKet = 'BPJS TK';
                if ($itemsMap['bpjs_kes'] > 0) {
                    $bpjsKet = 'BPJS TK/KES';
                }
            } elseif ($itemsMap['bpjs_kes'] > 0) {
                $bpjsKet = 'BPJS KES';
            }
            $sheet->setCellValue('E' . $currentRow, $bpjsKet);

            // Hire Date & Work Tenure
            if ($employee?->hire_date) {
                $sheet->setCellValue('F' . $currentRow, $employee->hire_date->format('Y-m-d'));
                $sheet->setCellValue('G' . $currentRow, '=IF(ISBLANK(F' . $currentRow . '),"-",DATEDIF(F' . $currentRow . ',TODAY(),"Y") & " Thn")');
            } else {
                $sheet->setCellValue('F' . $currentRow, '-');
                $sheet->setCellValue('G' . $currentRow, '-');
            }

            // Gaji & Tunjangan
            $sheet->setCellValue('H' . $currentRow, $itemsMap['gapok']);
            $sheet->setCellValue('I' . $currentRow, '=IF(H' . $currentRow . '>0,J' . $currentRow . '/H' . $currentRow . ',0)');
            $sheet->setCellValue('J' . $currentRow, $itemsMap['functional']);
            $sheet->setCellValue('K' . $currentRow, $itemsMap['jabatan']);
            $sheet->setCellValue('L' . $currentRow, $itemsMap['makan']);
            $sheet->setCellValue('M' . $currentRow, $itemsMap['transport']);
            $sheet->setCellValue('N' . $currentRow, '=IF(H' . $currentRow . '>0,O' . $currentRow . '/H' . $currentRow . ',0)');
            $sheet->setCellValue('O' . $currentRow, $itemsMap['keluarga']);
            $sheet->setCellValue('P' . $currentRow, $itemsMap['kehadiran']);
            $sheet->setCellValue('Q' . $currentRow, $itemsMap['pulsa']);

            // Gaji Bruto Component Formula
            $sheet->setCellValue('R' . $currentRow, '=H' . $currentRow . '+J' . $currentRow . '+K' . $currentRow . '+L' . $currentRow . '+M' . $currentRow . '+O' . $currentRow . '+P' . $currentRow . '+Q' . $currentRow);

            // Lembur, Biaya Kesehatan, PJD Luar Kota
            $sheet->setCellValue('S' . $currentRow, $itemsMap['lembur']);
            $sheet->setCellValue('T' . $currentRow, $itemsMap['kesehatan']);
            $sheet->setCellValue('U' . $currentRow, $itemsMap['pjd']);

            // Total Gaji Bruto (Gaji, Lembur & Kesehatan)
            $sheet->setCellValue('V' . $currentRow, '=R' . $currentRow . '+S' . $currentRow . '+T' . $currentRow . '+U' . $currentRow);

            // Absensi Counts
            $sheet->setCellValue('W' . $currentRow, $itemsMap['absensi_s']);
            $sheet->setCellValue('X' . $currentRow, $itemsMap['absensi_ic']);
            $sheet->setCellValue('Y' . $currentRow, $itemsMap['absensi_a']);
            $sheet->setCellValue('Z' . $currentRow, $itemsMap['absensi_sakit']);
            $sheet->setCellValue('AA' . $currentRow, $itemsMap['absensi_sakit_ket']);
            $sheet->setCellValue('AB' . $currentRow, $itemsMap['absensi_izin']);
            $sheet->setCellValue('AC' . $currentRow, $itemsMap['absensi_cuti']);
            $sheet->setCellValue('AD' . $currentRow, $itemsMap['absensi_alpa']);

            // Potongan Gaji
            $sheet->setCellValue('AE' . $currentRow, $itemsMap['bpjs_kes']);
            $sheet->setCellValue('AF' . $currentRow, $itemsMap['pot_absen']);
            $sheet->setCellValue('AG' . $currentRow, $itemsMap['pot_alpa']);
            $sheet->setCellValue('AH' . $currentRow, $itemsMap['bpjs_tk']);
            $sheet->setCellValue('AI' . $currentRow, $itemsMap['pinjaman']);
            $sheet->setCellValue('AJ' . $currentRow, $itemsMap['iuran_kop']);
            $sheet->setCellValue('AK' . $currentRow, $itemsMap['pinj_kop']);
            $sheet->setCellValue('AL' . $currentRow, $itemsMap['lain_lain']);

            // Total Potongan & Gaji Netto Formulas
            $sheet->setCellValue('AM' . $currentRow, '=SUM(AE' . $currentRow . ':AL' . $currentRow . ')');
            $sheet->setCellValue('AN' . $currentRow, '=V' . $currentRow . '-AM' . $currentRow);

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        $lastDataRow = max($startRow, $currentRow - 1);
        $totalRow = $currentRow;

        // Apply formatting across data rows
        if ($lastDataRow >= $startRow && $payslips->isNotEmpty()) {
            $dataRange = 'A' . $startRow . ':AN' . $lastDataRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            // Number formatting for currencies and percentages
            $currencyFormat = '#,##0;(#,##0);"-"';
            $sheet->getStyle('H' . $startRow . ':H' . $lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('I' . $startRow . ':I' . $lastDataRow)->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle('J' . $startRow . ':M' . $lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('N' . $startRow . ':N' . $lastDataRow)->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle('O' . $startRow . ':V' . $lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('W' . $startRow . ':AD' . $lastDataRow)->getNumberFormat()->setFormatCode('#,##0;-;0');
            $sheet->getStyle('AE' . $startRow . ':AN' . $lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);

            // Alignment
            $sheet->getStyle('A' . $startRow . ':A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $startRow . ':G' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('W' . $startRow . ':AD' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // 4. TOTAL ROW
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->mergeCells('A' . $totalRow . ':G' . $totalRow);

        $hasData = $payslips->isNotEmpty();
        $sumRange = fn (string $col) => $hasData ? "=SUM({$col}{$startRow}:{$col}{$lastDataRow})" : '0';

        $sheet->setCellValue('H' . $totalRow, $sumRange('H'));
        $sheet->setCellValue('I' . $totalRow, '-');
        $sheet->setCellValue('J' . $totalRow, $sumRange('J'));
        $sheet->setCellValue('K' . $totalRow, $sumRange('K'));
        $sheet->setCellValue('L' . $totalRow, $sumRange('L'));
        $sheet->setCellValue('M' . $totalRow, $sumRange('M'));
        $sheet->setCellValue('N' . $totalRow, '-');
        $sheet->setCellValue('O' . $totalRow, $sumRange('O'));
        $sheet->setCellValue('P' . $totalRow, $sumRange('P'));
        $sheet->setCellValue('Q' . $totalRow, $sumRange('Q'));
        $sheet->setCellValue('R' . $totalRow, $sumRange('R'));
        $sheet->setCellValue('S' . $totalRow, $sumRange('S'));
        $sheet->setCellValue('T' . $totalRow, $sumRange('T'));
        $sheet->setCellValue('U' . $totalRow, $sumRange('U'));
        $sheet->setCellValue('V' . $totalRow, $sumRange('V'));

        // Absensi Totals
        foreach (['W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD'] as $col) {
            $sheet->setCellValue($col . $totalRow, $sumRange($col));
        }

        // Potongan Totals
        foreach (['AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN'] as $col) {
            $sheet->setCellValue($col . $totalRow, $sumRange($col));
        }

        // Total Row Styling
        $totalRange = 'A' . $totalRow . ':AN' . $totalRow;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 9,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAEEF3'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1F4E78'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '1F4E78'],
                ],
                'left' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
                'right' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $currencyFormat = '#,##0;(#,##0);"-"';
        $sheet->getStyle('H' . $totalRow . ':V' . $totalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('W' . $totalRow . ':AD' . $totalRow)->getNumberFormat()->setFormatCode('#,##0;-;0');
        $sheet->getStyle('AE' . $totalRow . ':AN' . $totalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension($totalRow)->setRowHeight(22);

        // Auto-fit column widths with minimum padding
        $this->autoFitColumns($sheet, 'A', 'AN');

        return $totalRow;
    }

    /**
     * Build the Summary sheet referencing Staff Pusat totals.
     */
    protected function buildSummarySheet(Worksheet $sheet, PayrollPeriod $payrollPeriod, int $totalStaffRow): void
    {
        $periodLabel = $this->getPeriodLabel($payrollPeriod);

        // 1. Header Title
        $sheet->setCellValue('A1', 'PENGAJUAN GAJI KARYAWAN CV. ARYA DUTA ' . strtoupper($periodLabel));
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setName('Calibri');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // 2. Table Headers (Row 3 & Row 4)
        $sheet->setCellValue('A3', 'No.');
        $sheet->mergeCells('A3:A4');

        $sheet->setCellValue('B3', 'Cabang/Divisi');
        $sheet->mergeCells('B3:B4');

        // Group: PERINCIAN GAJI (C3:K3)
        $sheet->setCellValue('C3', 'PERINCIAN GAJI');
        $sheet->mergeCells('C3:K3');
        $sheet->setCellValue('C4', 'GAJI POKOK');
        $sheet->setCellValue('D4', 'TUNJ. FUNGSIONAL');
        $sheet->setCellValue('E4', 'JABATAN');
        $sheet->setCellValue('F4', 'UANG MAKAN');
        $sheet->setCellValue('G4', 'TRANSPORT');
        $sheet->setCellValue('H4', '% Tunjangan Keluarga');
        $sheet->setCellValue('I4', 'T.KELUARGA');
        $sheet->setCellValue('J4', 'KEHADIRAN');
        $sheet->setCellValue('K4', 'TUNJANGAN PULSA');

        $sheet->setCellValue('L3', 'Jumlah Karyawan');
        $sheet->mergeCells('L3:L4');

        // Group: Gaji, Lembur & Biaya Kesehatan (M3:Q3)
        $sheet->setCellValue('M3', 'Gaji, Lembur & Biaya Kesehatan');
        $sheet->mergeCells('M3:Q3');
        $sheet->setCellValue('M4', 'Gaji Bruto');
        $sheet->setCellValue('N4', 'Lembur');
        $sheet->setCellValue('O4', 'Biaya Kesehatan');
        $sheet->setCellValue('P4', 'PJD Luar Kota');
        $sheet->setCellValue('Q4', "Total Gaji Bruto,\nLembur & Biaya Kesehatan");

        // Group: Potongan & Iuran (R3:Y3)
        $sheet->setCellValue('R3', 'Potongan & Iuran');
        $sheet->mergeCells('R3:Y3');
        $sheet->setCellValue('R4', 'Pot. BPJS Kesehatan');
        $sheet->setCellValue('S4', 'Pot. Absen');
        $sheet->setCellValue('T4', 'Pot. Alpa');
        $sheet->setCellValue('U4', 'Pot. BPJS Ketenagakerjaan');
        $sheet->setCellValue('V4', 'Pinjaman');
        $sheet->setCellValue('W4', 'Iuran Koperasi');
        $sheet->setCellValue('X4', 'Pinjaman Koperasi');
        $sheet->setCellValue('Y4', 'Total Potongan');

        $sheet->setCellValue('Z3', "Gaji Netto /\nTotal Yang Diterima");
        $sheet->mergeCells('Z3:Z4');

        // Header Styling
        $headerRange = 'A3:Z4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
                'name' => 'Calibri',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $sheet->getStyle('A4:Z4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F5597'],
            ],
        ]);
        $sheet->getStyle('A3:B4')->getFill()->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('L3:L4')->getFill()->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('Z3:Z4')->getFill()->getStartColor()->setRGB('1F4E78');

        $sheet->getRowDimension(3)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(28);

        // 3. Row 5: Data Row for Staff Pusat (Linked to Staff Pusat sheet via Excel Formulas)
        $dataRow = 5;
        $sheet->setCellValue('A' . $dataRow, 1);
        $sheet->setCellValue('B' . $dataRow, 'Staff Pusat');

        // Perincian Gaji references
        $sheet->setCellValue('C' . $dataRow, "='Staff Pusat'!H{$totalStaffRow}");
        $sheet->setCellValue('D' . $dataRow, "='Staff Pusat'!J{$totalStaffRow}");
        $sheet->setCellValue('E' . $dataRow, "='Staff Pusat'!K{$totalStaffRow}");
        $sheet->setCellValue('F' . $dataRow, "='Staff Pusat'!L{$totalStaffRow}");
        $sheet->setCellValue('G' . $dataRow, "='Staff Pusat'!M{$totalStaffRow}");
        $sheet->setCellValue('H' . $dataRow, '-');
        $sheet->setCellValue('I' . $dataRow, "='Staff Pusat'!O{$totalStaffRow}");
        $sheet->setCellValue('J' . $dataRow, "='Staff Pusat'!P{$totalStaffRow}");
        $sheet->setCellValue('K' . $dataRow, "='Staff Pusat'!Q{$totalStaffRow}");

        // Jumlah Karyawan
        $lastStaffEmpRow = max(6, $totalStaffRow - 1);
        $sheet->setCellValue('L' . $dataRow, $payrollPeriod->payslips->count());

        // Gaji, Lembur & Biaya Kesehatan references
        $sheet->setCellValue('M' . $dataRow, "='Staff Pusat'!R{$totalStaffRow}");
        $sheet->setCellValue('N' . $dataRow, "='Staff Pusat'!S{$totalStaffRow}");
        $sheet->setCellValue('O' . $dataRow, "='Staff Pusat'!T{$totalStaffRow}");
        $sheet->setCellValue('P' . $dataRow, "='Staff Pusat'!U{$totalStaffRow}");
        $sheet->setCellValue('Q' . $dataRow, "='Staff Pusat'!V{$totalStaffRow}");

        // Potongan & Iuran references
        $sheet->setCellValue('R' . $dataRow, "='Staff Pusat'!AE{$totalStaffRow}");
        $sheet->setCellValue('S' . $dataRow, "='Staff Pusat'!AF{$totalStaffRow}");
        $sheet->setCellValue('T' . $dataRow, "='Staff Pusat'!AG{$totalStaffRow}");
        $sheet->setCellValue('U' . $dataRow, "='Staff Pusat'!AH{$totalStaffRow}");
        $sheet->setCellValue('V' . $dataRow, "='Staff Pusat'!AI{$totalStaffRow}");
        $sheet->setCellValue('W' . $dataRow, "='Staff Pusat'!AJ{$totalStaffRow}");
        $sheet->setCellValue('X' . $dataRow, "='Staff Pusat'!AK{$totalStaffRow}");
        $sheet->setCellValue('Y' . $dataRow, "='Staff Pusat'!AM{$totalStaffRow}");

        // Gaji Netto reference
        $sheet->setCellValue('Z' . $dataRow, "='Staff Pusat'!AN{$totalStaffRow}");

        $sheet->getRowDimension($dataRow)->setRowHeight(20);

        // Row 5 Styling
        $row5Range = 'A' . $dataRow . ':Z' . $dataRow;
        $sheet->getStyle($row5Range)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Calibri'],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $currencyFormat = '#,##0;(#,##0);"-"';
        $sheet->getStyle('C' . $dataRow . ':G' . $dataRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('I' . $dataRow . ':K' . $dataRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('L' . $dataRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('M' . $dataRow . ':Z' . $dataRow)->getNumberFormat()->setFormatCode($currencyFormat);

        $sheet->getStyle('A' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('L' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 4. Row 6: TOTAL Row
        $summaryTotalRow = 6;
        $sheet->setCellValue('A' . $summaryTotalRow, 'TOTAL');
        $sheet->mergeCells('A' . $summaryTotalRow . ':B' . $summaryTotalRow);

        foreach (['C', 'D', 'E', 'F', 'G', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'] as $col) {
            $sheet->setCellValue($col . $summaryTotalRow, "=SUM({$col}5:{$col}5)");
        }
        $sheet->setCellValue('H' . $summaryTotalRow, '-');

        // Total Row Styling
        $summaryTotalRange = 'A' . $summaryTotalRow . ':Z' . $summaryTotalRow;
        $sheet->getStyle($summaryTotalRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 9,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAEEF3'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1F4E78'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '1F4E78'],
                ],
                'left' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
                'right' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $sheet->getStyle('C' . $summaryTotalRow . ':G' . $summaryTotalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('I' . $summaryTotalRow . ':K' . $summaryTotalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('L' . $summaryTotalRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('M' . $summaryTotalRow . ':Z' . $summaryTotalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('A' . $summaryTotalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension($summaryTotalRow)->setRowHeight(22);

        // Auto-fit columns
        $this->autoFitColumns($sheet, 'A', 'Z');
    }

    /**
     * Map and extract payslip item amounts and attendance quantities.
     *
     * @return array<string, float|int>
     */
    protected function extractPayslipItems(Payslip $payslip): array
    {
        $result = [
            'gapok' => (float) $payslip->basic_salary,
            'functional' => 0.0,
            'jabatan' => 0.0,
            'makan' => 0.0,
            'transport' => 0.0,
            'keluarga' => 0.0,
            'kehadiran' => 0.0,
            'pulsa' => 0.0,
            'lembur' => 0.0,
            'kesehatan' => 0.0,
            'pjd' => (float) $payslip->uang_dinas,

            // Absensi counts
            'absensi_s' => 0,
            'absensi_ic' => 0,
            'absensi_a' => 0,
            'absensi_sakit' => 0,
            'absensi_sakit_ket' => 0,
            'absensi_izin' => 0,
            'absensi_cuti' => 0,
            'absensi_alpa' => 0,

            // Potongan
            'bpjs_kes' => 0.0,
            'pot_absen' => 0.0,
            'pot_alpa' => 0.0,
            'bpjs_tk' => 0.0,
            'pinjaman' => 0.0,
            'iuran_kop' => 0.0,
            'pinj_kop' => 0.0,
            'lain_lain' => 0.0,
        ];

        foreach ($payslip->items as $item) {
            $code = strtoupper((string) ($item->item_code_snapshot ?? ''));
            $desc = strtoupper((string) ($item->description_snapshot ?? ''));
            $type = strtolower((string) ($item->item_type ?? 'earning'));
            $category = strtolower((string) ($item->category ?? ''));
            $amount = (float) $item->amount;
            $qty = (int) round((float) ($item->quantity ?? 0));

            if ($type === 'earning') {
                if (in_array($code, ['BASIC_SALARY', 'GAJI_POKOK', 'GAPOK'], true) || str_contains($desc, 'GAJI POKOK')) {
                    $result['gapok'] = $amount;
                } elseif (in_array($code, ['FUNCTIONAL', 'FUNCTIONAL_ALLOWANCE', 'TUNJ_FUNGSIONAL'], true) || str_contains($desc, 'FUNGSIONAL')) {
                    $result['functional'] += $amount;
                } elseif (in_array($code, ['POSITION', 'POSITION_ALLOWANCE', 'TUNJ_JABATAN', 'JABATAN'], true) || str_contains($desc, 'JABATAN')) {
                    $result['jabatan'] += $amount;
                } elseif (in_array($code, ['MEAL', 'MEAL_ALLOWANCE', 'UANG_MAKAN'], true) || str_contains($desc, 'MAKAN')) {
                    $result['makan'] += $amount;
                } elseif (in_array($code, ['TRANSPORT', 'TRANSPORT_ALLOWANCE', 'UANG_TRANSPORT', 'TRANSPORTASI'], true) || str_contains($desc, 'TRANSPORT')) {
                    $result['transport'] += $amount;
                } elseif (in_array($code, ['FAMILY', 'FAMILY_ALLOWANCE', 'TUNJ_KELUARGA'], true) || str_contains($desc, 'KELUARGA')) {
                    $result['keluarga'] += $amount;
                } elseif (in_array($code, ['ATTENDANCE', 'ATTENDANCE_INCENTIVE', 'KEHADIRAN', 'TUNJ_KEHADIRAN'], true) || str_contains($desc, 'KEHADIRAN')) {
                    $result['kehadiran'] += $amount;
                } elseif (in_array($code, ['PHONE', 'COMMUNICATION_ALLOWANCE', 'PULSA', 'TUNJ_PULSA'], true) || str_contains($desc, 'PULSA') || str_contains($desc, 'KOMUNIKASI')) {
                    $result['pulsa'] += $amount;
                } elseif (in_array($code, ['OVERTIME', 'LEMBUR', 'UANG_LEMBUR'], true) || str_contains($desc, 'LEMBUR')) {
                    $result['lembur'] += $amount;
                } elseif (in_array($code, ['HEALTH_ALLOW', 'HEALTH_ALLOWANCE', 'BIAYA_KESEHATAN', 'KESEHATAN'], true) || str_contains($desc, 'KESEHATAN')) {
                    $result['kesehatan'] += $amount;
                } elseif (in_array($code, ['PJD', 'PJD_LUAR_KOTA', 'OFFICIAL_DUTY', 'UANG_DINAS'], true) || $category === 'official_duty' || str_contains($desc, 'DINAS') || str_contains($desc, 'PJD')) {
                    $result['pjd'] += $amount;
                } else {
                    // Fallback to functional/other earning
                    $result['functional'] += $amount;
                }
            } elseif ($type === 'deduction') {
                if (in_array($code, ['BPJS_KES', 'BPJS_KESEHATAN', 'POT_BPJS_KES'], true) || str_contains($desc, 'BPJS KES')) {
                    $result['bpjs_kes'] += $amount;
                } elseif (in_array($code, ['ATT_SIC', 'ATTENDANCE_CUT_SIC', 'POT_ABSEN'], true) || str_contains($desc, 'S,I,C') || str_contains($desc, 'S/I/C') || str_contains($desc, 'ABSEN')) {
                    $result['pot_absen'] += $amount;
                    if ($qty > 0) {
                        $result['absensi_ic'] += $qty;
                    }
                } elseif (in_array($code, ['ATT_ALPA', 'ALPHA_CUT', 'POT_ALPA'], true) || str_contains($desc, 'ALPA')) {
                    $result['pot_alpa'] += $amount;
                    if ($qty > 0) {
                        $result['absensi_alpa'] += $qty;
                        $result['absensi_a'] += $qty;
                    }
                } elseif (in_array($code, ['BPJS_TK', 'BPJS_KETENAGAKERJAAN', 'POT_BPJS_TK'], true) || str_contains($desc, 'BPJS TK') || str_contains($desc, 'KETENAGAKERJAAN')) {
                    $result['bpjs_tk'] += $amount;
                } elseif (in_array($code, ['LOCAL_LOAN', 'PINJAMAN', 'PINJAMAN_LOKAL'], true) || str_contains($desc, 'PINJAMAN LOKAL') || str_contains($desc, 'PINJAMAN KARYAWAN')) {
                    $result['pinjaman'] += $amount;
                } elseif (in_array($code, ['KOP_DUES', 'KOPERASI_MANDATORY', 'IURAN_KOPERASI'], true) || str_contains($desc, 'IURAN KOPERASI')) {
                    $result['iuran_kop'] += $amount;
                } elseif (in_array($code, ['KOP_LOAN', 'KOPERASI_LOAN', 'PINJAMAN_KOPERASI'], true) || str_contains($desc, 'PINJAMAN KOPERASI')) {
                    $result['pinj_kop'] += $amount;
                } else {
                    $result['lain_lain'] += $amount;
                }
            }
        }

        return $result;
    }

    /**
     * Generate dynamic filename: Payroll_[Nama_Cabang/Divisi]_[Nama_Bulan_Tahun].xlsx
     */
    protected function generateFilename(PayrollPeriod $payrollPeriod): string
    {
        $branchName = 'Staff_Pusat';

        $periodMonth = $this->getPeriodMonthYear($payrollPeriod);

        return "Payroll_{$branchName}_{$periodMonth}.xlsx";
    }

    /**
     * Get human-readable month & year string for filename.
     */
    protected function getPeriodMonthYear(PayrollPeriod $payrollPeriod): string
    {
        if (! empty($payrollPeriod->period_month_label)) {
            $cleaned = preg_replace('/[^a-zA-Z0-9]+/', '_', trim($payrollPeriod->period_month_label));
            return trim($cleaned, '_');
        }

        $date = $payrollPeriod->start_date ?? Carbon::now();
        $monthName = $date->translatedFormat('F');
        $year = $date->format('Y');

        return "{$monthName}_{$year}";
    }

    /**
     * Get label for period header.
     */
    protected function getPeriodLabel(PayrollPeriod $payrollPeriod): string
    {
        if (! empty($payrollPeriod->period_month_label)) {
            return $payrollPeriod->period_month_label;
        }

        if ($payrollPeriod->start_date && $payrollPeriod->end_date) {
            return $payrollPeriod->start_date->format('d/m/Y') . ' - ' . $payrollPeriod->end_date->format('d/m/Y');
        }

        return $payrollPeriod->period_code;
    }

    /**
     * Auto-fit columns with safety minimum widths to prevent Excel truncation (###).
     */
    protected function autoFitColumns(Worksheet $sheet, string $startCol, string $endCol): void
    {
        $startIdx = Coordinate::columnIndexFromString($startCol);
        $endIdx = Coordinate::columnIndexFromString($endCol);

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Apply calculated width plus buffer
        $sheet->calculateColumnWidths();

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $width = $sheet->getColumnDimension($colLetter)->getWidth();

            // Guarantee minimum width for readability
            $minWidth = 14;
            if (in_array($colLetter, ['A', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD'], true)) {
                $minWidth = 6;
            } elseif (in_array($colLetter, ['B'], true)) {
                $minWidth = 24;
            } elseif (in_array($colLetter, ['C', 'D', 'E'], true)) {
                $minWidth = 16;
            }

            if ($width < $minWidth) {
                $sheet->getColumnDimension($colLetter)->setAutoSize(false);
                $sheet->getColumnDimension($colLetter)->setWidth($minWidth);
            } else {
                $sheet->getColumnDimension($colLetter)->setAutoSize(false);
                $sheet->getColumnDimension($colLetter)->setWidth($width + 3);
            }
        }
    }
}
