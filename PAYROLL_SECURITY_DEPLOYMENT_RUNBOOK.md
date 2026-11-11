# Runbook Deployment Produksi: Modul Payroll Security
## Aplikasi: Kirim Slip Gaji Personalia
**Target Deployment**: cPanel / Linux Server (`/www/wwwroot/gaji_payroll`)  
**URL Produksi**: `https://rumahimpianku.it.com`  
**Target Rilis**: Enhancement Payroll Security (Phase 1 s/d Phase 6)  
**Versi Panduan**: 1.0 (11 November 2026)  

---

> [!CAUTION]
> **ATURAN KESELAMATAN WAJIB (MANDATORY SAFETY RULES)**:
> 1. Dilarang melakukan eksekusi deployment pada server produksi sebelum menerima persetujuan eksplisit dari pemangku kepentingan / User.
> 2. Dilarang menjalankan perintah destruktif (`migrate:fresh`, `db:wipe`, `migrate:reset`).
> 3. Selalu buat cadangan (backup) lengkap database MySQL dan direktori `storage/app/private/` sebelum menyentuh file apa pun di produksi.
> 4. Pastikan file `.env` produksi tidak tertimpa oleh file `.env` lokal.

---

## 1. Persiapan Pra-Deployment (Pre-Deployment Checklist)

Sebelum memulai jendela deployment (*maintenance window*), pastikan checklist berikut telah terpenuhi:

- [ ] **Persetujuan Rilis**: Telah memperoleh persetujuan tertulis dari pimpinan proyek / Super Admin.
- [ ] **Jendela Pemeliharaan (Maintenance Window)**: Telah mengumumkan jadwal pemeliharaan sistem kepada HRD/Personalia dan Manajemen (disarankan di luar jam operasional penggajian).
- [ ] **Kesiapan Artefak Rilis**:
  - Seluruh 364 automated tests lulus di lokal (`php artisan test`).
  - Frontend assets telah selesai dikompilasi dengan sukses (`npm run build`).
  - File `public/build/manifest.json` dan folder `public/build/assets/` siap diunggah.
- [ ] **Identifikasi Versi PHP Server**:
  - Temukan path eksekusi PHP 8.3+ di terminal cPanel server:
    ```bash
    # Cari binary PHP 8.3 yang aktif di server
    which php
    /opt/cpanel/ea-php83/root/usr/bin/php -v
    # atau
    /usr/local/bin/php -v
    ```
    *(Simpan path binary yang terkonfirmasi PHP 8.3+ untuk seluruh eksekusi perintah berikutnya)*.

---

## 2. Prosedur Pencadangan Lengkap (Backup Procedures)

Lakukan backup database dan file aplikasi di direktori aman di luar root web server.

### A. Backup Database MySQL Produksi
```bash
# Buat folder backup khusus jika belum ada
mkdir -p /www/backups/payroll_$(date +%Y%m%d)
cd /www/backups/payroll_$(date +%Y%m%d)

# Dump database MySQL dan kompresi dengan Gzip
mysqldump -u DB_USERNAME_PRODUKSI -p DB_DATABASE_PRODUKSI \
  --single-transaction \
  --quick \
  --lock-tables=false \
  | gzip > db_backup_pre_security_$(date +%Y%m%d_%H%M%S).sql.gz

# Verifikasi integritas dan ukuran file backup
ls -lh db_backup_pre_security_*.sql.gz
gzip -t db_backup_pre_security_*.sql.gz && echo "Backup database VALID dan tidak korup"
```

### B. Backup Storage PDF Privat & File Konfigurasi
```bash
# Backup file slip gaji PDF historis dan file .env
cd /www/wwwroot/gaji_payroll

tar -czf /www/backups/payroll_$(date +%Y%m%d)/storage_private_$(date +%Y%m%d_%H%M%S).tar.gz -C storage/app private/
cp .env /www/backups/payroll_$(date +%Y%m%d)/env_backup_$(date +%Y%m%d_%H%M%S)

# Verifikasi backup storage
ls -lh /www/backups/payroll_$(date +%Y%m%d)/
```

---

## 3. Langkah-Langkah Eksekusi Deployment (Deployment Steps)

### Langkah 1: Aktifkan Mode Pemeliharaan (Maintenance Mode)
Aktifkan mode maintenance dengan token bypass rahasia agar tim deployment dapat melakukan verifikasi tanpa gangguan pengguna umum:
```bash
cd /www/wwwroot/gaji_payroll

# Ganti SECRET_TOKEN dengan token acak yang aman
/opt/cpanel/ea-php83/root/usr/bin/php artisan down \
  --secret="DeploySecPayroll2026Bypass" \
  --render="errors::503" \
  --retry=60
```
*Akses verifikasi selama maintenance dapat dibuka melalui URL: `https://rumahimpianku.it.com/DeploySecPayroll2026Bypass`*.

---

### Langkah 2: Deploy Kode Aplikasi & Hasil Build Frontend
Pilih metode yang sesuai dengan alur hosting cPanel:

#### Opsi A: Menggunakan Git Deployment (Disarankan jika SSH aktif)
```bash
cd /www/wwwroot/gaji_payroll

# Tarik perubahan terbaru dari repository cabang produksi (misal: main)
git fetch origin main
git checkout main
git pull origin main
```

#### Opsi B: Menggunakan File Manager / FTP Upload
Jika mengunggah via archive zip:
1. Ekstrak archive kode baru ke folder `/www/wwwroot/gaji_payroll/`.
2. Pastikan file build frontend lokal (`public/build/`) ikut terunggah menggantikan folder `public/build/` lama.
3. Pastikan file `.env` produksi **TIDAK TERTIMPA**.

---

### Langkah 3: Pasang Dependensi Produksi (Composer)
Jalankan instalasi dependensi tanpa paket development:
```bash
cd /www/wwwroot/gaji_payroll

/opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist
```

---

### Langkah 4: Eksekusi Migrasi Database yang Disetujui
Jalankan migrasi skema database baru (Migrations 34 dan 35):
```bash
cd /www/wwwroot/gaji_payroll

# Periksa status migrasi yang pending
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status

# Jalankan migrasi dengan flag --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force

# Sinkronisasi role & permission baru (RbacSeeder)
/opt/cpanel/ea-php83/root/usr/bin/php artisan db:seed --class=RbacSeeder --force
```

> [!NOTE]
> Migrasi 34 dan 35 bersifat aditif pada tabel `security_overtime_summaries`. Tidak ada penghapusan data lama atau perubahan tipe kolom yang memicu kegagalan constraint.

---

### Langkah 5: Rebuild Cache Produksi (Optimasi Performa)
Bersihkan cache lama dan bangun cache produksi baru:
```bash
cd /www/wwwroot/gaji_payroll

# Bersihkan cache lama
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear

# Bangun kembali cache konfigurasi, route, dan view Blade
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

---

### Langkah 6: Verifikasi Izin Akses File & Direktori (Permissions Hardening)
Pastikan permission folder storage dan cache dapat ditulis oleh web server (user cPanel):
```bash
cd /www/wwwroot/gaji_payroll

# Set permission dasar
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Berikan izin tulis untuk storage dan bootstrap cache
chmod -R 775 storage bootstrap/cache

# Proteksi file konfigurasi .env agar hanya terbaca oleh sistem
chmod 600 .env
```

---

### Langkah 7: Konfigurasi Cron Job & Task Scheduler cPanel
Buka menu **Cron Jobs** pada antarmuka cPanel dan pastikan entri scheduler aktif:

#### Entri 1: Laravel Task Scheduler (Eksekusi setiap menit)
- **Schedule**: `* * * * *` (Once Per Minute)
- **Command**:
  ```bash
  /opt/cpanel/ea-php83/root/usr/bin/php /www/wwwroot/gaji_payroll/artisan schedule:run >> /dev/null 2>&1
  ```

#### Entri 2: Dedicated Delivery Queue Runner (Opsional jika ingin dedicated log)
- **Schedule**: `* * * * *` (Once Per Minute)
- **Command**:
  ```bash
  /opt/cpanel/ea-php83/root/usr/bin/php /www/wwwroot/gaji_payroll/artisan payroll:process-queue --limit=30 --timeout=50 >> /www/wwwroot/gaji_payroll/storage/logs/cron-queue.log 2>&1
  ```

---

## 4. Pengujian Asap Pasca-Deployment (Post-Deployment Smoke Test)

Gunakan URL bypass (`https://rumahimpianku.it.com/DeploySecPayroll2026Bypass`) untuk menjalankan verifikasi berikut sebelum menonaktifkan maintenance mode:

1. **Akses Halaman Login**: Buka halaman login, pastikan tampilan CSS/JS Vite termuat sempurna.
2. **Login Akun Personalia**: Masuk menggunakan akun Personalia/Admin terverifikasi.
3. **Navigasi Modul Security**: Buka menu `/payroll-security` dan `/payroll-security/reports`.
4. **Verifikasi Kalkulasi Non-Destruktif**: Buka form attendance salah satu personel Security, klik preview, dan pastikan nilai jam/lembur terhitung sesuai formula tanpa error.
5. **Periksa Error Log**:
   ```bash
   tail -n 50 /www/wwwroot/gaji_payroll/storage/logs/laravel.log
   ```
   *Pastikan tidak terdapat catatan Exception atau Fatal Error baru*.

---

## 5. Nonaktifkan Mode Pemeliharaan (Go-Live)

Setelah seluruh smoke test berhasil tanpa kendala:
```bash
cd /www/wwwroot/gaji_payroll

# Kembalikan aplikasi ke status aktif untuk publik
/opt/cpanel/ea-php83/root/usr/bin/php artisan up
```

---

## 6. Pemantauan Pasca Go-Live (Post-Go-Live Monitoring)

1. Pantau traffic dan respons server selama 60 menit pertama pasca deployment.
2. Periksa apakah ada lonjakan failed jobs:
   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:failed
   ```
3. Konfirmasi kepada perwakilan tim Personalia bahwa modul dapat diakses dan digunakan dengan lancar.

