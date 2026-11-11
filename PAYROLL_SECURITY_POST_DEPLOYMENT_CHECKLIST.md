# Checklist Pengujian Pasca-Deployment (Smoke Testing): Payroll Security
## Aplikasi: Kirim Slip Gaji Personalia
**Target URL**: `https://rumahimpianku.it.com`  
**Lingkungan**: Produksi (cPanel / Linux `/www/wwwroot/gaji_payroll`)  
**Tanggal Pengujian**: ____________________  
**Penguji (Tester)**: ____________________  

---

> [!IMPORTANT]
> **PANDUAN KESELAMATAN SMOKE TESTING PRODUKSI**:
> - Dilarang membuat periode penggajian riil atau memfinalisasi data riil karyawan hanya untuk pengujian.
> - Gunakan akun test internal yang sudah ditunjuk atau verifikasi read-only pada data draft test.
> - Uji integrasi email dan WhatsApp hanya boleh menggunakan nomor dan alamat email internal staf teknis. Dilarang melakukan broadcast.

---

## Bagian 1: Autentikasi, Sesi, dan Keamanan Pengguna

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 1.1 | Pemuatan Halaman Login | Akses `https://rumahimpianku.it.com/login` | Form login tampil sempurna, CSS Tailwind v4 & Vite JS termuat tanpa error 404/500 | [ ] | |
| 1.2 | Login Super Admin / Personalia | Masukkan email & password akun terotorisasi | Berhasil masuk ke Dashboard tanpa redirect loop | [ ] | |
| 1.3 | Penolakan Akun Nonaktif | Login akun dengan `is_active = false` | Ditolak dengan pesan jelas "Akun Anda telah dinonaktifkan" | [ ] | |
| 1.4 | Rate Limiting Login | Coba login 5x dengan password salah | Respon HTTP 429 Too Many Requests aktif | [ ] | |
| 1.5 | Logout & Regenerasi Token CSRF | Klik Logout | Sesi terhapus, token CSRF di-regenerate, kembali ke login | [ ] | |

---

## Bagian 2: Kontrol Akses Berbasis Peran (RBAC) & Boundary Isolation

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 2.1 | Akses Role Personalia | Login sebagai Personalia, buka `/payroll-security` | Form input, tombol preview, dan sync aktif | [ ] | |
| 2.2 | Akses Role Approver | Login sebagai Approver, buka `/payroll-security` | Halaman dapat dilihat, tombol aksi mutasi tidak tampil/nonaktif | [ ] | |
| 2.3 | Akses Role Auditor | Login sebagai Auditor, buka `/payroll-security/reports` | Laporan dapat dilihat, ekspor CSV memasking kolom nominal `[TERBATAS]` | [ ] | |
| 2.4 | Akses Tanpa Izin | Pengguna tanpa permission `payroll.security.view` mengakses `/payroll-security` | Sistem menolak dengan HTTP 403 Forbidden | [ ] | |

---

## Bagian 3: Alur Inti Modul Payroll Security (Core Workflows)

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 3.1 | Tampilan Rekap Security | Buka menu Payroll Security dan pilih periode Draft | Tabel personel Security termuat dengan kolom kehadiran dan lembur | [ ] | |
| 3.2 | Modal Input Kehadiran & Lembur | Klik tombol "Kelola Kehadiran & Lembur" salah satu staf Security | Modal terbuka, field input Hari Masuk, Hari Libur, Lembur Reguler, dan Lembur Libur tampil | [ ] | |
| 3.3 | Perhitungan Otomatis (Calculation Engine) | Ubah nilai hari masuk (misal: 22 hari) dan lembur (10 jam), klik Simpan | Jam kerja terhitung otomatis (22 × 8 = 176 jam), kekurangan jam terhitung akurat | [ ] | |
| 3.4 | Pemisahan Lembur Reguler vs Hari Libur | Masukkan jam lembur reguler dan jam lembur hari libur | Sistem menghitung tarif lembur hari kerja (1,5x jam ke-1, 2,0x jam berikutnya) terpisah dari lembur libur | [ ] | |
| 3.5 | Potongan Istirahat Lembur | Masukkan lembur ≥ 8 jam | Sistem memotong 1 jam istirahat secara otomatis | [ ] | |
| 3.6 | Checkbox Konfirmasi Tarif Lembur Libur | Aktifkan/nonaktifkan checkbox konfirmasi tarif libur | Status tersimpan dan nilai biaya lembur libur terhitung presisi | [ ] | |
| 3.7 | Sinkronisasi ke Slip Gaji Periode | Klik tombol "Sinkronisasi ke Slip Gaji" | Komponen gaji OVERTIME pada slip gaji terupdate sesuai hasil rekap | [ ] | |

