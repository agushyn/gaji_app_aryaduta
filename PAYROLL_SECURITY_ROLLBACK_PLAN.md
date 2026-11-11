# Rencana Pemulihan & Rollback Produksi: Modul Payroll Security
## Aplikasi: Kirim Slip Gaji Personalia
**Target Lingkungan**: cPanel / Linux Server (`/www/wwwroot/gaji_payroll`)  
**URL Produksi**: `https://rumahimpianku.it.com`  
**Dokumen Referensi**: `PAYROLL_SECURITY_DEPLOYMENT_RUNBOOK.md`  
**Versi Panduan**: 1.0 (11 November 2026)  

---

## 1. Tujuan & Ruang Lingkup Rollback

Rencana rollback ini dirancang untuk memulihkan sistem ke kondisi stabil sebelum deployment modul Payroll Security jika terjadi kegagalan fatal, inkonsistensi data, gangguan layanan kritis, atau regresi pada modul penggajian reguler.

Prosedur ini memastikan bahwa:
1. Data historis slip gaji dan data karyawan yang telah ada sebelumnya tetap aman dan tidak mengalami kerusakan.
2. Waktu henti sistem (*downtime*) diminimalisasi sekecil mungkin.
3. Kredensial dan konfigurasi server tidak hilang atau rusak saat pemulihan.

---

## 2. Pemicu Rollback & Matriks Keputusan (Rollback Triggers & Decision Matrix)

Rollback **WAJIB** segera dieksekusi jika salah satu kondisi berikut terjadi selama smoke testing atau dalam 60 menit pertama pasca deployment:

| Kode Pemicu | Skenario Kegagalan Kritis | Batas Toleransi | Tindakan |
|:---:|---|---|:---:|
| **TRG-01** | Autentikasi Login Gagal Total (Error 500 atau loop redirect) | 0 insiden | **Rollback Penuh Segera** |
| **TRG-02** | Regresi Modul Payroll Reguler (Staf/Karyawan tidak bisa diproses) | 0 insiden | **Rollback Penuh Segera** |
| **TRG-03** | Inkonsistensi Kalkulasi Payroll Security (Mismatch nominal signifikan) | > 0 selisih | **Rollback Aplikasi** |
| **TRG-04** | Kerusakan Data / Anomali Foreign Key pada database | 0 toleransi | **Restore Database Penuh** |
| **TRG-05** | Dokumen PDF Slip Gaji Final Tidak Dapat Dibuka / Hilang | 0 toleransi | **Restore Storage & DB** |
| **TRG-06** | Pengiriman Antrean Mengalami Duplikasi (Duplicate delivery attempt) | > 0 duplikasi | **Hentikan Cron & Rollback** |
| **TRG-07** | Kegagalan Fatal HTTP 500 yang tidak dapat diperbaiki dalam 15 menit | > 15 menit | **Rollback Penuh Segera** |

---

## 3. Prosedur Eksekusi Rollback Langkah-demi-Langkah

### Tahap 1: Aktifkan Segera Mode Pemeliharaan Darurat
Hentikan seluruh akses pengguna eksternal untuk mencegah modifikasi data baru:
```bash
cd /www/wwwroot/gaji_payroll

/opt/cpanel/ea-php83/root/usr/bin/php artisan down \
  --message="Sistem sedang menjalani pemulihan darurat. Harap tunggu beberapa saat." \
  --retry=60
```

---

### Tahap 2: Matikan Sementara Cron Job Antrean
Untuk mencegah cron worker memproses job di tengah-tengah proses pemulihan database, beri tanda komentar (`#`) pada cron job cPanel:
- Buka cPanel > **Cron Jobs**.
- Nonaktifkan sementara entri `artisan schedule:run` dan `payroll:process-queue`.

---

### Tahap 3: Pemulihan Basis Data (Database Rollback)

Pilih salah satu strategi pemulihan database berdasarkan akar masalah:

#### Strategi A: Rollback Skema Migrasi (Jika Database Utuh & Tidak Korup)
Gunakan strategi ini jika data operasional tidak rusak dan hanya perlu membatalkan migrasi modul Security (Migrations 34 dan 35):
```bash
cd /www/wwwroot/gaji_payroll

# Rollback 2 migrasi terakhir (000034 dan 000035)
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:rollback --step=2 --force

# Verifikasi status migrasi
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
```
*Metode ini aman karena method `down()` pada migrasi 34 dan 35 telah dirancang khusus untuk mendrop kolom tambahan tanpa menghapus tabel `security_overtime_summaries`.*

