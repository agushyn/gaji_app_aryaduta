# LAPORAN PENGUJIAN END-TO-END (E2E)
## MODUL PAYROLL SECURITY — KIRIM SLIP GAJI PERSONALIA

**Aplikasi:** Kirim Slip Gaji Personalia  
**Modul:** Payroll Security (`/payroll-security`, `/payroll-security/reports`)  
**File Test Terkait:** [`tests/Feature/SecurityPayrollEndToEndTest.php`](file:///c:/Antigravity-Project/Laravel/Gaji_Personalia/tests/Feature/SecurityPayrollEndToEndTest.php)  
**Tanggal Pengujian:** 11 November 2026  
**Hasil Keseluruhan:** **6 SCENARIOS PASSED (100% SUKSES, 0 GAGAL)**  

---

## 1. Ringkasan Hasil Pengujian

Pengujian End-to-End (E2E) memvalidasi seluruh alur kerja operasional Payroll Security mulai dari input kehadiran dan lembur, perhitungan otomatis di backend, sinkronisasi komponen slip gaji, alur persetujuan bertingkat, finalisasi dan pembuatan snapshot, hingga pelaporan, rekonsiliasi, dan ekspor.

```
PASS  Tests\Feature\SecurityPayrollEndToEndTest
✓ scenario 1 normal payroll lifecycle                                              1.04s
✓ scenario 2 shortage and carry forward lifecycle                                  0.10s
✓ scenario 3 overtime separation and protection                                    0.09s
✓ scenario 4 request changes workflow                                              0.13s
✓ scenario 5 finalized payroll immutability                                        0.37s
✓ scenario 6 reporting reconciliation and export                                   0.28s

Tests:    6 passed (60 assertions)
Duration: 2.44s
```

---

## 2. Rincian Skenario dan Hasil Verifikasi

### Skenario 1: Siklus Normal Payroll Lengkap (`scenario_1_normal_payroll`)
- **Alur yang Diuji**:
  1. HRD memilih periode payroll aktif (`E2E-2026-01`).
  2. HRD memasukkan data kehadiran (26 hari masuk, 5 hari off) dan lembur (18 jam lembur biasa, 8 jam lembur libur) melalui endpoint `POST /payroll-security/summary`.
  3. Backend Laravel secara otomatis menghitung jam hadir aktual ($26 \times 8 = 208\text{ jam}$).
  4. Calculation Engine menghitung potongan istirahat lembur biasa ($18 / 8 = 2.25\text{ jam}$), jam lembur pertama (6 hari $\times 1.5\text{x}$), dan jam lembur lanjutan ($9.75\text{ jam} \times 2.0\text{x}$).
  5. HRD menguji endpoint `POST /payroll-security/preview` untuk melihat simulasi kalkulasi tanpa persistensi.
  6. HRD melakukan generate slip gaji dan sinkronisasi (`POST /payroll-security/periods/{period}/sync`).
  7. Sistem memasukkan komponen `OVERTIME` ke slip gaji dengan rincian biaya dan jam yang cocok dengan rekap.
  8. HRD mengajukan periode gaji untuk persetujuan (`POST /approvals/periods/{period}/submit`). Status berubah menjadi `in_review`.
  9. Approver menyetujui pengajuan (`POST /approvals/periods/{period}/approve`). Status berubah menjadi `approved`.
  10. Periode difinalisasi oleh sistem. Sistem mengunci data, membuat stempel SHA-256 PDF, dan menyimpan snapshot identitas serta komponen finansial.
- **Hasil**: **PASS (60 assertions)**. Integritas data terjaga konsisten di setiap tahapan.

---

### Skenario 2: Kekurangan Jam Kerja dan Carry-Forward (`scenario_2_shortage_and_carry_forward`)
- **Alur yang Diuji**:
  1. Karyawan memiliki kekurangan 16 jam kerja pada periode sebelumnya (`E2E-2026-02`).
  2. Pada periode berjalan (`E2E-2026-03`), sistem mengambil carry-forward sebesar 16 jam.
  3. Karyawan hadir 24 hari masuk dan 7 hari off (total 31 hari kalender). Target jam kerja tercapai penuh sehingga kekurangan jam berjalan adalah 0 jam.
  4. Total kekurangan jam adalah $0 + 16 = 16\text{ jam}$.
  5. Karyawan memiliki 20 jam lembur biasa. Sesuai aturan kompensasi (offset), 16 jam lembur digunakan untuk menutup kekurangan jam kerja.
  6. Sisa kekurangan jam kerja menjadi **0.00 jam (TUNTAS)**.
  7. Laporan Shortage memverifikasi asal periode carry-forward, nilai offset, dan status lunas.
- **Hasil**: **PASS**. Kekurangan jam tidak pernah dihitung ganda dan kompensasi lembur berjalan tepat.

---

### Skenario 3: Pemisahan Kategori Lembur & Proteksi Tarif (`scenario_3_overtime`)
- **Alur yang Diuji**:
  1. HRD menginput 12 jam lembur biasa dan 16 jam lembur hari libur.
  2. Status persetujuan tarif lembur libur diset `false` (belum ada kesepakatan tertulis dari manajemen).
  3. Calculation engine secara mandiri menghitung lembur biasa dan memproteksi lembur libur:
     - Biaya lembur biasa: Terhitung proporsional sesuai formula 1.5x dan 2.0x.
     - Biaya lembur libur: **Wajib Rp 0.00** dan diberi keterangan *Menunggu Konfirmasi Tarif*.
  4. Laporan lembur menampilkan status `PENDING_KONFIRMASI` pada kategori lembur libur.
- **Hasil**: **PASS**. Menjamin perusahaan tidak membayarkan nominal tidak berdasar tanpa otorisasi tertulis.

---

### Skenario 4: Alur Revisi & Penolakan Perubahan (`scenario_4_request_changes`)
- **Alur yang Diuji**:
  1. HRD mengajukan periode payroll (`submit`). Status: `in_review`.
  2. Approver menemukan kekeliruan data dan menekan tombol *Request Changes* dengan catatan perbaikan.
  3. Status periode otomatis kembali ke `changes_requested`, dan slip gaji berstatus dapat diedit kembali (`canBeModified() == true`).
  4. HRD memperbaiki data kehadiran dan jam lembur.
  5. Calculation engine menghitung ulang seluruh metrik secara otomatis.
  6. HRD mengajukan ulang (`resubmit`). Status kembali ke `in_review`.
  7. Approver menyetujui hasil revisi terbaru (`approve`). Status menjadi `approved`.
  8. Tabel `payroll_approval_events` menyimpan kronologi lengkap: `submit` $\rightarrow$ `request_changes` $\rightarrow$ `resubmit` $\rightarrow$ `approve`.
- **Hasil**: **PASS**. Seluruh siklus revisi terdokumentasi tanpa kehilangan riwayat audit.

---

### Skenario 5: Imutabilitas Payroll Berstatus Final (`scenario_5_finalized_payroll`)
- **Alur yang Diuji**:
  1. Periode payroll dan slip gaji difinalisasi (`finalized`).
  2. Nilai snapshot slip (`basic_salary`, `net_salary`, `pdf_sha256`) dicatat sebagai baseline.
  3. Disimulasikan perubahan pada data master karyawan: gaji pokok dinaikkan secara ekstrem menjadi Rp 99.000.000.
  4. Sistem diverifikasi: data slip gaji lama dan snapshot PDF **sama sekali tidak berubah**.
  5. Disimulasikan percobaan pengeditan data kehadiran atau rekap pada periode yang sudah final melalui HTTP request langsung.
  6. Backend Laravel menolak mutasi tersebut dengan melempar `DomainException`.
- **Hasil**: **PASS**. Payroll yang telah final terbukti kebal dari manipulasi maupun efek samping perubahan master data.

---

### Skenario 6: Pelaporan, Rekonsiliasi, dan Ekspor Dokumen (`scenario_6_reporting_and_export`)
- **Alur yang Diuji**:
  1. Mengakses endpoint dashboard `/payroll-security/reports` dengan parameter filter periode dan kantor.
  2. Menjalankan mesin rekonsiliasi mandiri (`POST /payroll-security/reconciliation/run`).
  3. Mengunduh laporan via format `.xlsx` (memverifikasi header `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`).
  4. Mengunduh laporan via format `.csv` (memverifikasi header `Content-Type: text/csv; charset=UTF-8` dan keberadaan UTF-8 BOM).
- **Hasil**: **PASS**. Data yang dihasilkan pada file ekspor identik dengan data pada database.

