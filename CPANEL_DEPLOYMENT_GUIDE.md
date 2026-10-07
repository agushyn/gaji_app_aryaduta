# Panduan Deployment cPanel dan Shared Hosting: Kirim Slip Gaji Personalia

Panduan teknis komprehensif untuk melakukan deployment aplikasi **Kirim Slip Gaji Personalia** pada lingkungan shared hosting cPanel berbasis Apache/Nginx, PHP 8.2+, dan MySQL 8.0.

---

## 1. Persyaratan Server dan Ekstensi PHP

Sebelum melakukan deployment, pastikan hosting cPanel memenuhi kriteria berikut:
- **PHP Version**: PHP 8.2 atau 8.3 (dapat diatur melalui cPanel *Select PHP Version* atau *MultiPHP Manager*).
- **Ekstensi PHP Wajib**:
  - `bcmath` (kalkulasi presisi desimal gaji)
  - `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
  - `gd` (pemrosesan gambar/logo kop surat PDF)
- **Database**: MySQL 8.0+ atau MariaDB 10.5+ dengan dukungan InnoDB dan `utf8mb4_unicode_ci`.
- **Fitur Hosting**:
  - Akses SSH / Terminal cPanel (disarankan) atau File Manager + Git Version Control.
  - Akses Cron Jobs (wajib untuk queue database dan scheduled tasks).
  - SSL Certificate aktif (Let's Encrypt / cPanel AutoSSL) untuk enkripsi HTTPS.

---

## 2. Struktur Direktori Aman (Isolasi Public Document Root)

Untuk keamanan maksimal di shared hosting, **JANGAN** menempatkan seluruh root Laravel di dalam folder `public_html`. Pisahkan kode inti aplikasi di luar direktori publik web server.

### Rekomendasi Struktur Direktori:
```text
/home/username/
├── gaji_app/                     <-- Core Laravel Application (Di luar web root)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── artisan
│
└── public_html/                  <-- Web Document Root Publik
    ├── build/                    <-- Hasil kompilasi Vite (assets CSS/JS)
    ├── .htaccess                 <-- Konfigurasi Apache URL rewriting
    ├── index.php                 <-- Front controller yang disesuaikan
    ├── favicon.ico
    └── robots.txt
