<?php

namespace App\Services;

use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipPdfService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Generate the private PDF for a payslip from its snapshot data,
     * calculate SHA-256 hash, and save it on the private storage disk.
     */
    public function generatePdf(Payslip $payslip): Payslip
    {
        $payslip->loadMissing(['items', 'employee', 'payrollPeriod']);

        // Configure DomPDF for secure, standalone server-side rendering
        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
        ]);

        // Set custom physical paper size: 110 mm x 95 mm (311.81 pt x 269.29 pt)
        $pdf->setPaper([0, 0, 311.81, 269.29], 'portrait');
        $pdf->setOptions([
            'isRemoteEnabled' => false, // Prevent external SSRF
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 96,
        ]);

        $output = $pdf->output();
        $sha256 = hash('sha256', $output);

        // Store privately outside public web root
        $periodId = $payslip->payroll_period_id ?? 'global';
        $safeSlip = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip->slip_number);
        $fileName = "slip_{$payslip->id}_{$safeSlip}.pdf";
        $relativePath = "payslips/{$periodId}/{$fileName}";

        Storage::disk('local')->put($relativePath, $output);

        $payslip->update([
            'pdf_path' => $relativePath,
            'pdf_sha256' => $sha256,
            'pdf_generated_at' => now(),
        ]);

        return $payslip;
    }

    /**
     * Preview payslip PDF inline for authorized browser display.
     */
    public function previewPdf(Payslip $payslip): Response
    {
        $content = $this->getPdfContent($payslip);
        $fileName = $this->getDownloadFilename($payslip);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Download payslip PDF as an attachment.
     */
    public function downloadPdf(Payslip $payslip): StreamedResponse
    {
        if (! $payslip->pdf_path || ! Storage::disk('local')->exists($payslip->pdf_path)) {
            $this->generatePdf($payslip);
            $payslip->refresh();
        }

        $fileName = $this->getDownloadFilename($payslip);

        return Storage::disk('local')->download($payslip->pdf_path, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Retrieve the binary content of the payslip PDF, generating it if necessary.
     */
    public function getPdfContent(Payslip $payslip): string
    {
        if (! $payslip->pdf_path || ! Storage::disk('local')->exists($payslip->pdf_path)) {
            $this->generatePdf($payslip);
            $payslip->refresh();
        }

        $content = Storage::disk('local')->get($payslip->pdf_path);

        if (! $content) {
            throw new DomainException("Dokumen PDF untuk slip gaji {$payslip->slip_number} tidak dapat dibaca dari storage privat.");
        }

        return $content;
    }

    /**
     * Verify whether the physical file matches the stored SHA-256 hash.
     */
    public function verifyHash(Payslip $payslip): bool
    {
        if (! $payslip->pdf_path || ! $payslip->pdf_sha256) {
            return false;
        }

        if (! Storage::disk('local')->exists($payslip->pdf_path)) {
            return false;
        }

        $content = Storage::disk('local')->get($payslip->pdf_path);
        $actualHash = hash('sha256', $content);

        return hash_equals($payslip->pdf_sha256, $actualHash);
    }

    /**
     * Generate safe download filename.
     */
    protected function getDownloadFilename(Payslip $payslip): string
    {
        $safeSlip = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip->slip_number);

        return "Slip_Gaji_{$safeSlip}.pdf";
    }
}
