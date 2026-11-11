# LAPORAN IMPLEMENTASI PHASE 2: SECURITY PAYROLL CALCULATION ENGINE

## 1. Executive Summary

| Item | Deskripsi |
| :--- | :--- |
| **Modul** | Payroll Security (`/payroll-security`) |
| **Fase** | **PHASE 2 - Security Payroll Calculation Engine** |
| **Status** | **SELESAI (COMPLETED)** |
| **Tanggal Eksekusi** | 11 November 2026 |
| **Baseline Test Sebelum Enhancement** | 285 tests passed, 1555 assertions, 0 failures |
| **Test Suite Setelah Phase 1** | 293 tests passed, 1608 assertions, 0 failures |
| **Test Suite Terkini Setelah Phase 2** | **305 tests passed, 1690 assertions, 0 failures** (+12 test baru) |
| **Tingkat Kelulusan (Pass Rate)** | **100% (Zero Regression, 0 Failures)** |

Phase 2 berhasil membangun dan mengintegrasikan calculation engine modular untuk penggajian petugas Security berdasarkan audit mendalam lembar kerja referensi `Security.xlsx` dan arsitektur aplikasi *Kirim Slip Gaji Personalia*.

Seluruh perhitungan jam kerja, target jam operasional, kekurangan jam kerja (*shortage hours*), carry-forward kekurangan jam antarperiode, pemisahan lembur REGULAR dan OFF_HOLIDAY, potongan istirahat lembur, serta alokasi jam pertama dinamis telah terimplementasi di backend Laravel dengan presisi desimal BCMath.

---

## 2. Pemetaan Formula Excel ke Laravel

Berdasarkan inspeksi sel `AJ` sampai `BD` pada `Security.xlsx` (Sheet `Perhitungan Security`, baris 8 karyawan Wawan):

| Sel Excel | Komponen Bisnis | Formula Asli di Excel | Implementasi di Laravel Engine |
|:---:|---|---|---|
| **`AJ`** | Hari Kalender Periode | `+'[1]Draft Non Manager'!$AN$593` | `count($this->getPeriodDates($period))` (misal 31 hari) |
| **`AK`** | Total Jam Kalender | `+AJ8 * 8` | `$attendanceCalculator->calculateCalendarHours($calendarDays)` |
| **`AL`** | Hari OFF Rutin | Input manual / jadwal | `$offDays` (input HRD) |
| **`AM`** | Jam OFF Rutin | `+AL8 * 8` | `$offDays * 8.0` |
| **`AN`** | Total Ketidakhadiran | `COUNTIF(S)+COUNTIF(I)+COUNTIF(C)+COUNTIF(A)` | `$totalAbsenceDays = $sick + $permission + $leave + $absent` |
| **`AO`** | Jam Ketidakhadiran | `+AN8 * 8` | `$totalAbsenceDays * 8.0` |
| **`AP`** | **Target Jam Kerja** | `+AK8 - AM8 - AO8` | `$targetHours = ($calendarDays - $offDays - $totalAbsenceDays) * 8.0` |
| **`AQ`** | **Jam Aktual Kehadiran** | `+SUM(C8:AI8)` | `$attendanceHours = $presentDays * 8.0` (Backend source of truth) |
| **`AR`** | **Kekurangan Jam Bulan Ini** | `+IF(AQ8 < AP8, AP8 - AQ8, 0)` | `$currentShortage = max(0.0, round($targetHours - $attendanceHours, 2))` |
| **`AS`** | **Carry-Forward Bulan Lalu** | Link eksternal / bulan lalu | `$previousShortage = $attendanceCalculator->resolvePreviousShortage(...)` |
| **`AT`** | **Total Kekurangan Jam** | `+AR8 + AS8` | `$totalShortage = $currentShortage + $previousShortage` |
| **`AU`** | **Jam Lembur Bruto Kompensasi**| `+IF(AQ8 > AP8, AQ8 - AT8 - AP8, 0)` | `$adjustedOvertime = max(0.0, round($grossOvertime - $shortageOffset, 2))` di mana `$shortageOffset = min($totalShortage, $grossOvertime)` |
| **`AV`** | **Potongan Jam Istirahat** | `+(AU8 / 8)` | `round($adjustedOvertime / 8.0, 2)` (1 jam potong per 8 jam shift) |
| **`AW`** | **Jam Lembur Bersih** | `+AU8 - AV8` | `$netOvertime = max(0.0, round($adjustedOvertime - $breakDeduction, 2))` |
| **`AY`** | Tarif Upah per Jam | `14000` | `$config->hourly_rate` (default Rp 14.000) |
| **`AZ`** | **Alokasi Jam Pertama** | `+COUNTIF(C8:AI8, ">8")` | `$firstHours = min($netOvertime, (float) $overtimeDays)` |
| **`BA`** | Biaya Jam Pertama (1.5x) | `+AZ8 * 1.5 * AY8` | `bcmul(bcmul($firstHours, $hourlyRate, 4), '1.50', 4)` |
| **`BB`** | Alokasi Jam Berikutnya | `+AW8 - AZ8` | `$subsequentHours = max(0.0, round($netOvertime - $firstHours, 2))` |
| **`BC`** | Biaya Jam Lanjut (2.0x) | `+BB8 * 2.0 * AY8` | `bcmul(bcmul($subsequentHours, $hourlyRate, 4), '2.00', 4)` |
| **`BD`** | **Total Biaya Lembur** | `+BA8 + BC8` | `bcadd($firstHoursCost, $subsequentHoursCost, 2)` |