```

### Langkah Konfigurasi:
1. Upload folder proyek Laravel ke direktori `/home/username/gaji_app/` (abaikan folder `node_modules` dan `.git`).
2. Pindahkan seluruh isi dari folder `/home/username/gaji_app/public/` ke direktori `/home/username/public_html/`.
3. Buka dan edit file `/home/username/public_html/index.php`:
   ```php
   <?php

   use Illuminate\Http\Request;

   define('LARAVEL_START', microtime(true));

   // Maintenance mode check
   if (file_exists($maintenance = __DIR__.'/../gaji_app/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   // Register composer autoloader
   require __DIR__.'/../gaji_app/vendor/autoload.php';

   // Bootstrap Laravel application
   $app = require_once __DIR__.'/../gaji_app/bootstrap/app.php';

   $handle = $app->make(Illuminate\Contracts\Http\Kernel::class);

   $response = $handle->handle(
       $request = Request::capture()
   )->send();

   $handle->terminate($request, $response);
   ```
4. Pastikan file `/home/username/public_html/.htaccess` memiliki proteksi rewrite standar Laravel:
   ```apache
   <IfModule mod_rewrite.c>
       <IfModule mod_negotiation.c>
           Options -MultiViews -Indexes
       </IfModule>

       RewriteEngine On

       # Handle Authorization Header
       RewriteCond %{HTTP:Authorization} .
       RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

       # Redirect Trailing Slashes If Not A Folder...
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteCond %{REQUEST_URI} (.+)/$
       RewriteRule ^ %1 [L,R=301]

       # Send Requests To Front Controller...
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteRule ^ index.php [L]
   </IfModule>
   ```

---

## 3. Konfigurasi Environment (.env.production)

Buat file `.env` di dalam `/home/username/gaji_app/.env` dan lakukan penyesuaian:

```ini
APP_NAME="Kirim Slip Gaji Personalia"
APP_ENV=production
APP_KEY=base64:GENERATE_VIA_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://gaji.perusahaan.com

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

# Database MySQL cPanel
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=username_gajiapp
DB_USERNAME=username_dbuser
DB_PASSWORD="PasswordKuatMySQL"

# Queue Driver Database (Wajib untuk background job cPanel)
QUEUE_CONNECTION=database

# Session dan Cache
BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=private
QUEUE_FAILED_DRIVER=database-uuids
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Storage Disk Privat untuk PDF Slip Gaji
PAYSLIP_STORAGE_DISK=private

# Mail SMTP Configuration
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mail.perusahaan.com
MAIL_PORT=465
MAIL_USERNAME=noreply-slip@perusahaan.com
MAIL_PASSWORD="PasswordSmtpKuat"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply-slip@perusahaan.com"
MAIL_FROM_NAME="Personalia Perusahaan"

# WhatsApp Gateway API Configuration
WHATSAPP_GATEWAY_URL="https://api.fonnte.com/send"
WHATSAPP_API_TOKEN="SecretTokenFonnteAtauWablas"
WHATSAPP_SENDER_NUMBER="628123456789"
```

---

## 4. Izin Akses File dan Direktori (File Permissions)

Jalankan perintah izin file berikut melalui Terminal cPanel atau SSH:
```bash
cd /home/username/gaji_app

# Set izin dasar folder dan file
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Berikan izin tulis untuk storage dan cache
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```
Pastikan file storage privat (`/home/username/gaji_app/storage/app/private/`) tidak dapat diakses langsung via URL browser. Karena letaknya di luar `public_html`, dokumen PDF slip gaji terlindungi 100% dari unduhan publik tanpa otentikasi.

---

## 5. Migrasi Database dan Seeding

Jalankan migrasi di environment production via Terminal cPanel:
```bash
cd /home/username/gaji_app

# Migrasi tabel dan RBAC
php artisan migrate --force
php artisan db:seed --class=RbacSeeder --force

# Optimasi performa Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika Terminal SSH tidak tersedia di cPanel, buat route migrasi sementara yang diproteksi token rahasia di `routes/console.php` atau jalankan via cron satu kali lalu hapus kembali.

---

## 6. Konfigurasi Cron Jobs (Queue Runner dan Scheduler)

Shared hosting cPanel tidak mengizinkan background process permanen seperti `supervisord` atau `php artisan queue:work` tanpa henti (proses akan dibunuh oleh resource governor LVE / CloudLinux).

Oleh karena itu, gunakan command bawaan sistem yang telah dioptimasi dengan atomic cache lock:
`payroll:process-queue --limit=30 --timeout=60`

### A. Mencari Path PHP CLI di cPanel
Jalankan di terminal cPanel:
```bash
which php
```
Contoh path umum di cPanel CloudLinux:
- `/usr/local/bin/php`
- `/usr/bin/php`
- `/opt/cpanel/ea-php82/root/usr/bin/php`

### B. Menambahkan Cron di cPanel (Menu: Cron Jobs)

Tambahkan dua entri cron job berikut:

#### 1. Laravel Task Scheduler (Menjalankan queue internal dan cleanup)
- **Interval**: `* * * * *` (Setiap Menit)
- **Command**:
  ```bash
  /usr/local/bin/php /home/username/gaji_app/artisan schedule:run >> /dev/null 2>&1
  ```

#### 2. Dedicated Delivery Queue Runner (Memproses antrean batch pengiriman email dan WA)
- **Interval**: `* * * * *` (Setiap Menit)
- **Command**:
  ```bash
  /usr/local/bin/php /home/username/gaji_app/artisan payroll:process-queue --limit=30 --timeout=50 >> /home/username/gaji_app/storage/logs/cron-queue.log 2>&1
  ```

### Cara Kerja Safety Runner:
- Command mengambil atomic lock `payroll_queue_runner_lock` selama maksimal 60 detik.
- Jika cron sebelumnya masih berjalan, cron baru akan langsung berhenti (`exit 0`), mencegah penumpukan proses zombie.
- Jika antrean kosong, proses akan langsung keluar (`--stop-when-empty`) untuk menghemat CPU dan RAM hosting.

---

## 7. Prosedur Backup dan Restore Data Sensitif

### A. Prosedur Backup Rutin (Mingguan / Bulanan)
1. **Backup Database**:
   ```bash
   mysqldump -u username_dbuser -p username_gajiapp | gzip > /home/username/backups/db_backup_$(date +\%F).sql.gz
   ```
2. **Backup Storage PDF Privat**:
   ```bash
   tar -czf /home/username/backups/pdf_storage_$(date +\%F).tar.gz -C /home/username/gaji_app/storage/app/ private/
   ```
3. Unduh file backup ke cold storage terenkripsi lokal dan jangan simpan selamanya di server hosting.

### B. Prosedur Restore Data
1. **Restore Database**:
   ```bash
   gunzip < /home/username/backups/db_backup_2026-10-01.sql.gz | mysql -u username_dbuser -p username_gajiapp
   ```
2. **Restore Storage PDF**:
   ```bash
   tar -xzf /home/username/backups/pdf_storage_2026-10-01.tar.gz -C /home/username/gaji_app/storage/app/
   ```

---

## 8. Hardening dan Keamanan Tambahan

1. **Nonaktifkan APP_DEBUG**: Selalu pastikan `APP_DEBUG=false` di `.env`.
2. **Kunci Enkripsi Cookie**: Pastikan `SESSION_SECURE_COOKIE=true` aktif saat domain menggunakan SSL HTTPS.
3. **Audit Log Retention**: Audit log dan delivery attempts disimpan di database; lakukan arsip log berkala jika kapasitas hosting mendekati batas kuota inode.
4. **Proteksi File Sensitif**: Pastikan file `.env` memiliki permission `600` dan tidak terbaca oleh modul web.
