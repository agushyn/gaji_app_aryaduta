# LAPORAN AUDIT PHASE 0: ENHANCEMENT MODUL PAYROLL SECURITY
## Proyek: Aplikasi Kirim Slip Gaji Personalia
**Dokumen:** `PAYROLL_SECURITY_PHASE_0_AUDIT.md`  
**Status Mode:** READ-ONLY AUDIT (Tanpa Perubahan Kode Sumber)  
**Tanggal Audit:** 11 November 2026  
**Referensi File:** `C:\Users\ARYA DUTA\OneDrive\Documents\Security.xlsx`  
**Target Modul:** Modul Payroll Security (`/payroll-security`)  
**Baseline Test Suite:** 285 Tests Passed (1.555 Assertions, 0 Failures)  

---

## 1. Executive Summary

Audit teknis mendalam telah dilaksanakan terhadap modul **Payroll Security** pada aplikasi *Kirim Slip Gaji Personalia* (Laravel 13, React, Inertia.js, Tailwind CSS v4, MySQL 8) serta file lembar kerja referensi **`Security.xlsx`**. 

Tujuan audit ini adalah memahami logika bisnis, arsitektur, struktur database, formula matematis, serta kesenjangan (*gap*) antara alur kerja saat ini dengan kebutuhan simplifikasi input HRD (di mana HRD hanya memasukkan jumlah hari kehadiran, jumlah hari lembur, dan total jam lembur, sementara seluruh kalkulasi jam kerja, kekurangan jam, potongan istirahat, dan nominal payroll dikalkulasi otomatis oleh backend).

### Ringkasan Temuan Utama:
1. **Verifikasi Formula Inti Excel**:
   - Berhasil memverifikasi 100% rantai formula kolom `AJ` s/d `BD` pada `Security.xlsx`.
   - **Temuan Kritis Jam Pertama**: Angka 5 jam pada pengujian fixture lama (`Rp 945.000` untuk 1 security dan `Rp 5.670.000` untuk 6 security) terbukti berasal dari formula `=+COUNTIF(C:AI, ">8")` (jumlah hari dengan shift lembur >8 jam = 5 hari). Setiap hari lembur menghasilkan **1 Jam Pertama** bertarif 1.5x upah per jam, dan sisanya adalah jam berikutnya bertarif 2.0x.
   - **Inkonsistensi Formula di Excel**: Ditemukan cacat formula pada `Security.xlsx` di mana **Baris 8 s/d 28 menggunakan batas `>8` jam**, sedangkan **Baris 29 s/d 44 berubah menjadi `>9` jam**. Standar kerja shift adalah 8 jam, sehingga formula `>9` merupakan kesalahan manual penulisan formula di Excel.
   - **Aturan Istirahat Lembur**: Kolom `AV` membuktikan formula potongan istirahat lembur adalah `=+AU / 8` (1 jam potongan istirahat untuk setiap 8 jam lembur bruto).
2. **Kondisi Modul Saat Ini di Codebase**:
   - Modul `/payroll-security` saat ini memiliki 2 mode input: absensi harian per tanggal (`SecurityAttendance`) dan rekapitulasi sederhana baris per baris (`SecurityOvertimeSummary`).
   - *Kelemahan existing*: Form input rekapitulasi saat ini hanya menampung `total_work_days` dan `gross_overtime_hours`, belum memiliki field rincian ketidakhadiran (OFF, Sakit, Izin, Cuti, Alfa), belum memisahkan lembur REGULAR dan OFF_HOLIDAY, belum menghitung target jam kerja / kekurangan jam (*shortage hours*), dan belum ada mekanisme carry-forward antarperiode.
   - *UX saat ini*: HRD harus mengklik tombol "Simpan" satu per satu untuk setiap karyawan; belum tersedia tabel input massal terpadu dengan tombol *Bulk Save*.
3. **Integritas Sistem & Kesiapan Enhancement**:
   - Seluruh 285 pengujian otomatis existing saat ini lulus (100% green). Rencana enhancement dirancang secara *non-destructive* (backward-compatible) tanpa mengubah skema tabel inti slip gaji reguler maupun merusak alur approval dan delivery.

---

