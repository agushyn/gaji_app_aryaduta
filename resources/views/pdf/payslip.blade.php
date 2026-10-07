<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Slip Gaji - {{ $payslip->slip_number }}</title>
  <style>
    @page {
      size: 110mm 95mm;
      margin: 1.5mm 3mm 1.5mm 3mm;
    }
    * {
      box-sizing: border-box;
    }
    html, body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 4.2pt;
      line-height: 1.05;
      color: #111111;
      margin: 0;
      padding: 0;
      background-color: #ffffff;
      height: 95mm;
      max-height: 95mm;
      overflow: hidden;
    }
    .slip-page {
      width: 100%;
      page-break-inside: avoid;
      page-break-after: avoid;
      page-break-before: avoid;
    }
    .header-company {
      margin-bottom: 0.5px;
      text-align: left;
    }
    .header-company .name {
      font-size: 6.5pt;
      font-weight: bold;
      letter-spacing: 0.1px;
      line-height: 7.5pt;
    }
    .header-company .address {
      font-size: 3.8pt;
      color: #333333;
      line-height: 4.6pt;
    }
    .doc-title {
      text-align: center;
      font-weight: bold;
      font-size: 5.8pt;
      margin: 0.5px 0 1px 0;
      letter-spacing: 0.3px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      page-break-inside: avoid;
      page-break-after: avoid;
    }
    tr, td {
      page-break-inside: avoid;
      page-break-after: avoid;
    }
    .border-dashed {
      border: 0.5pt dashed #222222;
    }
    .border-t-dashed {
      border-top: 0.5pt dashed #222222;
    }
    .border-b-dashed {
      border-bottom: 0.5pt dashed #222222;
    }
    .border-r-dashed {
      border-right: 0.5pt dashed #222222;
    }
    .border-l-dashed {
      border-left: 0.5pt dashed #222222;
    }
    .info-table td {
      padding: 0.2px 1.5px;
      font-size: 4.1pt;
      vertical-align: top;
      line-height: 5.1pt;
    }
    .columns-table {
      width: 100%;
      border-collapse: collapse;
    }
    .columns-table > tbody > tr > td {
      width: 50%;
      vertical-align: top;
      padding: 0.8px 2px;
    }
    .section-title {
      font-weight: bold;
      font-size: 4.4pt;
      margin-bottom: 0.5px;
    }
    .items-table td {
      padding: 0.15px 0.6px;
      font-size: 4pt;
      vertical-align: middle;
      line-height: 4.9pt;
    }
    .text-right {
      text-align: right;
    }
    .text-center {
      text-align: center;
    }
    .text-bold {
      font-weight: bold;
    }
    .total-box {
      padding: 0.8px 3px;
      font-weight: bold;
      font-size: 5.3pt;
    }
    .signature-table td {
      padding-top: 0.5px;
      padding-bottom: 0.5px;
      text-align: center;
      vertical-align: bottom;
      font-size: 4.1pt;
    }
    .signature-name {
      text-decoration: underline;
      font-weight: bold;
      font-size: 4.1pt;
    }
    .signature-title {
      font-size: 3.6pt;
      color: #333333;
    }
  </style>
</head>
<body>

