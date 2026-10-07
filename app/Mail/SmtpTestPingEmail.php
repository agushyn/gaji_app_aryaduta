<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestPingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $testedAt
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uji Coba Koneksi SMTP - Sistem Penggajian Personalia',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '
                <div style="font-family: sans-serif; line-height: 1.6; color: #334155; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <h2 style="color: #0f172a; margin-top: 0;">Uji Koneksi Server Email Berhasil</h2>
                    <p>Halo,</p>
                    <p>Ini adalah email uji coba koneksi SMTP dari Sistem Informasi Penggajian Personalia CV. Bina Pustaka.</p>
                    <p>Koneksi server SMTP Anda telah berhasil diverifikasi dan siap digunakan untuk pengiriman berkas slip gaji resmi.</p>
                    <p style="font-size: 12px; color: #64748b; background: #f8fafc; padding: 10px; border-radius: 6px;">
                        Waktu pengujian: '.e($this->testedAt).' WIB.<br>
                        <em>Pesan ini tidak memuat data gaji atau informasi karyawan rahasia apa pun.</em>
                    </p>
                    <p>Salam,<br><strong>Bagian Personalia / IT Support</strong></p>
                </div>
            '
        );
    }
}
