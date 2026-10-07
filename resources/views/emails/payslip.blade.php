<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Slip Gaji Karyawan</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      line-height: 1.6;
      color: #334155;
      background-color: #f8fafc;
      margin: 0;
      padding: 24px;
    }
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .header {
      background: #0f172a;
      color: #ffffff;
      padding: 24px 32px;
      text-align: left;
    }
    .company-name {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin: 0;
    }
    .doc-subtitle {
      font-size: 12px;
      color: #94a3b8;
      margin-top: 4px;
    }
    .content {
      padding: 32px;
    }
    .greeting {
      font-size: 15px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 16px;
    }
    .paragraph {
      font-size: 14px;
      color: #475569;
      margin-bottom: 16px;
    }
    .card-info {
      background: #f1f5f9;
      border-left: 4px solid #4f46e5;
      padding: 16px;
      border-radius: 6px;
      margin: 20px 0;
      font-size: 13px;
    }
    .card-row {
      margin-bottom: 6px;
    }
    .card-label {
      font-weight: 600;
      color: #334155;
      display: inline-block;
      width: 140px;
    }
    .footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 20px 32px;
      font-size: 12px;
      color: #64748b;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">
      <h1 class="company-name">{{ $payslip->company_name_snapshot ?? 'CV. BINA PUSTAKA' }}</h1>
      <div class="doc-subtitle">Pemberitahuan Slip Gaji Karyawan Resmi</div>
    </div>

    <div class="content">
      <div class="greeting">Yth. Bapak/Ibu {{ $payslip->employee_name_snapshot }},</div>

      <p class="paragraph">
        Bersama email ini, kami sampaikan dokumen resmi Slip Gaji Anda untuk periode 
        <strong>{{ $payslip->period_month_snapshot }}</strong>.
      </p>

      <div class="card-info">
        <div class="card-row">
          <span class="card-label">Nomor Slip:</span>
          <strong>{{ $payslip->slip_number }}</strong>
        </div>
        <div class="card-row">
          <span class="card-label">Nomor Induk (NIK):</span>
          {{ $payslip->employee_number_snapshot }}
        </div>
        <div class="card-row">
          <span class="card-label">Jabatan:</span>
          {{ $payslip->position_snapshot ?: '-' }}
        </div>
        <div class="card-row">
          <span class="card-label">Cabang / Divisi:</span>
          {{ $payslip->department_snapshot ?: '-' }}
        </div>
        <div class="card-row">
          <span class="card-label">Periode:</span>
          {{ $payslip->period_start_date_snapshot }} s/d {{ $payslip->period_end_date_snapshot }}
        </div>
      </div>

      <p class="paragraph">
        Rincian lengkap penghasilan, potongan, dan total penerimaan telah terlampir dalam berkas PDF resmi terlampir pada email ini. Dokumen PDF dilindungi dan diverifikasi menggunakan stempel digital integritas (SHA-256).
      </p>

      <p class="paragraph">
        Bila Anda memiliki pertanyaan atau kendala terkait rincian tersebut, silakan menghubungi bagian Personalia / HR.
      </p>

      <p class="paragraph" style="margin-top: 24px;">
        Hormat kami,<br>
        <strong>Bagian Personalia</strong><br>
        {{ $payslip->company_name_snapshot ?? 'CV. BINA PUSTAKA' }}
      </p>
    </div>

    <div class="footer">
      Email ini dikirim secara otomatis oleh Sistem Penggajian Personalia {{ $payslip->company_name_snapshot ?? 'CV. BINA PUSTAKA' }}. Harap tidak membalas email ini secara langsung (No-Reply).
    </div>
  </div>
</body>
</html>
