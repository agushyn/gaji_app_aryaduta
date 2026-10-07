<?php

namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Payslip $payslip,
        public string $pdfContent
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $company = $this->payslip->company_name_snapshot ?? 'CV. Bina Pustaka';
        $period = $this->payslip->period_month_snapshot ?? 'Bulan Ini';
        $employee = $this->payslip->employee_name_snapshot;

        return new Envelope(
            subject: "Slip Gaji {$period} - {$employee} ({$company})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payslip',
            with: [
                'payslip' => $this->payslip,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $safeSlip = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->payslip->slip_number);
        $fileName = "Slip_Gaji_{$safeSlip}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfContent, $fileName)
                ->withMime('application/pdf'),
        ];
    }
}