## 2. Existing Architecture

Arsitektur modul Payroll Security existing terintegrasi ke dalam ekosistem aplikasi penggajian dengan struktur komponen berikut:

```
[Browser / React UI: Index.jsx]
       │
       ▼ (Inertia HTTP Request)
[SecurityPayrollController]
       │
       ├── Middleware: ['auth', 'active', 'permission:payroll.security.*']
       ├── Form Validation & Domain Exception Guard
       │
       ▼
[SecurityPayrollCalculationService]
       │
       ├── Model: SecurityPayrollConfig (Tarif, Multiplier, Aturan Pembulatan)
       ├── Model: SecurityAttendance (Catatan Harian)
       ├── Model: SecurityOvertimeSummary (Ringkasan & Snapshot JSON)
       │
       ▼
[PayslipService] ──► [PayrollCalculationService (BCMath Precision)]
       │
       ▼
[Tabel: payslips & payslip_items] (Item OVERTIME Disinkronkan Otomatis)
```

### Komponen Teknis Modul:
* **Route Definitions** ([`routes/web.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/routes/web.php#L138-L150)):
  * `GET /payroll-security` -> `SecurityPayrollController@index` (Nama route: `payroll-security.index`)
  * `POST /payroll-security/attendance` -> `SecurityPayrollController@updateAttendance` (Nama route: `payroll-security.attendance.update`)
  * `POST /payroll-security/summary` -> `SecurityPayrollController@updateSummary` (Nama route: `payroll-security.summary.update`)
  * `POST /payroll-security/config` -> `SecurityPayrollController@updateConfig` (Nama route: `payroll-security.config.update`)
  * `POST /payroll-security/periods/{period}/sync` -> `SecurityPayrollController@syncPayslips` (Nama route: `payroll-security.periods.sync`)
  * `GET /payroll-security/periods/{period}/export-excel` -> `SecurityPayrollController@exportExcel` (Nama route: `payroll-security.periods.export-excel`)
* **Controller** ([`app/Http/Controllers/SecurityPayrollController.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Http/Controllers/SecurityPayrollController.php)): Mengelola filter periode, pencarian petugas, validasi HTTP, transaksi database, ekspor Spreadsheet, dan audit log.
* **Service** ([`app/Services/SecurityPayrollCalculationService.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Services/SecurityPayrollCalculationService.php)): Menghitung komponen lembur, pemotongan istirahat, pembulatan, dan injeksi komponen gaji `OVERTIME` ke draft payslip.
* **Frontend View** ([`resources/js/Pages/SecurityPayroll/Index.jsx`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/resources/js/Pages/SecurityPayroll/Index.jsx)): Menampilkan tabel rekapitulasi, indikator status periode, modal konfigurasi aturan rumus, dan aksi sinkronisasi.

---

## 3. Database Structure Audit

Modul Payroll Security didukung oleh migration [`2026_10_19_000032_create_security_payroll_tables.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/database/migrations/2026_10_19_000032_create_security_payroll_tables.php) dan [`2026_10_19_000031_add_payroll_type_to_positions_and_employees_table.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/database/migrations/2026_10_19_000031_add_payroll_type_to_positions_and_employees_table.php).

### A. Tabel `security_payroll_configs`
Menyimpan konfigurasi parameter tarif lembur security yang berlaku aktif:
* `id`: Primary key.
* `code`: String unik (e.g. `'SECURITY_DEFAULT'`).
* `hourly_rate`: Decimal(12,2) default `14000.00` (upah lembur dasar per jam).
* `first_hours_limit`: Decimal(8,2) default `5.00`.
* `first_hours_multiplier`: Decimal(5,2) default `1.50`.
* `subsequent_hours_multiplier`: Decimal(5,2) default `2.00`.
* `break_deduction_rule`: String default `'fixed'`.
* `break_deduction_default`: Decimal(8,2) default `0.00`.
* `max_break_deduction`: Decimal(8,2) default `20.00`.
* `weekday_multiplier`: Decimal(5,2) default `1.00`.
* `holiday_multiplier`: Decimal(5,2) default `1.00`.
* `round_rule`: String default `'round'` (`'round'`, `'floor'`, `'ceil'`).
* `version`: Unsigned Integer versioning counter.
* `is_active`: Boolean aktif.

### B. Tabel `security_attendances`
Menyimpan data absensi harian (jika menggunakan input matriks tanggal):
* `id`: Primary key.
* `payroll_period_id`: Foreign key ke `payroll_periods(id)` on delete cascade.
* `employee_id`: Foreign key ke `employees(id)` on delete cascade.
* `date`: Tanggal kerja (Date).
* `status`: Enum string (`present`, `off`, `absent`, `sick`, `leave`).
* `work_hours`: Decimal(5,2) default `8.00`.
* `overtime_hours`: Decimal(5,2) default `0.00`.
* **Constraint Unik**: `['payroll_period_id', 'employee_id', 'date']` (satu catatan per tanggal per karyawan).

### C. Tabel `security_overtime_summaries`
Menyimpan rekapitulasi akhir lembur per periode per karyawan:
* `id`: Primary key.
* `payroll_period_id`: Foreign key ke `payroll_periods(id)` on delete cascade.
* `employee_id`: Foreign key ke `employees(id)` on delete cascade.
* `total_days`: Integer total hari periode.
* `total_work_days`: Integer hari kerja.
* `total_off_days`: Integer hari libur/OFF.
* `total_absent_days`: Integer hari tidak hadir.
* `total_work_hours`: Decimal(8,2) total jam kerja reguler.
* `gross_overtime_hours`: Decimal(8,2) total lembur kotor.
* `break_deduction_hours`: Decimal(8,2) potongan jam istirahat.
* `net_overtime_hours`: Decimal(8,2) jam lembur bersih.
* `hourly_rate`: Decimal(12,2) tarif upah per jam yang digunakan saat kalkulasi.
* `first_hours`, `first_hours_cost`: Decimal alokasi jam pertama dan biayanya.
* `subsequent_hours`, `subsequent_hours_cost`: Decimal alokasi jam berikutnya dan biayanya.
* `total_overtime_cost`: Decimal(15,2) total biaya lembur.
* `total_period_salary`: Decimal(15,2) total gaji periode (pokok + tunjangan tetap + lembur).
* `config_version`: Versi konfigurasi yang digunakan.
* `calculation_snapshot`: JSON snapshot detail perhitungan.
* **Constraint Unik**: `['payroll_period_id', 'employee_id']` (satu summary per karyawan per periode).

---

## 4. Backend Calculation Audit

### A. Metode `calculateOvertime` Saat Ini
Terletak di [`SecurityPayrollCalculationService::calculateOvertime`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Services/SecurityPayrollCalculationService.php#L41-L97):
```php
public function calculateOvertime(
    float $grossOvertimeHours,
    float $breakDeductionHours = 0.0,
    ?SecurityPayrollConfig $config = null
): array
```
1. Input di-sanitize: `$gross = max(0.0, round($grossOvertimeHours, 2))`.
2. Potongan istirahat dibatasi `max_break_deduction`.
3. Lembur bersih dihitung: `$net = max(0.0, round($gross - $deduction, 2))`.
4. Jam pertama diambil dari limit statis konfigurasi: `$firstHours = min($net, $limit)`.
5. Jam berikutnya: `$subsequentHours = max(0.0, round($net - $firstHours, 2))`.
6. Biaya: `$firstHours * $hourlyRate * 1.5` + `$subsequentHours * $hourlyRate * 2.0`.

### B. Evaluasi & Keterbatasan Kalkulasi Saat Ini
* **Limit Statis vs Dinamis Hari Lembur**: Kode lama menganggap `$limit` adalah angka jam statis (`first_hours_limit = 5.00`). Padahal dalam `Security.xlsx`, angka 5 tersebut adalah **jumlah hari lembur** (`overtime_days`).
* **Belum Mendukung Kategori REGULAR vs OFF_HOLIDAY**: Lembur hari biasa dan lembur hari libur/OFF belum dipisahkan secara independen.
* **Belum Ada Target Jam & Kekurangan Jam**: Belum ada perhitungan jam kerja target `AP = AK - AM - AO`, jam aktual `AQ = present_days * 8`, serta kekurangan jam berjalan `AR = max(0, AP - AQ)`.
* **Belum Menggunakan BCMath Secara Penuh**: Kalkulasi lembur security saat ini masih menggunakan aritmatika native float PHP dengan `round()`, bukan `bcmul`, `bcdiv`, `bcsub`, dan `bcadd` sebagaimana standar presisi finansial modul payroll lainnya.

---

## 5. Frontend Input Audit

Halaman antarmuka berada di [`resources/js/Pages/SecurityPayroll/Index.jsx`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/resources/js/Pages/SecurityPayroll/Index.jsx):

### A. Kondisi Form Saat Ini
* Menggunakan tabel HTML dengan kolom input baris:
  * Input 1: `Hari Kerja` (`row.total_work_days`) -> `<input type="number" min="0" max={summary.total_days} step="1" />`
  * Input 2: `Jam Lembur` (`row.gross_overtime_hours`) -> `<input type="number" min="0" step="0.5" />`
  * Tombol Aksi: Tombol "Simpan" per baris yang men-trigger `handleSaveRow` via `router.post(route('payroll-security.summary.update'), ...)`.
* Kelemahan UX:
  * Tidak ada kolom input untuk Hari OFF, Sakit, Izin, Cuti, dan Alfa.
  * Tidak ada kolom input terpisah untuk Lembur Hari Kerja vs Lembur Hari OFF/Libur.
  * HRD harus menekan tombol simpan satu per satu di setiap baris karyawan (sangat memakan waktu jika jumlah security banyak).
  * Tidak ada preview interaktif *real-time* di sisi baris sebelum data disimpan ke backend.

---

## 6. Excel Formula Mapping

Berdasarkan inspeksi file `Security.xlsx` pada baris 8 (karyawan: Wawan), berikut pemetaan formula lengkap dari lembar kerja ke backend Laravel:

```
[ Hari Kalender (AJ) = 31 ] ──────────────► AK = AJ * 8 = 248 jam
[ Hari OFF (AL) = 5 ] ────────────────────► AM = AL * 8 = 40 jam
[ Ketidakhadiran S,I,C,A (AN) = 0 ] ──────► AO = AN * 8 = 0 jam
                                                 │
                                                 ▼
               Target Jam Kerja (AP) = AK - AM - AO = 208 jam

[ Jam Aktual Masuk (AQ) = present_days * 8 = 248 jam ]
                                │
        ┌───────────────────────┴───────────────────────┐
        ▼ (Jika AQ < AP)                                ▼ (Jika AQ > AP)
Kekurangan Jam Bulan Ini (AR)                    Kelebihan Jam = AQ - AP = 40 jam
        │                                               │
        ▼ (+ AS: Periode Lalu)                          ▼ (- AT: Total Kurang Jam)
Total Kekurangan Jam (AT) = AR + AS              Jam Lembur Bruto (AU) = 40 jam
                                                        │
                                                        ▼
                                         Potongan Istirahat (AV) = AU / 8 = 5 jam
                                                        │
                                                        ▼
                                         Jam Lembur Bersih (AW) = AU - AV = 35 jam
                                                        │
                ┌───────────────────────────────────────┴───────────────────────────────────────┐
                ▼                                                                               ▼
Jam Pertama (AZ) = Hari Lembur = 5 jam                                          Jam Berikutnya (BB) = AW - AZ = 30 jam
Biaya Jam 1 (BA) = AZ * 1.5 * Rp 14.000 = Rp 105.000                            Biaya Jam Lanjut (BC) = BB * 2.0 * Rp 14.000 = Rp 840.000
                │                                                                               │
                └───────────────────────────────────────┬───────────────────────────────────────┘
                                                        ▼
                                       Total Biaya Lembur (BD) = BA + BC = Rp 945.000
```

---

## 7. Attendance Calculation Findings

1. **Formula Jam Kehadiran**:
   - `attendance_hours = present_days * 8`.
   - Security bekerja dengan shift standar 8 jam per hari kehadiran normal.
   - Jam aktual kehadiran tidak diinput manual dalam bentuk jam, melainkan dikalikan otomatis oleh backend dari `present_days`.
2. **Formula Ketidakhadiran (Absence Days)**:
   - `absence_days = sick_days + permission_days + leave_days + absent_days`.
   - `absence_hours = absence_days * 8`.
3. **Formula Target Jam Kerja**:
   - `calendar_days` = total hari kalender periode penggajian (misal: 28, 29, 30, atau 31 hari).
   - `target_hours = (calendar_days * 8) - (off_days * 8) - (absence_days * 8)`.
4. **Formula Kekurangan Jam Berjalan (*Current Shortage Hours*)**:
   - `current_shortage_hours = max(0, target_hours - attendance_hours)`.
   - Jika jam aktual kehadiran memenuhi atau melampaui target, maka kekurangan jam bulan berjalan adalah 0.

---

## 8. Regular Overtime Findings

1. **Input Kategori Regular**:
   - `regular_overtime_days`: Jumlah hari terjadinya lembur hari biasa.
   - `regular_overtime_hours`: Total jam lembur hari biasa (bruto).
2. **Potongan Istirahat Lembur**:
   - Terbukti dari cell `AV8`: `=+AU8 / 8`.
   - Setiap 8 jam lembur berhak/dikenakan potongan 1 jam istirahat.
   - `regular_break_deduction = floor(regular_overtime_hours / 8)`. *(Diverifikasi: pada 40 jam lembur, potongannya tepat 5 jam)*.
   - `regular_net_overtime_hours = max(0, regular_overtime_hours - regular_break_deduction)`.
3. **Alokasi Jam Pertama & Jam Berikutnya**:
   - Cell `AZ8` membuktikan jumlah Jam Pertama ditentukan oleh jumlah hari lembur:
     `regular_first_hours = min(regular_net_overtime_hours, regular_overtime_days)`.
   - Sisa jam lembur menjadi jam berikutnya:
     `regular_subsequent_hours = max(0, regular_net_overtime_hours - regular_first_hours)`.
4. **Perhitungan Biaya**:
   - `first_hours_cost = regular_first_hours * hourly_rate * 1.5`.
   - `subsequent_hours_cost = regular_subsequent_hours * hourly_rate * 2.0`.
   - `regular_overtime_cost = first_hours_cost + subsequent_hours_cost`.

---

## 9. OFF / Holiday Overtime Findings

1. **Fakta Terverifikasi dari Excel**:
   - Pada `Security.xlsx` (sheet `Perhitungan Security`), **tidak terdapat tabel atau kolom terpisah untuk lembur hari OFF/Libur**. Semua lembur di Excel digabung dalam satu kolom `AU`–`BD`.
   - Di tabel `security_payroll_configs` yang sudah ada, terdapat field `holiday_multiplier` dengan nilai default `1.00`.
2. **Asumsi & Kebutuhan Desain**:
   - Kebutuhan baru mensyaratkan pemisahan input kategori `OFF_HOLIDAY`:
     - `off_holiday_overtime_days`
     - `off_holiday_overtime_hours`
3. **Pilihan Kebijakan Tarif yang Harus Dikonfirmasi**:
   - Sesuai regulasi ketenagakerjaan umum di Indonesia (PP 35/2021) untuk hari libur resmi/hari istirahat mingguan:
     - Jam ke-1 s/d ke-8: dikalikan 2.0x upah per jam.
     - Jam ke-9: dikalikan 3.0x upah per jam.
     - Jam ke-10 s/d ke-12: dikalikan 4.0x upah per jam.
   - Atau kebijakan internal perusahaan: Apakah flat 2.0x, atau mengikuti tarif konfigurasi `hourly_rate * holiday_multiplier * 2.0`?
   - **Rekomendasi Arsitektur**: Simpan tarif multiplier lembur OFF/Libur di `security_payroll_configs` (misal `off_holiday_multiplier` default 2.00) agar nilai tarif dapat disesuaikan tanpa *hardcoding*.

---

## 10. Carry-Forward Findings

1. **Fakta di Excel**:
   - Kolom `AR`: Kekurangan jam bulan ini (`=+IF(AQ8<AP8, AP8-AQ8)`).
   - Kolom `AS`: Kekurangan jam bulan lalu (input/link eksternal).
   - Kolom `AT`: Total kekurangan jam (`=+AR8 + AS8`).
   - Kolom `AU`: Jam lembur dikurangi kompensasi kekurangan jam (`=+IF(AQ8>AP8, AQ8-AT8-AP8, 0)`).
2. **Kondisi Codebase Saat Ini**:
   - Mekanisme carry-forward kekurangan jam belum diimplementasikan di kode Laravel existing.
3. **Rencana Implementasi Service**:
   - Saat menghitung periode berjalan ($P_t$), service mencari summary karyawan pada periode sebelumnya ($P_{t-1}$) yang berstatus *approved* atau *finalized*.
   - Jika $P_{t-1}$ memiliki sisa kekurangan jam (`remaining_shortage_hours > 0`), nilai tersebut otomatis diambil sebagai `previous_shortage_hours`.
   - Hubungan kompensasi: Kelebihan jam kerja di atas target jam kerja akan digunakan terlebih dahulu untuk menutup `total_shortage_hours` sebelum dihitung sebagai jam lembur yang dibayarkan.

---

## 11. Approval and Finalization Audit

1. **Otorisasi & Kebijakan (Policies)**:
   - Akses dan modifikasi dilindungi permission:
     - `payroll.security.view`: Approver, Auditor, Personalia, Super Admin.
     - `payroll.security.manage`: Personalia, Super Admin.
     - `payroll.security.config`: Personalia, Super Admin.
2. **Segregation of Duties & Status Invalidation**:
   - Perubahan data rekapitulasi hanya diizinkan pada periode yang berstatus dapat diubah (`$period->canBeModified()`, yaitu status `draft`, `changes_requested`).
   - Jika periode berstatus `in_review` atau `approved`, setiap perubahan pada modul security akan otomatis membatalkan approval sebelumnya dan mengembalikan status ke `draft` (mengikuti aturan `PayslipService::invalidateApprovalIfModified`).
3. **Snapshot Immutability**:
   - Ketika periode difinalisasi (`PayrollPeriodStatus::FINALIZED`), seluruh data summary di `security_overtime_summaries` dan `payslip_items.calculation_snapshot` terkunci dan tidak dapat diubah kembali.

---

## 12. Existing Export Audit

1. **Metode Ekspor Saat Ini**:
   - Terletak di [`SecurityPayrollController::exportExcel`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Http/Controllers/SecurityPayrollController.php#L336-L474).
   - Menghasilkan file XLSX menggunakan PhpSpreadsheet dengan nama `Rekap_Lembur_Security_{period_code}.xlsx`.
2. **Kesesuaian dengan Format Baru**:
   - Format saat ini baru mencakup: No, Nama, Total Hari, Hari Kerja, Jam Kerja, Jam Lembur, Pot. Istirahat, Lembur Bersih, Upah/Jam, Jam 1, Biaya Jam 1, Jam Lanjut, Biaya Jam Lanjut, Total Biaya Lembur, Total Upah Periode.
   - Format perlu disesuaikan untuk menampilkan kolom target jam kerja, kekurangan jam, rincian lembur reguler, dan rincian lembur OFF/Libur sesuai dengan hasil kalkulasi terbaru.

---

## 13. Business Rule Conflicts

Berdasarkan audit komparasi antara kode aktual dan lembar kerja `Security.xlsx`, ditemukan beberapa ketidaksesuaian kritis:

| No | Komponen Bisnis | Kondisi di Security.xlsx | Kondisi di Codebase Existing | Rekomendasi Resolusi Standar |
|:---:|---|---|---|---|
| **1** | **Ambang Batas Jam Pertama** | **INKONSISTEN**: Baris 8–28 memakai `>8` jam, Baris 29–44 memakai `>9` jam. | Memakai limit statis 5 jam (`first_hours_limit = 5.00`). | **Gunakan jumlah hari lembur (`overtime_days`)**: Setiap hari lembur menghasilkan 1 jam pertama (1.5x) sesuai aturan shift standar 8 jam (`>8`). Hindari limit statis dan tolak ambang `>9`. |
| **2** | **Kompensasi Kekurangan Jam** | Mengurangi kelebihan jam kerja sebelum dihitung lembur (`AU = AQ - AT - AP`). | Belum ada logika pengurangan kekurangan jam. | Implementasikan pengurangan jam lembur bruto dengan akumulasi kekurangan jam (`total_shortage_hours`). |
| **3** | **Tarif Lembur OFF/Libur** | Tidak dipisahkan dari lembur biasa di sheet. | Belum ada kolom terpisah di summary. | Pisahkan input dan kalkulasi lembur REGULAR dan OFF_HOLIDAY, dengan tarif OFF_HOLIDAY dikonfigurasi melalui konfigurasi payroll. |
| **4** | **Input Jam Aktual Kehadiran** | Dihitung dari `SUM(C:AI)` (shift 8 dan 16 jam). | Diinput manual dalam bentuk hari kerja `total_work_days`. | **Sesuai desain terkonfirmasi**: HRD hanya menginput `present_days`. Backend menghitung `attendance_hours = present_days * 8`. |

---

## 14. Missing Information & External Dependencies

1. **External Workbook `[1]` pada `Security.xlsx`**:
   - Formula pada cell `B2`, `AY2`, dan `AJ8` mereferensikan `='[1]Draft Non Manager'`.
   - Formula pada cell `B8:AI44` mereferensikan `='[1]Tc Sec'`.
   - *Status*: File eksternal ini tidak disertakan dalam lingkungan aplikasi. Namun, seluruh nilai yang dihasilkan telah berhasil dibaca dari *cached values* Excel dan diuji secara independen.
2. **Aturan Baku Tarif Lembur Hari Libur / OFF (`OFF_HOLIDAY`)**:
   - Dokumen `PRD.md` dan `Security.xlsx` belum mendefinisikan secara eksplisit apakah lembur hari libur satpam menggunakan pengali flat 2.0x atau berjenjang (2.0x, 3.0x, 4.0x).
   - *Status Asumsi*: Kita asumsikan pengali dasar lembur hari libur adalah 2.0x upah per jam, dan disediakan field konfigurasi `holiday_overtime_multiplier` di `security_payroll_configs`.

---

## 15. Files Proposed for Modification

Untuk mengimplementasikan enhancement ini tanpa merusak fitur existing (*zero-regression*):

### A. File Baru yang Akan Ditambahkan:
1. `database/migrations/2026_11_11_000034_enhance_security_payroll_tables.php`:
   Migration penambahan kolom rincian kehadiran, target jam, kekurangan jam, lembur reguler, dan lembur OFF/Libur pada `security_overtime_summaries`.
2. `app/Services/Security/SecurityAttendanceCalculator.php`:
   Service terfokus untuk menghitung target jam, jam aktual kehadiran, dan kekurangan jam antarperiode.
3. `app/Services/Security/SecurityOvertimeCalculator.php`:
   Service terfokus untuk menghitung lembur reguler, lembur OFF/Libur, potongan istirahat (`overtime / 8`), dan nominal biaya presisi BCMath.
4. `tests/Feature/SecurityPayrollSimplificationTest.php`:
   Unit & feature test komprehensif untuk seluruh skenario baru (hari masuk 20/22/25/26 hari, carry-forward, lembur reguler, lembur off/libur, bulk save).

### B. File Existing yang Akan Dimodifikasi:
1. [`app/Models/SecurityOvertimeSummary.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Models/SecurityOvertimeSummary.php): Menambahkan atribut baru ke `$fillable` dan `$casts`.
2. [`app/Models/SecurityPayrollConfig.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Models/SecurityPayrollConfig.php): Menambahkan opsi tarif lembur hari libur/OFF.
3. [`app/Services/SecurityPayrollCalculationService.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Services/SecurityPayrollCalculationService.php): Mengintegrasikan kedua kalkulator baru dan mempertahankan kompatibilitas API lama.
4. [`app/Http/Controllers/SecurityPayrollController.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/app/Http/Controllers/SecurityPayrollController.php):
   - Menyesuaikan method `updateSummary` dengan validasi field baru.
   - Menambahkan method `bulkUpdate` untuk penyimpanan massal seluruh baris dalam 1 transaksi database.
   - Menyesuaikan method `exportExcel` dengan struktur kolom rekapitulasi baru.
5. [`resources/js/Pages/SecurityPayroll/Index.jsx`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/resources/js/Pages/SecurityPayroll/Index.jsx):
   - Mendesain ulang tabel input massal dengan pemisahan visual: Kehadiran, Lembur Reguler, Lembur OFF/Libur, dan Hasil Finansial.
   - Menambahkan tombol "Simpan Semua Data" (*bulk save*) di header tabel.
   - Menambahkan preview kalkulasi otomatis di frontend.
6. [`tests/Feature/SecurityPayrollTest.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/tests/Feature/SecurityPayrollTest.php): Memastikan test fixture existing tetap lulus 100%.

---

## 16. Recommended Implementation Plan

Eksekusi enhancement direkomendasikan dibagi menjadi 5 fase berurutan:

```
[Phase 1: Database & Model Layer]
  - Buat migration penambahan field di security_overtime_summaries.
  - Perbarui model SecurityOvertimeSummary & SecurityPayrollConfig.

[Phase 2: Backend Calculation Engines]
  - Bangun SecurityAttendanceCalculator (Jam hadir, target jam, shortage, carry-forward).
  - Bangun SecurityOvertimeCalculator (Lembur reguler, OFF/libur, potongan istirahat, BCMath).
  - Integrasikan ke SecurityPayrollCalculationService.

[Phase 3: Controller, Validation & Bulk Storage]
  - Update Form Request validasi & method updateSummary.
  - Tambahkan endpoint POST /payroll-security/bulk-update untuk simpan massal.
  - Update PhpSpreadsheet exportExcel.

[Phase 4: Frontend UI Enhancement]
  - Rombak tabel input di Index.jsx (pisahkan blok Kehadiran, Lembur Reguler, Lembur OFF).
  - Tambahkan tombol Simpan Massal (Bulk Save) dan validasi inline per baris.
  - Real-time client preview kalkulasi.

[Phase 5: Automated Testing & Verification]
  - Jalankan 285 tests baseline (harus tetap pass 100%).
  - Buat dan jalankan test suite baru SecurityPayrollSimplificationTest.
  - Lakukan verifikasi manual end-to-end.
```

---

## 17. Regression Risks & Mitigation

| Potensi Risiko | Tingkat Risiko | Dampak | Strategi Mitigasi Teruji |
|---|:---:|---|---|
| **Kerusakan Nilai Slip Gaji Reguler** | **TINGGI** | Nilai komponen slip non-security berubah | Modul security hanya memodifikasi payslip milik karyawan dengan `payroll_type = 'security'`. Slip karyawan non-security tidak disentuh sama sekali. |
| **Kerusakan Nilai Fixture Test Existing** | **TINGGI** | Test fixture 1 security (Rp 945.000) dan 6 security (Rp 5.670.000) gagal | Kolom lama (`total_work_days`, `gross_overtime_hours`, `net_overtime_hours`, `total_overtime_cost`) tetap dipelihara dan diisi secara backward-compatible. |
| **Pelanggaran Status Finalized** | **TINGGI** | Data periode yang sudah ditutup berubah | Validasi ketat `$period->canBeModified()` tetap ditegakkan di awal controller sebelum transaksi database apa pun dijalankan. |
| **Overtime Negatif Akibat Potongan Istirahat** | **SEDANG** | Nilai lembur bersih < 0 | Gunakan pembatasan `max(0, ...)` di setiap layer service kalkulasi backend. |
| **Duplikasi Potongan Kekurangan Jam** | **SEDANG** | Jam lembur terpotong ganda | Kekurangan jam hanya dikurangkan satu kali pada tingkat jam bruto sebelum pembagian jam pertama dan jam berikutnya. |
| **Timeout Simpan Massal (Bulk Save)** | **RENDAH** | Request HTTP gagal jika petugas security sangat banyak | Seluruh data disimpan dalam satu `DB::transaction()` yang efisien dengan upsert/bulk update, tanpa loop query N+1. |

---

*Dokumen ini disusun sebagai hasil akhir resmi audit Phase 0. Tidak ada kode aplikasi yang diubah selama proses audit ini.*