#### Strategi B: Pemulihan Penuh dari File Backup (Jika Terjadi Kerusakan Data)
Gunakan strategi ini jika data historis mengalami kerusakan atau anomali fatal:
```bash
# Arahkan ke direktori backup yang dibuat sebelum deployment
cd /www/backups/payroll_YYYYMMDD

# Restore database dari file cadangan terkompresi
gunzip < db_backup_pre_security_YYYYMMDD_HHMMSS.sql.gz | mysql -u DB_USERNAME_PRODUKSI -p DB_DATABASE_PRODUKSI

echo "Restore database selesai dieksekusi"
```

---

### Tahap 4: Pemulihan Kode Aplikasi & Aset Frontend

Kembalikan source code aplikasi ke rilis stabil sebelumnya:

#### Opsi A: Jika Menggunakan Git
```bash
cd /www/wwwroot/gaji_payroll

# Batalkan perubahan dan checkout commit stabil terakhir (sebelum merge Phase 6)
git reset --hard COMMIT_HASH_SEBELUM_DEPLOY
# atau checkout tag rilis sebelumnya
git checkout v1.0.0-stable

# Bersihkan file untracked yang tertinggal
git clean -fd
```

#### Opsi B: Jika Menggunakan Backup Arsip File
```bash
cd /www/wwwroot

# Ganti direktori gaji_payroll dengan cadangan pra-deployment
mv gaji_payroll gaji_payroll_failed_$(date +%H%M%S)
cp -r /www/backups/payroll_YYYYMMDD/gaji_payroll_pre_deploy /www/wwwroot/gaji_payroll
```

---

### Tahap 5: Rebuild Cache & Pengaturan Izin File
```bash
cd /www/wwwroot/gaji_payroll

# Bersihkan dan rebuild cache untuk versi kode yang dipulihkan
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache

# Pastikan permission storage tetap benar
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

---

### Tahap 6: Pengujian Verifikasi Pasca-Rollback

Sebelum membuka sistem kembali untuk pengguna:
1. **Akses Dashboard**: Login sebagai Personalia dan pastikan modul payroll reguler berfungsi normal.
2. **Uji Slip Gaji**: Buka salah satu slip gaji yang sudah difinalisasi pada periode sebelumnya dan pastikan unduhan PDF berhasil dan integritas hash SHA-256 valid.
3. **Pemeriksaan Log**: Pastikan tidak ada exception baru pada `storage/logs/laravel.log`.

---

### Tahap 7: Aktifkan Kembali Cron Job & Buka Sistem
1. Buka cPanel > **Cron Jobs**, aktifkan kembali entri `artisan schedule:run`.
2. Matikan mode maintenance:
   ```bash
   cd /www/wwwroot/gaji_payroll
   /opt/cpanel/ea-php83/root/usr/bin/php artisan up
   ```

---

## 4. Templat Komunikasi & Laporan Insiden (Post-Mortem Template)

Jika rollback dilakukan, sampaikan pemberitahuan resmi kepada pemangku kepentingan menggunakan templat berikut:

```text
Kepada: Tim Manajemen & Personalia
Perihal: Pemberitahuan Pemulihan Sistem (Rollback) Aplikasi Kirim Slip Gaji

Dengan ini kami informasikan bahwa proses deployment enhancement modul Payroll Security pada [Tanggal & Waktu] telah dibatalkan dan sistem telah dipulihkan secara penuh ke versi stabil sebelumnya.

- Status Sistem Saat Ini: NORMAL & STABIL
- Dampak Data: 0 data historis hilang / seluruh data payroll lama tetap utuh.
- Alasan Pemulihan: [Sebutkan kode pemicu, misal: Ditemukan kendala teknis pada sinkronisasi antrean / formula].
- Tindak Lanjut: Tim teknis akan melakukan evaluasi dan pengujian mendalam pada lingkungan pengujian sebelum menjadwalkan ulang rilis.

Terima kasih atas pengertian dan kerjasamanya.
```