---

## 3. Daftar Formula yang Berhasil Diverifikasi

1. **Kalkulasi Jam Hadir & Anti-Tampering**:
   - `attendance_hours = present_days * 8.0`.
   - Mengabaikan nilai jam yang dikirim oleh client; backend menjadi satu-satunya *source of truth*.
2. **Formula Target Jam Kerja**:
   - $\text{target\_hours} = (\text{calendar\_days} - \text{off\_days} - \text{total\_absence\_days}) \times 8.0$.
   - Sesuai dengan sel `AP` di Excel. Jika seorang security memiliki hari cuti/sakit/izin, target jam kerja operasionalnya berkurang secara proporsional.
3. **Kekurangan Jam Berjalan (*Current Shortage*)**:
   - $\text{current\_shortage\_hours} = \max(0.0, \text{target\_hours} - \text{attendance\_hours})$.
   - Jika jam aktual memenuhi atau melampaui target, kekurangan jam adalah 0.0.
4. **Kompensasi Jam Lembur terhadap Kekurangan Jam (*Shortage Offset*)**:
   - $\text{shortage\_offset\_hours} = \min(\text{total\_shortage\_hours}, \text{gross\_overtime\_hours})$.
   - $\text{adjusted\_overtime\_hours} = \max(0.0, \text{gross\_overtime\_hours} - \text{shortage\_offset\_hours})$.
   - $\text{remaining\_shortage\_hours} = \max(0.0, \text{total\_shortage\_hours} - \text{shortage\_offset\_hours})$.
   - Mencegah *double deduction*: Kekurangan jam yang sudah dikompensasikan tidak akan terbawa ke bulan berikutnya.
5. **Alokasi Jam Pertama Dinamis Berdasarkan Hari Lembur**:
   - Di Excel cell `AZ8`: `=+COUNTIF(C:AI, ">8")`.
   - Setiap 1 hari lembur menghasilkan 1 jam pertama (bertarif 1.5x upah per jam). Sisa jam lembur dialokasikan sebagai jam berikutnya (bertarif 2.0x).
   - Menghapus keterbatasan limit statis 5 jam pada sistem lama.
6. **Potongan Istirahat Lembur**:
   - Formula Excel cell `AV`: `+(AU / 8)`. 1 jam istirahat untuk setiap 8 jam lembur bruto.
   - Tetap mendukung konfigurasi perusahaan (`per_8_hours`, `fixed`, `none`).

---

## 4. Daftar Formula / Tarif yang Membutuhkan Keputusan Perusahaan

### Lembur Hari OFF / Libur (`OFF_HOLIDAY`)
1. **Fakta di Lapangan & Excel**:
   - Di `Security.xlsx`, tidak terdapat kolom terpisah untuk lembur hari OFF/Libur (seluruh shift digabung dalam tabel harian).
   - Namun, kebutuhan produk mensyaratkan pencatatan dan kalkulasi independen untuk `off_holiday_overtime_days` dan `off_holiday_overtime_hours`.