@php
  // Helper Formatter
  $formatSlipCurrency = function($amount) {
    if ($amount === null || $amount === '' || (float) $amount == 0.0) {
      return '-';
    }
    return number_format((float) $amount, 0, ',', '.');
  };

  $formatTotalCurrency = function($amount) {
    return number_format((float) ($amount ?? 0), 0, ',', '.');
  };

  $formatSlipQty = function($qty, $unit = '') {
    return (!empty($qty) && (float) $qty > 0) ? (float) $qty . ($unit ? ' ' . $unit : '') : '-' . ($unit ? ' ' . $unit : '');
  };

  $indonesianMonths = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
  ];

  $formatIndoDate = function($date) use ($indonesianMonths) {
    if (! $date) return '-';
    $carbon = is_string($date) ? \Carbon\Carbon::parse($date) : $date;
    return $carbon->day . ' ' . ($indonesianMonths[$carbon->month] ?? $carbon->format('F')) . ' ' . $carbon->year;
  };

  $periodDateRange = '-';
  if ($payslip->period_start_date_snapshot && $payslip->period_end_date_snapshot) {
    $startDate = is_string($payslip->period_start_date_snapshot) ? \Carbon\Carbon::parse($payslip->period_start_date_snapshot) : $payslip->period_start_date_snapshot;
    $endDate = is_string($payslip->period_end_date_snapshot) ? \Carbon\Carbon::parse($payslip->period_end_date_snapshot) : $payslip->period_end_date_snapshot;
    
    if ($startDate->year === $endDate->year && $startDate->month === $endDate->month) {
      $periodDateRange = $startDate->day . ' - ' . $endDate->day . ' ' . ($indonesianMonths[$startDate->month] ?? '') . ' ' . $startDate->year;
    } else {
      $periodDateRange = $startDate->day . ' ' . ($indonesianMonths[$startDate->month] ?? '') . ' - ' . $endDate->day . ' ' . ($indonesianMonths[$endDate->month] ?? '') . ' ' . $endDate->year;
    }
  } elseif ($payslip->payrollPeriod) {
    $periodDateRange = $formatIndoDate($payslip->payrollPeriod->start_date) . ' - ' . $formatIndoDate($payslip->payrollPeriod->end_date);
  }

  $periodMonth = $payslip->period_month_snapshot 
    ?? ($payslip->payrollPeriod?->period_month_label 
    ?? ($payslip->payrollPeriod?->start_date ? $payslip->payrollPeriod->start_date->translatedFormat('F Y') : ($payslip->payrollPeriod?->period_code ?? '-')));

  // Extract components statically
  $items = $payslip->items;

  $getItem = function($codes, $descKeywords = [], $type = null) use ($items) {
    if (is_string($codes)) $codes = [$codes];
    if (is_string($descKeywords)) $descKeywords = [$descKeywords];

    foreach ($items as $item) {
      if ($type && $item->item_type !== $type) continue;
      $code = strtoupper($item->item_code_snapshot ?? '');
      $desc = strtoupper($item->description_snapshot ?? '');

      foreach ($codes as $c) {
        if ($code === strtoupper($c)) return $item;
      }
      foreach ($descKeywords as $kw) {
        if (str_contains($desc, strtoupper($kw))) return $item;
      }
    }
    return null;
  };

  // 1. Kolom Kiri: GAJI
  $itemGapok = $getItem(['BASIC_SALARY', 'GAJI_POKOK', 'GAPOK'], ['GAJI POKOK'], 'earning');
  $valGapok = $itemGapok ? (float) $itemGapok->amount : ((float) $payslip->basic_salary > 0 ? (float) $payslip->basic_salary : 0);

  $itemMakan = $getItem(['MEAL_ALLOWANCE', 'MEAL', 'UANG_MAKAN'], ['MAKAN'], 'earning');
  $rateMakan = $itemMakan && (float) $itemMakan->rate > 0 ? (float) $itemMakan->rate : (float) ($payslip->employee?->uang_makan_per_hari ?? 0);
  $valMakan = $itemMakan ? (float) $itemMakan->amount : 0;

  $itemTransport = $getItem(['TRANSPORT_ALLOWANCE', 'TRANSPORT', 'UANG_TRANSPORT', 'TRANSPORTASI'], ['TRANSPORT'], 'earning');
  $rateTransport = $itemTransport && (float) $itemTransport->rate > 0 ? (float) $itemTransport->rate : (float) ($payslip->employee?->uang_transportasi_per_hari ?? 0);
  $valTransport = $itemTransport ? (float) $itemTransport->amount : 0;

  $itemLembur = $getItem(['OVERTIME', 'LEMBUR', 'UANG_LEMBUR'], ['LEMBUR'], 'earning');
  $periodeLembur = $itemLembur?->note ?: ($payslip->overtime_period_snapshot ?: $periodDateRange);
  $employeeOvertimeRate = (float) ($payslip->employee?->default_overtime_rate ?? 0);
  $rateLembur = $itemLembur && (float) $itemLembur->rate > 0 ? (float) $itemLembur->rate : $employeeOvertimeRate;
  $valLembur = $itemLembur ? (float) $itemLembur->amount : 0;

  $itemFunctional = $getItem(['FUNCTIONAL_ALLOWANCE', 'FUNCTIONAL', 'TUNJ_FUNGSIONAL'], ['FUNGSIONAL'], 'earning');
  $valFunctional = $itemFunctional ? (float) $itemFunctional->amount : 0;

  $itemJabatan = $getItem(['POSITION_ALLOWANCE', 'POSITION', 'TUNJ_JABATAN', 'JABATAN'], ['JABATAN'], 'earning');
  $valJabatan = $itemJabatan ? (float) $itemJabatan->amount : 0;

  $itemKeluarga = $getItem(['FAMILY_ALLOWANCE', 'FAMILY', 'TUNJ_KELUARGA'], ['KELUARGA'], 'earning');
  $valKeluarga = $itemKeluarga ? (float) $itemKeluarga->amount : 0;

  $itemKehadiran = $getItem(['ATTENDANCE_INCENTIVE', 'ATTENDANCE', 'KEHADIRAN', 'TUNJ_KEHADIRAN'], ['KEHADIRAN'], 'earning');
  $valKehadiran = $itemKehadiran ? (float) $itemKehadiran->amount : 0;

  $itemPulsa = $getItem(['COMMUNICATION_ALLOWANCE', 'PHONE', 'PULSA', 'TUNJ_PULSA'], ['PULSA', 'KOMUNIKASI'], 'earning');
  $valPulsa = $itemPulsa ? (float) $itemPulsa->amount : 0;

  $itemKesehatan = $getItem(['HEALTH_ALLOWANCE', 'HEALTH_ALLOW', 'BIAYA_KESEHATAN', 'KESEHATAN'], ['KESEHATAN'], 'earning');
  $valKesehatan = $itemKesehatan ? (float) $itemKesehatan->amount : 0;

  $valGajiBruto = (float) $payslip->gaji_bruto > 0 ? (float) $payslip->gaji_bruto : 0;

  // 2. Kolom Kanan: POTONGAN & UANG DINAS
  $itemBpjsKes = $getItem(['BPJS_KES', 'BPJS_KESEHATAN', 'POT_BPJS_KES'], ['BPJS KES'], 'deduction');
  $valBpjsKes = $itemBpjsKes ? (float) $itemBpjsKes->amount : 0;

  $itemSic = $getItem(['ATT_SIC', 'ATTENDANCE_CUT_SIC', 'POT_ABSEN'], ['S,I,C', 'S/I/C', 'ABSEN'], 'deduction');
  $qtySic = $itemSic && (float) $itemSic->quantity > 0 ? (int) round((float) $itemSic->quantity) : 0;
  $valSic = $itemSic ? (float) $itemSic->amount : 0;

  $itemAlpa = $getItem(['ATT_ALPA', 'ALPHA_CUT', 'POT_ALPA'], ['ALPA'], 'deduction');
  $qtyAlpa = $itemAlpa && (float) $itemAlpa->quantity > 0 ? (int) round((float) $itemAlpa->quantity) : 0;
  $valAlpa = $itemAlpa ? (float) $itemAlpa->amount : 0;

  $itemBpjsTk = $getItem(['BPJS_TK', 'BPJS_KETENAGAKERJAAN', 'POT_BPJS_TK'], ['BPJS TK', 'KETENAGAKERJAAN'], 'deduction');
  $valBpjsTk = $itemBpjsTk ? (float) $itemBpjsTk->amount : 0;

  $itemIuranKop = $getItem(['KOP_DUES', 'KOPERASI_MANDATORY', 'IURAN_KOPERASI'], ['IURAN KOPERASI'], 'deduction');
  $valIuranKop = $itemIuranKop ? (float) $itemIuranKop->amount : 0;

  $itemPinjKop = $getItem(['KOP_LOAN', 'KOPERASI_LOAN', 'PINJAMAN_KOPERASI'], ['PINJAMAN KOPERASI'], 'deduction');
  $valPinjKop = $itemPinjKop ? (float) $itemPinjKop->amount : 0;

  $itemPinjaman = $getItem(['LOCAL_LOAN', 'PINJAMAN_LOKAL', 'PINJAMAN'], ['PINJAMAN LOKAL', 'PINJAMAN KARYAWAN', 'PINJAMAN'], 'deduction');
  $valPinjaman = $itemPinjaman ? (float) $itemPinjaman->amount : 0;

  $itemKendaraan = $getItem(['VEHICLE_INST', 'VEHICLE_INSTALLMENT', 'ANGSURAN_KENDARAAN'], ['KENDARAAN', 'ANGSURAN'], 'deduction');
  $valKendaraan = $itemKendaraan ? (float) $itemKendaraan->amount : 0;

  $itemOff = $getItem(['OFF_DED', 'OFF_DAYS_CUT', 'JUMLAH_OFF'], ['OFF'], 'deduction');
  $qtyOff = $itemOff && (float) $itemOff->quantity > 0 ? (int) round((float) $itemOff->quantity) : 0;
  $valOff = $itemOff ? (float) $itemOff->amount : 0;

  $valTotalPotongan = (float) $payslip->total_deductions > 0 ? (float) $payslip->total_deductions : 0;

  $itemPjd = $getItem(['OFFICIAL_DUTY', 'PJD_LUAR_KOTA', 'PJD', 'UANG_DINAS'], ['PJD', 'DINAS'], 'earning');
  $valPjd = (float) $payslip->uang_dinas > 0 ? (float) $payslip->uang_dinas : ($itemPjd ? (float) $itemPjd->amount : 0);

  $valTotalTerima = (float) $payslip->net_salary > 0 ? (float) $payslip->net_salary : 0;

  $officeName = $payslip->office_name_snapshot ?: ($payslip->company_name_snapshot ?? 'CV. BINA PUSTAKA');
  $officeAddress = $payslip->office_address_snapshot ?: ($payslip->company_address_snapshot ?? 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.');
  $divisionName = $payslip->division_name_snapshot ?: ($payslip->department_snapshot ?: '-');
  $positionName = $payslip->position_name_snapshot ?: ($payslip->position_snapshot ?: '-');
@endphp

<div class="slip-page">

  <!-- Header Kantor (Snapshot) -->
  <div class="header-company">
    <div class="name">{{ $officeName }}</div>
    <div class="address">{{ $officeAddress }}</div>
  </div>

  <!-- Judul Dokumen -->
  <div class="doc-title">
    SLIP GAJI KARYAWAN
  </div>

  <!-- Kotak Info Karyawan & Periode (2 Kolom Info Bersih) -->
  <table class="border-dashed info-table" style="margin-bottom: 0;">
    <tr>
      <td style="width: 52px;">Nama Karyawan</td>
      <td style="width: 3px;">:</td>
      <td class="text-bold" style="width: 90px;">{{ $payslip->employee_name_snapshot }}</td>
      <td style="width: 52px;">Bulan</td>
      <td style="width: 3px;">:</td>
      <td>{{ $periodMonth }}</td>
    </tr>
    <tr>
      <td>Cabang/Divisi</td>
      <td>:</td>
      <td>{{ $divisionName }}</td>
      <td>Periode</td>
      <td>:</td>
      <td>{{ $periodDateRange }}</td>
    </tr>
    <tr>
      <td>Jabatan</td>
      <td>:</td>
      <td>{{ $positionName }}</td>
      <td>Jumlah hari kerja</td>
      <td>:</td>
      <td>{{ $payslip->working_days_snapshot ?? 25 }} &nbsp;&nbsp;&nbsp;&nbsp; hari</td>
    </tr>
  </table>

  <!-- Bagian Rincian Gaji & Potongan (2 Kolom Statis Lengkap) -->
  <table class="columns-table border-dashed" style="border-top: none;">
    <tr>
      <!-- Kolom Kiri: GAJI -->
      <td class="border-r-dashed">
        <div class="section-title">GAJI</div>
        <table class="items-table">
          <tbody>
            <tr>
              <td colspan="4">Gaji Pokok</td>
              <td style="width: 4px;">:</td>
              <td style="width: 10px;">{{ $valGapok > 0 ? 'Rp' : '' }}</td>
              <td class="text-right" style="width: 36px;">{{ $formatSlipCurrency($valGapok) }}</td>
            </tr>
            <tr>
              <td style="width: 46px;">Uang Makan</td>
              <td style="width: 10px;">{{ $rateMakan > 0 ? 'Rp' : '' }}</td>
              <td class="text-center" style="width: 20px;">{{ $formatSlipCurrency($rateMakan) }}</td>
              <td></td>
              <td>:</td>
              <td>{{ $valMakan > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valMakan) }}</td>
            </tr>
            <tr>
              <td>Uang Transport</td>
              <td>{{ $rateTransport > 0 ? 'Rp' : '' }}</td>
              <td class="text-center">{{ $formatSlipCurrency($rateTransport) }}</td>
              <td></td>
              <td>:</td>
              <td>{{ $valTransport > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valTransport) }}</td>
            </tr>
            <tr>
              <td colspan="4">Uang Lembur &nbsp;&nbsp; {{ $periodeLembur }}</td>
              <td>:</td>
              <td>{{ $valLembur > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valLembur) }}</td>
            </tr>
            <tr>
              <td style="padding-left: 6px;">Upah/jam</td>
              <td>{{ $rateLembur > 0 ? 'Rp' : '' }}</td>
              <td class="text-center">{{ $formatSlipCurrency($rateLembur) }}</td>
              <td colspan="4"></td>
            </tr>
            <tr>
              <td style="padding-left: 6px;">Hari biasa</td>
              <td colspan="2">0 jam</td>
              <td class="text-center">-</td>
              <td colspan="3"></td>
            </tr>
            <tr>
              <td style="padding-left: 6px;">Hari Libur</td>
              <td colspan="2">0 jam</td>
              <td class="text-center">-</td>
              <td colspan="3"></td>
            </tr>
            <tr>
              <td colspan="4">Tunjangan Fungsional</td>
              <td>:</td>
              <td>{{ $valFunctional > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valFunctional) }}</td>
            </tr>
            <tr>
              <td colspan="4">Tunjangan Jabatan</td>
              <td>:</td>
              <td>{{ $valJabatan > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valJabatan) }}</td>
            </tr>
            <tr>
              <td colspan="4">Tunjangan Keluarga</td>
              <td>:</td>
              <td>{{ $valKeluarga > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valKeluarga) }}</td>
            </tr>
            <tr>
              <td colspan="4">Kehadiran</td>
              <td>:</td>
              <td>{{ $valKehadiran > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valKehadiran) }}</td>
            </tr>
            <tr>
              <td colspan="4">Tunjangan Pulsa</td>
              <td>:</td>
              <td>{{ $valPulsa > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valPulsa) }}</td>
            </tr>
            <tr>
              <td colspan="4">Biaya Kesehatan</td>
              <td>:</td>
              <td>{{ $valKesehatan > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valKesehatan) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Gaji Bruto -->
        <div class="border-t-dashed" style="padding-top: 1px; margin-top: 1.5px;">
          <table class="items-table text-bold">
            <tr>
              <td>GAJI BRUTO</td>
              <td style="width: 4px;">:</td>
              <td style="width: 10px;">Rp</td>
              <td class="text-right" style="width: 36px;">{{ $formatTotalCurrency($valGajiBruto) }}</td>
            </tr>
          </table>
        </div>
      </td>

      <!-- Kolom Kanan: POTONGAN GAJI & UANG DINAS -->
      <td>
        <div class="section-title">POTONGAN GAJI</div>
        <table class="items-table">
          <tbody>
            <tr>
              <td colspan="4">BPJS Kesehatan</td>
              <td style="width: 4px;">:</td>
              <td style="width: 10px;">{{ $valBpjsKes > 0 ? 'Rp' : '' }}</td>
              <td class="text-right" style="width: 36px;">{{ $formatSlipCurrency($valBpjsKes) }}</td>
            </tr>
            <tr>
              <td style="width: 52px;">Potongan Kehadiran</td>
              <td style="width: 22px;">S, I, C</td>
              <td class="text-center" style="width: 12px;">{{ $qtySic > 0 ? $qtySic : '-' }}</td>
              <td>hari</td>
              <td>:</td>
              <td>{{ $valSic > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valSic) }}</td>
            </tr>
            <tr>
              <td></td>
              <td>Alpa</td>
              <td class="text-center">{{ $qtyAlpa > 0 ? $qtyAlpa : '-' }}</td>
              <td>hari</td>
              <td>:</td>
              <td>{{ $valAlpa > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valAlpa) }}</td>
            </tr>
            <tr>
              <td colspan="4">Iuran BPJS Ketenagakerjaan</td>
              <td>:</td>
              <td>{{ $valBpjsTk > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valBpjsTk) }}</td>
            </tr>
            <tr>
              <td colspan="4">Iuran Koperasi</td>
              <td>:</td>
              <td>{{ $valIuranKop > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valIuranKop) }}</td>
            </tr>
            <tr>
              <td colspan="4">Pinjaman Koperasi</td>
              <td>:</td>
              <td>{{ $valPinjKop > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valPinjKop) }}</td>
            </tr>
            <tr>
              <td colspan="4">Pinjaman Lokal</td>
              <td>:</td>
              <td>{{ $valPinjaman > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valPinjaman) }}</td>
            </tr>
            <tr>
              <td colspan="4">Angsuran Kendaraan</td>
              <td>:</td>
              <td>{{ $valKendaraan > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valKendaraan) }}</td>
            </tr>
            <tr>
              <td colspan="2">Jumlah Off</td>
              <td class="text-center">{{ $qtyOff > 0 ? $qtyOff : '-' }}</td>
              <td>hari</td>
              <td>:</td>
              <td>{{ $valOff > 0 ? 'Rp' : '' }}</td>
              <td class="text-right">{{ $formatSlipCurrency($valOff) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Total Potongan -->
        <div class="border-t-dashed" style="padding-top: 1px; margin-top: 1.5px;">
          <table class="items-table text-bold">
            <tr>
              <td>TOTAL POTONGAN</td>
              <td style="width: 4px;">:</td>
              <td style="width: 10px;">Rp</td>
              <td class="text-right" style="width: 36px;">{{ $formatTotalCurrency($valTotalPotongan) }}</td>
            </tr>
          </table>
        </div>

        <!-- Uang Dinas -->
        <div style="margin-top: 1.5px;">
          <div class="section-title">UANG DINAS</div>
          <table class="items-table">
            <tr>
              <td>PJD Luar Kota</td>
              <td style="width: 4px;">:</td>
              <td style="width: 10px;">{{ $valPjd > 0 ? 'Rp' : '' }}</td>
              <td class="text-right" style="width: 36px;">{{ $formatSlipCurrency($valPjd) }}</td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>

  <!-- Total Terima Gaji -->
  <table class="total-box border-dashed" style="border-top: none;">
    <tr>
      <td style="text-align: center; font-weight: bold; font-size: 5.4pt;">TOTAL TERIMA GAJI</td>
      <td style="width: 12px; text-align: left; font-weight: bold; font-size: 5.4pt;">Rp</td>
      <td style="width: 44px; text-align: right; font-weight: bold; font-size: 5.8pt;">{{ $formatTotalCurrency($valTotalTerima) }}</td>
    </tr>
  </table>

  <!-- Bagian Tanda Tangan -->
  <table class="signature-table border-dashed" style="border-top: none;">
    <tr>
      <td style="width: 33.33%;">
        <div style="height: 8px;"></div>
        <div class="signature-name">{{ $payslip->signatory_personalia_name ?? 'Singgih Sugiarto' }}</div>
        <div class="signature-title">{{ $payslip->signatory_personalia_title ?? 'Personalia' }}</div>
      </td>
      <td style="width: 33.33%;">
        <div style="height: 8px; font-size: 3.5pt;">-</div>
        <div class="signature-name">{{ $payslip->signatory_reliever_name ?? 'Sheren Regiana' }}</div>
        <div class="signature-title">{{ $payslip->signatory_reliever_title ?? 'Yang Menyerahkan' }}</div>
      </td>
      <td style="width: 33.33%;">
        <div style="height: 8px;"></div>
        <div class="signature-name">{{ $payslip->signatory_receiver_name ?? $payslip->employee_name_snapshot }}</div>
        <div class="signature-title">Yang Menerima</div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>