---

## Bagian 4: Pelaporan, Analitik, dan Rekonsiliasi Otomatis

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 4.1 | Dashboard Monitoring & Pelaporan | Akses `/payroll-security/reports` | 4 kartu ringkasan (Total Jam, Jam Kurang, Lembur Reguler, Lembur Libur) terisi metrik akurat | [ ] | |
| 4.2 | Rekonsiliasi Otomatis (Auto-Reconcile) | Klik tombol "Jalankan Rekonsiliasi" | Sistem mencocokkan seluruh poin cek; status "100% Cocok (0 Mismatch)" | [ ] | |
| 4.3 | Ekspor Laporan Excel (XLSX) | Klik "Ekspor XLSX" pada tab Ringkasan | File XLSX terunduh, dapat dibuka di Microsoft Excel tanpa peringatan file korup | [ ] | |
| 4.4 | Ekspor Laporan CSV & Sanitasi CWE-1236 | Unduh file CSV laporan dengan data mengandung karakter formula (`=`, `@`, `+`, `-`) | Sel tersanitasi dengan prefix petik tunggal (`'`), formula tidak tereksekusi di Excel | [ ] | |

---

## Bagian 5: Keamanan Dokumen Slip Gaji Privat (Private Storage & PDF)

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 5.1 | Proteksi Akses Langsung Storage | Buka URL langsung ke file di browser: `https://rumahimpianku.it.com/storage/app/private/payslips/...` | Server merespons HTTP 403 Forbidden atau 404 Not Found | [ ] | |
| 5.2 | Unduh PDF Slip Gaji Terotorisasi | Login sebagai Personalia, klik unduh slip gaji pada periode final | Dokumen PDF terunduh dengan layout resmi (110 mm × 95 mm, 3 tanda tangan) | [ ] | |
| 5.3 | HMAC Expiring Signed URL | Buat URL signed sementara untuk slip gaji, akses via browser | URL valid dalam masa berlaku (15 menit); akses setelah kedaluwarsa menghasilkan HTTP 403 Invalid Signature | [ ] | |

---

## Bagian 6: Task Scheduler, Antrean, dan Integrasi

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 6.1 | Eksekusi Scheduler Cron | Jalankan `php artisan schedule:run` di terminal | Scheduler berjalan sukses tanpa error | [ ] | |
| 6.2 | Safety Runner Antrean | Jalankan `php artisan payroll:process-queue --limit=5 --timeout=30` | Atomic lock bekerja; jika antrean kosong proses keluar dengan kode 0 | [ ] | |
| 6.3 | Tes Koneksi SMTP Email | Buka menu Pengaturan Email, klik "Tes Koneksi" | Koneksi SMTP sukses; tidak ada pengiriman massal atau kebocoran password di log | [ ] | |
| 6.4 | Tes Koneksi WhatsApp Gateway | Buka menu Pengaturan WhatsApp, kirim pesan tes ke nomor internal | Pesan tes diterima di nomor internal; format status tercatat rapi di database | [ ] | |

---

## Bagian 7: Pemeriksaan Log & Integritas Client-Side

| No | Butir Pengujian | Langkah Verifikasi | Hasil yang Diharapkan | Status | Catatan |
|:---:|---|---|---|:---:|---|
| 7.1 | Laravel Application Log | Buka `/www/wwwroot/gaji_payroll/storage/logs/laravel.log` | Tidak terdapat catatan `CRITICAL`, `ERROR`, atau `EXCEPTION` baru | [ ] | |
| 7.2 | Web Server Error Log | Periksa cPanel Error Log / Apache / Nginx error log | Tidak ada log HTTP 500 atau segfault modul PHP | [ ] | |
| 7.3 | Browser Console & Network | Buka Developer Tools (F12) > Console & Network | Nol error JavaScript (0 red errors) dan seluruh API call HTTP 200 OK | [ ] | |

---

## Lembar Persetujuan Hasil UAT & Smoke Testing (Sign-Off)

| Peran Pemangku Kepentingan | Nama Penilai | Keputusan (DITERIMA / DITOLAK) | Tanggal | Tanda Tangan |
|---|---|:---:|:---:|---|
| **Staf Personalia / HRD** | ____________________ | [ ] DITERIMA  [ ] DITOLAK | ____________ | ________________ |
| **Approver / Finance Head** | ____________________ | [ ] DITERIMA  [ ] DITOLAK | ____________ | ________________ |
| **Auditor Internal** | ____________________ | [ ] DITERIMA  [ ] DITOLAK | ____________ | ________________ |
| **Super Admin / Tech Lead** | ____________________ | [ ] DITERIMA  [ ] DITOLAK | ____________ | ________________ |