2. **Perlindungan Terhadap Pembayaran Tidak Sah (Anti-Arbitrary Payout)**:
   - Sesuai instruksi: *"Jangan mengarang tarif atau menghasilkan nilai nominal yang menyesatkan. Jangan menampilkan nominal final jika tarif yang dibutuhkan belum tersedia."*
   - Jika `off_holiday_rate_confirmed` bernilai `false`, maka `off_holiday_overtime_cost = 0.00`.
   - Nominal lembur OFF **tidak dimasukkan** ke slip gaji untuk mencegah pencairan dana perusahaan tanpa otorisasi tertulis.
   - Sistem menyediakan estimasi acuan regulasi PP 35/2021 ($2.0\times$ upah per jam) dan menampilkan badge peringatan kuning di UI/snapshot:
     > *"Menunggu konfirmasi tarif lembur OFF/Libur dari manajemen (Estimasi PP 35/2021: 2.0x upah/jam = Rp ...)"*
3. **Pilihan Kebijakan yang Perlu Ditetapkan Manajemen**:
   - Apakah lembur hari OFF/Libur menggunakan pengali flat $2.0\times$ upah per jam?
   - Atau mengikuti skema berjenjang (jam 1–8: 2.0x, jam 9: 3.0x, jam 10–12: 4.0x)?

---

## 5. Arsitektur Teknis Baru (Separation of Concerns)

Komponen service dirancang modular dengan pemisahan tanggung jawab yang jelas:

```
[Browser UI: Index.jsx]
       │
       ├── Live Preview & Detail Modal
       ▼ (Inertia & Axios API)
[SecurityPayrollController]
       │
       ├── updateSummary() & bulkUpdate()
       ├── preview() ──► [previewForEmployee()] (In-Memory, No DB Mutation)
       │
       ▼
[SecurityPayrollCalculationService]
       │
       ├──► [SecurityAttendanceCalculator]
       │      ├── calculateCalendarHours()
       │      ├── calculateTargetHours() (AP = AK - AM - AO)
       │      ├── calculateAttendanceHours() (AQ = present_days * 8)
       │      ├── calculateCurrentShortage() (AR = AP - AQ)
       │      ├── resolvePreviousShortage() (Carry-forward aman)
       │      └── applyShortageOffset() (Kompensasi AU = AQ - AT - AP)
       │
       ├──► [SecurityOvertimeCalculator]
       │      ├── calculateRegularOvertime() (AV, AW, AZ, BA, BB, BC, BD)
       │      ├── calculateOffHolidayOvertime() (Proteksi tarif belum konfirmasi)
       │      └── applyRounding() (BCMath precision)
       │
       ▼
[Database: security_overtime_summaries & calculation_snapshot JSON]
       │
       ▼
[PayslipService::syncSecurityPayslip] ──► [Draft Payslip OVERTIME Component]
```

---

## 6. Database Migration (Phase 2)

Dibuat migration penambahan kolom kalkulasi engine:
- **Migration**: `database/migrations/2026_11_11_000035_add_calculation_engine_fields_to_security_overtime_summaries_table.php`
- **Alasan**: Menyimpan hasil kalkulasi target jam kerja, rincian kekurangan jam, carry-forward, kompensasi lembur, dan pemisahan biaya lembur REGULAR vs OFF_HOLIDAY secara eksplisit agar dapat diaudit langsung di database, diekspor ke Excel, dan diinspeksi tanpa harus mem-parse JSON snapshot.

### Kolom-Kolom Baru:
1. `target_hours` (`decimal(8,2)`): Target jam kerja operasional periode.
2. `current_shortage_hours` (`decimal(8,2)`): Kekurangan jam periode berjalan.
3. `previous_shortage_hours` (`decimal(8,2)`): Kekurangan jam carry-forward periode sebelumnya.
4. `total_shortage_hours` (`decimal(8,2)`): Total akumulasi kekurangan jam.
5. `shortage_offset_hours` (`decimal(8,2)`): Jam kekurangan yang dikompensasikan ke lembur.
6. `remaining_shortage_hours` (`decimal(8,2)`): Sisa jam kekurangan untuk periode berikutnya.
7. `previous_payroll_period_id` (`foreignId` nullable): Periode asal carry-forward untuk audit trail.
8. `regular_break_deduction_hours` (`decimal(8,2)`): Potongan istirahat lembur regular.
9. `regular_net_overtime_hours` (`decimal(8,2)`): Jam lembur regular bersih.
10. `regular_first_hours`, `regular_first_hours_cost`: Alokasi jam pertama dan biayanya.
11. `regular_subsequent_hours`, `regular_subsequent_hours_cost`: Alokasi jam berikutnya dan biayanya.
12. `regular_overtime_cost`: Subtotal biaya lembur regular.
13. `off_holiday_break_deduction_hours`: Potongan istirahat lembur OFF/Libur.
14. `off_holiday_net_overtime_hours`: Jam lembur OFF/Libur bersih.
15. `off_holiday_multiplier`: Multiplier lembur OFF/Libur.
16. `off_holiday_overtime_cost`: Biaya lembur OFF/Libur.
17. `off_holiday_rate_confirmed`: Boolean flag status verifikasi kebijakan tarif.

