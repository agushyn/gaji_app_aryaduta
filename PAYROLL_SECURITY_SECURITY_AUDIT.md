# LAPORAN AUDIT KEAMANAN (SECURITY HARDENING REPORT)
## MODUL PAYROLL SECURITY — KIRIM SLIP GAJI PERSONALIA

**Aplikasi:** Kirim Slip Gaji Personalia  
**Modul:** Payroll Security (`/payroll-security`, `/payroll-security/reports`)  
**File Test Terkait:** [`tests/Feature/SecurityPayrollSecurityHardeningTest.php`](file:///c:/Antigravity-Project\Laravel\Gaji_Personalia\tests\Feature\SecurityPayrollSecurityHardeningTest.php)  
**Tanggal Audit:** 11 November 2026  
**Status Keamanan:** **HARDENED & VERIFIED (13/13 SECURITY TESTS PASSING)**  

---

## 1. Ringkasan Eksekutif Keamanan

Audit keamanan dilakukan untuk memastikan seluruh proses bisnis dan data sensitif penggajian personel Security terlindungi dari akses ilegal, manipulasi parameter, eskalasi hak akses (privilege escalation), serangan injeksi formula spreadsheet, serta pelanggaran integritas data slip gaji.

### Hasil Pengujian Keamanan:
```
PASS  Tests\Feature\SecurityPayrollSecurityHardeningTest
✓ unauthenticated user cannot access security routes                                0.58s
✓ unauthorized user cannot access security dashboard or reports                     0.10s
✓ auditor has strictly read only access                                             0.23s
✓ user without config permission cannot update config                               0.10s
✓ input validation rejects negative days and hours                                  0.10s
✓ input validation rejects decimal days                                             0.10s
✓ input validation rejects days exceeding period                                    0.09s
✓ calculated fields cannot be tampered from request                                 0.11s
✓ cannot modify payroll in review status                                            0.09s
✓ cannot modify finalized payroll period                                            0.09s
✓ idempotent payroll sync does not duplicate components                             0.15s
✓ expired or tampered signed pdf url is rejected                                    0.09s
✓ cwe 1236 formula injection defense                                                0.09s

Tests:    13 passed (45 assertions)
Duration: 2.33s
```

---

## 2. Analisis Vektor Ancaman dan Langkah Mitigasi

### A. Autentikasi & Kontrol Akses Berbasis Peran (RBAC)
- **Vektor Ancaman**: Akses endpoint tanpa login atau oleh user tanpa hak akses (contoh: staff umum membuka data payroll security atau auditor mengubah konfigurasi rumus).
- **Mitigasi**:
  1. Middleware `auth` dan `active` diterapkan pada seluruh rute aplikasi.
  2. Pengecekan izin granular pada controller:
     - `payroll.security.view`: Hak melihat matriks input dan laporan.
     - `payroll.security.manage`: Hak menginput dan menyimpan rekap kehadiran/lembur.
     - `payroll.security.config`: Hak mengubah parameter rumus upah/jam dan jam pertama.
     - `reports.export`: Hak mengunduh file spreadsheet/CSV.
  3. **Jaminan Read-Only Auditor**: Peran `auditor` diuji secara ketat. Auditor diizinkan mengakses laporan, menjalankan uji rekonsiliasi, dan mengunduh berkas, tetapi **ditolak secara mutlak (403 Forbidden)** ketika mencoba mengubah data absensi, rekap, sinkronisasi slip gaji, atau alur persetujuan.

---

### B. Validasi Input & Pencegahan Manipulasi Data Klien
- **Vektor Ancaman**: Penyerang mengirimkan nilai negatif, nilai desimal pada field hari, jumlah hari melebihi kalender periode, atau menyuntikkan nominal gaji fiktif langsung pada payload request HTTP.
- **Mitigasi**:
  1. **Strict Type & Boundary Validation**:
     Field hari divalidasi dengan aturan `integer`, `min:0`, `max:62`. Field jam divalidasi dengan aturan `numeric`, `min:0`, `max:500`.
  2. **Period Calendar Boundary Check**:
     Total penjumlahan hari $(\text{Masuk} + \text{OFF} + \text{Sakit} + \text{Izin} + \text{Cuti} + \text{Alfa})$ wajib $\le$ total hari kalender periode aktif. Jika melebihi, sistem menolak request dengan pesan error validasi.
  3. **Zero Trust on Frontend Computed Values**:
     Field kalkulasi seperti `attendance_hours`, `regular_overtime_cost`, `total_overtime_cost`, dan `net_salary` **diabaikan sepenuhnya dari input HTTP**. Nilai-nilai tersebut hanya boleh diproduksi oleh `SecurityPayrollCalculationService` di backend menggunakan pustaka presisi tinggi `BCMath`.

---

### C. Integritas State Machine & Mutasi Data Finalized
- **Vektor Ancaman**: Mengubah absensi saat payroll sedang ditinjau atasan (`in_review`), atau mengubah nominal pada periode yang telah berstatus `finalized`.
- **Mitigasi**:
  1. Pengecekan method `$period->canBeModified()` pada setiap operasi mutasi (`updateAttendance`, `updateSummary`, `bulkUpdate`, `syncPayslips`).
  2. Jika periode berada dalam status `in_review`, `approved`, atau `finalized`, backend langsung menolak aksi dengan melempar `DomainException`.
  3. Pengecekan idempotensi: Eksekusi sinkronisasi berulang kali tidak pernah menduplikasi item `OVERTIME` pada slip gaji.

---

### D. Keamanan Slip Gaji & Penyimpanan Dokumen Privat
- **Vektor Ancaman**: PDF slip gaji diakses melalui URL publik tanpa otentikasi, atau ditebak (IDOR / enumeration).
- **Mitigasi**:
  1. File PDF slip gaji disimpan pada disk non-publik (`storage/app/private/payslips/`) yang tidak dapat diakses langsung via web server (Nginx/Apache).
  2. Pengunduhan slip gaji wajib menggunakan URL bertanda tangan kriptografis sementara (**Temporary HMAC Signed URL**).
  3. Sistem memvalidasi parameter signature dan masa kedaluwarsa URL. Upaya modifikasi signature atau penggunaan URL yang telah kedaluwarsa langsung ditolak dengan kode status `403 Forbidden`.
  4. Setiap file PDF yang dihasilkan dilengkapi stempel hash digital **SHA-256** untuk menjamin integritas berkas.

---

### E. Mitigasi CSV Formula Injection (CWE-1236)
- **Vektor Ancaman**: Data teks karyawan atau catatan yang mengandung payload formula jahat (seperti `=cmd|'/C calc'!A0`, `+cmd`, `@SUM(...)`, `\tHYPERLINK(...)`) dapat dieksekusi secara otomatis oleh aplikasi spreadsheet (Excel/LibreOffice) ketika pengguna membuka file ekspor CSV.
- **Mitigasi**:
  1. Sistem menerapkan fungsi sanitasi khusus `SecurityPayrollExportService::sanitizeCell()`.
  2. Karakter pemicu formula berbahaya (`=`, `+`, `-`, `@`, `\t`, `\r`) pada string teks otomatis diawali dengan tanda kutip tunggal (`'`).
  3. Nilai numerik riil (seperti nominal negatif `"-15.50"`, nilai bertanda plus `"+100"`, atau angka gaji `"4500000"`) diperiksa melalui `is_numeric()` dan **dibiarkan sebagai angka murni tanpa kutip**, sehingga tidak merusak fungsionalitas perhitungan angka pengguna.
  4. File CSV disematkan **UTF-8 BOM (`\xEF\xBB\xBF`)** di awal stream untuk mencegah kesalahan decoding teks pada sistem operasi Windows.