---

## 7. Mekanisme Carry-Forward Kekurangan Jam Kerja

1. **Pencegahan Referensi Sirkular**:
   - Service hanya mencari periode sebelumnya yang memiliki `end_date <= current_period->start_date` dan berstatus `finalized` atau `approved`.
   - Tidak pernah membaca periode berjalan atau periode di masa depan.
2. **Pencegahan Double Deduction**:
   - Jika kekurangan jam sebesar 8 jam telah dikompensasikan pada lembur periode ini (`shortage_offset_hours = 8`), maka `remaining_shortage_hours = 0`.
   - Periode berikutnya akan membaca `remaining_shortage_hours = 0` sehingga kekurangan jam tidak terpotong dua kali.
3. **Periode Pertama / Rollout Awal**:
   - Jika belum ada periode sebelumnya di database, HRD dapat menginput `previous_shortage_hours` secara manual saat inisialisasi awal.

---

## 8. Contoh Perhitungan 3 Karyawan dengan Kondisi Berbeda

Berikut hasil pengujian kalkulasi aktual pada 3 kondisi security:

### Karyawan 1: Wawan (Shift Normal & Lembur Regular Sesuai Excel Fixture)
- **Kondisi**: Bekerja 26 hari shift standar (8 jam/hari), 5 hari OFF, 0 hari absen pada periode 31 hari.
  - Hari Masuk: 26 hari $\rightarrow$ Jam Aktual: $26 \times 8 = 208.0$ jam.
  - Target Jam Kerja: $(31 - 5 - 0) \times 8 = 208.0$ jam.
  - Kekurangan Jam: $\max(0, 208 - 208) = 0.0$ jam.
- **Lembur Regular**: 5 hari lembur, total 40.0 jam lembur bruto.
  - Kompensasi Jam Kurang: 0.0 jam $\rightarrow$ Jam lembur disesuaikan: 40.0 jam.
  - Potongan Istirahat: $40 / 8 = 5.0$ jam.
  - Jam Lembur Bersih: $40.0 - 5.0 = 35.0$ jam.
  - Jam Pertama: $\min(35.0, 5\text{ hari}) = 5.0$ jam $\times 14.000 \times 1.5 = \text{Rp } 105.000$.
  - Jam Berikutnya: $35.0 - 5.0 = 30.0$ jam $\times 14.000 \times 2.0 = \text{Rp } 840.000$.
  - **Total Biaya Lembur**: $\text{Rp } 105.000 + \text{Rp } 840.000 = \mathbf{\text{Rp } 945.000}$.
  - Gaji Pokok: Rp 3.500.000 $\rightarrow$ **Total Gaji Periode**: $\mathbf{\text{Rp } 4.445.000}$.

### Karyawan 2: Budi (Membawa Carry-Forward Kekurangan Jam 8 Jam)
- **Kondisi**: Periode sebelumnya memiliki sisa kekurangan 8.0 jam.
  - Hari Masuk: 26 hari, 5 hari OFF, 0 absen $\rightarrow$ Target 208 jam, Jam Aktual 208 jam.
  - Kekurangan Bulan Ini: 0.0 jam.
  - Carry-Forward Bulan Lalu: 8.0 jam $\rightarrow$ **Total Kekurangan**: 8.0 jam.
- **Lembur Regular**: 2 hari lembur, total 16.0 jam lembur bruto.
  - **Kompensasi Kekurangan Jam**: Kekurangan 8.0 jam mengurangi lembur bruto:
    $\text{adjusted\_overtime} = 16.0 - 8.0 = 8.0\text{ jam}$.
  - Sisa kekurangan jam untuk periode depan: $8.0 - 8.0 = \mathbf{0.0\text{ jam (Lunas)}}$.
  - Potongan Istirahat: $8.0 / 8 = 1.0$ jam.
  - Jam Lembur Bersih: $8.0 - 1.0 = 7.0$ jam.
  - Jam Pertama: $\min(7.0, 2\text{ hari}) = 2.0$ jam $\times 14.000 \times 1.5 = \text{Rp } 42.000$.
  - Jam Berikutnya: $7.0 - 2.0 = 5.0$ jam $\times 14.000 \times 2.0 = \text{Rp } 140.000$.
  - **Total Biaya Lembur**: $\text{Rp } 42.000 + \text{Rp } 140.000 = \mathbf{\text{Rp } 182.000}$.

### Karyawan 3: Siti (Lembur Hari OFF/Libur dengan Tarif Belum Dikonfirmasi)
- **Kondisi**: Bekerja 26 hari normal, 5 hari OFF, 0 absen. Lembur biasa: 0 jam.
- **Lembur OFF/Libur**: 1 hari lembur pada hari OFF/Libur, total 8.0 jam lembur.
  - Potongan Istirahat: $8.0 / 8 = 1.0$ jam.
  - Jam Lembur Bersih: $8.0 - 1.0 = 7.0$ jam.
  - Status Kebijakan Tarif Perusahaan: **Belum Terkonfirmasi** (`off_holiday_rate_confirmed = false`).
  - **Biaya Lembur Resmi di Slip Gaji**: $\mathbf{\text{Rp } 0}$ *(Mencegah pencairan tanpa otorisasi)*.
  - **Indikator Sistem**: Status *"Menunggu konfirmasi kebijakan tarif lembur OFF/Libur dari manajemen"*.
  - **Estimasi Acuan PP 35/2021**: $7.0\text{ jam} \times 14.000 \times 2.0 = \text{Rp } 196.000$ (ditampilkan sebagai referensi informatif bagi HRD di UI dan snapshot).

---

## 9. Hasil Automated Testing & Verifikasi Regresi

### A. Test Suite Unit Baru: `tests/Unit/SecurityCalculationEngineTest.php`
- `✓ attendance hours formula is present days multiplied by eight`
- `✓ target hours formula matches excel cell ap`
- `✓ current shortage calculation matches excel cell ar`
- `✓ total shortage calculation matches excel cell at`
- `✓ shortage offset reduces overtime and calculates remaining shortage`
- `✓ regular overtime verified excel formula wawan fixture`
- `✓ off holiday overtime separation and unconfirmed rate protection`
*Hasil: 7 passed, 36 assertions.*

### B. Test Suite Feature Baru: `tests/Feature/SecurityCalculationEngineFeatureTest.php`
- `✓ employee scenario one wawan verified excel calculation`
- `✓ employee scenario two budi shortage compensation`
- `✓ employee scenario three siti off holiday unconfirmed rate`
- `✓ preview endpoint returns json metrics without database mutations`
- `✓ automatic carry forward from preceding finalized period`
*Hasil: 5 passed, 46 assertions.*

### C. Full Regression Suite (Seluruh Modul Aplikasi):
```text
Total Tests:       305 passed (1690 assertions)
Total Failures:    0 failures
Execution Time:    54.54s
Pass Rate:         100%
```

---

## 10. Daftar File yang Dibuat dan Diubah

| Aksi | Path File |
| :--- | :--- |
| **Created** | `app/Services/Security/SecurityAttendanceCalculator.php` |
| **Created** | `app/Services/Security/SecurityOvertimeCalculator.php` |
| **Created** | `database/migrations/2026_11_11_000035_add_calculation_engine_fields_to_security_overtime_summaries_table.php` |
| **Created** | `tests/Unit/SecurityCalculationEngineTest.php` |
| **Created** | `tests/Feature/SecurityCalculationEngineFeatureTest.php` |
| **Created** | `PAYROLL_SECURITY_PHASE_2_REPORT.md` |
| **Modified** | `app/Models/SecurityOvertimeSummary.php` |
| **Modified** | `app/Services/SecurityPayrollCalculationService.php` |
| **Modified** | `app/Http/Controllers/SecurityPayrollController.php` |
| **Modified** | `resources/js/Pages/SecurityPayroll/Index.jsx` |
| **Modified** | `routes/web.php` |

---

## 11. Status Kesiapan & Langkah Selanjutnya

Phase 2 telah selesai secara sempurna. Seluruh kriteria penerimaan terpenuhi dengan zero regression.
Sesuai **Execution Rules**:
> **PENTING**: Pengerjaan **Phase 3** (Finalisasi Modul, Excel Export Refinement, atau UAT Checklist) **TIDAK AKAN DIMULAI** sebelum USER memberikan persetujuan eksplisit.

