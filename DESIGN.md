# DESIGN.md --- Desain Teknis Aplikasi Kirim Slip Gaji

**Versi:** 1.0\
**Status:** Blueprint implementasi\
**Stack:** Laravel 13, React, Inertia.js, Tailwind CSS v4, MySQL 8\
**Deployment:** Shared hosting/cPanel

## 1. Prinsip Arsitektur

-   Modular monolith; satu aplikasi Laravel untuk UI, domain, dan
    integrasi.
-   React/Inertia hanya menangani presentasi dan interaksi; aturan gaji
    berada di backend.
-   Service layer untuk perhitungan, persetujuan, finalisasi, PDF, dan
    delivery.
-   Private storage untuk seluruh PDF slip.
-   Queue berbasis database agar tidak memerlukan Redis pada shared
    hosting.
-   Adapter provider WhatsApp agar gateway dapat diganti.
-   Semua transisi status melalui domain service dan transaksi database.
-   RBAC server-side; setiap aksi sensitif wajib diotorisasi.

## 2. Arsitektur Lapisan

### Presentation

-   Inertia pages dan React components.
-   Form menggunakan validasi server-side melalui Laravel Form Requests.
-   Props Inertia hanya berisi data yang diperlukan untuk halaman
    tersebut.
-   Jangan pernah mengirim kredensial provider, token, atau konfigurasi
    rahasia ke frontend.

### HTTP/Application

-   Controllers tipis: validasi melalui Form Request, otorisasi melalui
    Policy, lalu panggil service.
-   Form Requests: validasi bentuk input dan nilai yang diperbolehkan.
-   Policies/Gates: otorisasi setiap record dan aksi.
-   Middleware: auth, active user, permission, CSRF, rate limit.

### Domain services

-   `PayrollCalculationService`: hitung earning, deduction, net.
-   `PayrollSubmissionService`: ajukan periode/slip untuk review.
-   `PayrollApprovalService`: approve atau request changes.
-   `PayslipFinalizationService`: cek approval, snapshot, nomor slip,
    finalisasi.
-   `PayslipPdfService`: render PDF dari snapshot dan simpan privat.
-   `DeliveryOrchestrator`: buat delivery record dan enqueue job.
-   `DeliveryStatusService`: normalisasi status provider dan callback.
-   `AuditLogService`: tulis audit event tanpa rahasia.

### Infrastructure

-   MySQL 8.
-   Laravel database queue.
-   Private local disk di luar web root jika cPanel mengizinkan.
-   SMTP untuk email.
-   HTTP client Laravel untuk gateway WhatsApp.
-   Cron cPanel untuk `schedule:run` dan pemrosesan queue batch.

## 3. Struktur Direktori yang Disarankan

``` text
app/
├── Actions/
├── Enums/
│   ├── PayrollPeriodStatus.php
│   ├── PayslipStatus.php
│   └── DeliveryStatus.php
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
│   ├── SendPayslipDelivery.php
│   └── ProcessDeliveryStatusCallback.php
├── Mail/
│   └── PayslipMail.php
├── Models/
├── Notifications/
├── Policies/
├── Providers/
├── Services/
│   ├── PayrollCalculationService.php
│   ├── PayrollSubmissionService.php
│   ├── PayrollApprovalService.php
│   ├── PayslipFinalizationService.php
│   ├── PayslipPdfService.php
│   ├── DeliveryOrchestrator.php
│   ├── DeliveryStatusService.php
│   └── AuditLogService.php
└── Support/
    └── PhoneNumberNormalizer.php

resources/js/
├── components/
├── layouts/
└── pages/
    ├── Dashboard/
    ├── Employees/
    ├── PayrollPeriods/
    ├── Payslips/
    ├── Approvals/
    ├── Deliveries/
    ├── Reports/
    ├── Users/
    ├── Roles/
    └── Settings/
```

## 4. Database Design

### 4.1 `users`

-   `id` BIGINT PK
-   `name` VARCHAR(150)
-   `email` VARCHAR(190) UNIQUE
-   `password` VARCHAR(255)
-   `is_active` BOOLEAN DEFAULT TRUE
-   `last_login_at` TIMESTAMP NULL
-   Laravel timestamps

### 4.2 RBAC

Pilihan yang disarankan: gunakan paket RBAC Laravel yang terpelihara,
atau tabel internal berikut: - `roles`: `id`, `name` UNIQUE, `label`,
timestamps - `permissions`: `id`, `name` UNIQUE, `label`, timestamps -
`role_user`: `user_id`, `role_id`, unique pair - `permission_role`:
`permission_id`, `role_id`, unique pair

Jangan membuat role sebagai satu kolom enum pada `users` jika
role/permission perlu dikelola melalui UI.

### 4.3 `employees`

-   `id` BIGINT PK
-   `employee_number` VARCHAR(50) UNIQUE
-   `full_name` VARCHAR(190)
-   `department` VARCHAR(150) NULL
-   `position` VARCHAR(150) NULL
-   `email` VARCHAR(190) NULL
-   `phone` VARCHAR(30) NULL
-   `hire_date` DATE NULL
-   `employment_status` VARCHAR(30) DEFAULT `active`
-   timestamps
-   index `employment_status`, `department`

### 4.4 `payroll_periods`

-   `id` BIGINT PK
-   `period_code` VARCHAR(20) UNIQUE
-   `start_date` DATE
-   `end_date` DATE
-   `status` VARCHAR(30) DEFAULT `draft`
-   `created_by` FK users
-   `submitted_by` FK users NULL
-   `submitted_at` TIMESTAMP NULL
-   `approved_by` FK users NULL
-   `approved_at` TIMESTAMP NULL
-   `approval_comment` TEXT NULL
-   `finalized_by` FK users NULL
-   `finalized_at` TIMESTAMP NULL
-   `closed_at` TIMESTAMP NULL
-   timestamps
-   index `status`, `start_date`

### 4.5 `pay_components`

-   `id` BIGINT PK
-   `code` VARCHAR(50) UNIQUE
-   `name` VARCHAR(150)
-   `type` VARCHAR(20) (`earning` / `deduction`)
-   `is_active` BOOLEAN DEFAULT TRUE
-   `sort_order` SMALLINT DEFAULT 0
-   `created_by` FK users NULL
-   timestamps

Komponen yang sudah pernah dipakai dinonaktifkan, tidak dihapus fisik.

### 4.6 `payslips`

-   `id` BIGINT PK
-   `payroll_period_id` FK payroll_periods
-   `employee_id` FK employees
-   `slip_number` VARCHAR(80) UNIQUE
-   snapshot: `employee_number_snapshot`, `employee_name_snapshot`,
    `department_snapshot`, `position_snapshot`, `email_snapshot`,
    `phone_snapshot`
-   `basic_salary` DECIMAL(15,2) DEFAULT 0
-   `total_earnings` DECIMAL(15,2) DEFAULT 0
-   `total_deductions` DECIMAL(15,2) DEFAULT 0
-   `net_salary` DECIMAL(15,2) DEFAULT 0
-   `currency` CHAR(3) DEFAULT `IDR`
-   `pdf_path` VARCHAR(500) NULL
-   `pdf_sha256` CHAR(64) NULL
-   `pdf_generated_at` TIMESTAMP NULL
-   `status` VARCHAR(30) DEFAULT `draft`
-   `created_by` FK users
-   `submitted_by` FK users NULL
-   `submitted_at` TIMESTAMP NULL
-   `approved_by` FK users NULL
-   `approved_at` TIMESTAMP NULL
-   `approval_comment` TEXT NULL
-   `finalized_by` FK users NULL
-   `finalized_at` TIMESTAMP NULL
-   `voided_by` FK users NULL
-   `voided_at` TIMESTAMP NULL
-   `void_reason` TEXT NULL
-   timestamps
-   UNIQUE (`payroll_period_id`, `employee_id`)
-   index (`status`, `payroll_period_id`)

### 4.7 `payslip_items`

-   `id` BIGINT PK
-   `payslip_id` FK payslips
-   `pay_component_id` FK pay_components NULL
-   `item_code_snapshot` VARCHAR(50)
-   `description_snapshot` VARCHAR(190)
-   `item_type` VARCHAR(20)
-   `amount` DECIMAL(15,2)
-   `sort_order` SMALLINT DEFAULT 0
-   timestamps
-   index (`payslip_id`, `sort_order`)

Snapshot komponen diperlukan agar perubahan nama master komponen tidak
mengubah slip lama.

### 4.8 `payslip_deliveries`

-   `id` BIGINT PK
-   `payslip_id` FK payslips
-   `channel` VARCHAR(20) (`email` / `whatsapp`)
-   `recipient` VARCHAR(190)
-   `status` VARCHAR(30) DEFAULT `queued`
-   `provider_message_id` VARCHAR(190) NULL
-   `idempotency_key` VARCHAR(100) UNIQUE
-   `attempt_count` UNSIGNED SMALLINT DEFAULT 0
-   `last_attempt_at` TIMESTAMP NULL
-   `sent_at` TIMESTAMP NULL
-   `delivered_at` TIMESTAMP NULL
-   `last_error_code` VARCHAR(100) NULL
-   `last_error_summary` TEXT NULL
-   timestamps
-   UNIQUE (`payslip_id`, `channel`)
-   index (`status`, `channel`)

Jika kebutuhan bisnis mengharuskan pengiriman ulang sebagai pengiriman
baru, desain delivery perlu memakai `delivery_batch_id`/`generation` dan
aturan unik yang sesuai. Jangan menghapus histori pengiriman sebelumnya.

### 4.9 `delivery_attempts`

-   `id` BIGINT PK
-   `payslip_delivery_id` FK payslip_deliveries
-   `attempt_number` UNSIGNED SMALLINT
-   `status` VARCHAR(30)
-   `provider_message_id` VARCHAR(190) NULL
-   `error_code` VARCHAR(100) NULL
-   `error_summary` TEXT NULL
-   `started_at` TIMESTAMP NULL
-   `finished_at` TIMESTAMP NULL
-   timestamps
-   UNIQUE (`payslip_delivery_id`, `attempt_number`)

### 4.10 `payroll_approval_events`

-   `id` BIGINT PK
-   `payroll_period_id` FK payroll_periods
-   `payslip_id` FK payslips NULL
-   `actor_user_id` FK users
-   `decision` VARCHAR(30) (`submitted`, `approved`,
    `changes_requested`, `reopened`)
-   `comment` TEXT NULL
-   `created_at` TIMESTAMP
-   index (`payroll_period_id`, `created_at`)

Tabel ini menyimpan histori keputusan agar kolom status terkini tidak
menjadi satu-satunya sumber riwayat.

### 4.11 `audit_logs`

-   `id` BIGINT PK
-   `user_id` FK users NULL
-   `action` VARCHAR(100)
-   `subject_type` VARCHAR(150)
-   `subject_id` VARCHAR(100)
-   `changes_json` JSON NULL
-   `ip_address` VARCHAR(45) NULL
-   `user_agent` VARCHAR(500) NULL
-   `created_at` TIMESTAMP
-   index (`subject_type`, `subject_id`), (`user_id`, `created_at`)

### 4.12 `company_settings` dan `integration_settings`

Untuk pengaturan: - `company_settings`: nama perusahaan, alamat, logo
path, identitas pajak opsional, footer slip. - `integration_settings`:
provider, endpoint, sender identifier, konfigurasi non-rahasia, dan
secret terenkripsi. - Jangan pernah mengembalikan secret mentah ke
frontend setelah disimpan. UI hanya menampilkan status terkonfigurasi
dan opsi mengganti secret.

### 4.13 Tabel bawaan Laravel

-   `sessions` bila session driver database dipakai.
-   `jobs`, `job_batches`, `failed_jobs` untuk database queue.
-   `cache`, `cache_locks` bila cache driver database digunakan.
-   `password_reset_tokens` untuk reset password.

## 5. Relasi Eloquent

-   `Employee hasMany Payslip`
-   `PayrollPeriod hasMany Payslip`
-   `Payslip belongsTo Employee`
-   `Payslip belongsTo PayrollPeriod`
-   `Payslip hasMany PayslipItem`
-   `Payslip hasMany PayslipDelivery`
-   `PayslipDelivery hasMany DeliveryAttempt`
-   `PayrollPeriod hasMany PayrollApprovalEvent`
-   `User belongsToMany Role`
-   `Role belongsToMany Permission`

Gunakan FK dan unique constraints di database sebagai pertahanan
terakhir, bukan hanya validasi frontend.

## 6. Perhitungan Gaji

Gunakan decimal/string decimal yang didukung database; jangan memakai
floating-point untuk uang.

Rumus: - `total_earnings = SUM(items where item_type = earning)` -
`total_deductions = SUM(items where item_type = deduction)` -
`net_salary = total_earnings - total_deductions`

Validasi: - Nominal item tidak negatif. - `item_type` harus sama dengan
jenis komponen master saat item dibuat. - Basic salary dapat menjadi
item earning atau field ringkasan, tetapi jangan menghitungnya dua
kali. - Semua total dihitung ulang di backend. - Pengubahan item memicu
perhitungan ulang dan menghapus approval sebelumnya jika slip sudah
diajukan. - Tentukan secara eksplisit apakah gaji bersih negatif ditolak
atau memerlukan override beralasan.

## 7. Approval dan Finalisasi

Gunakan service dengan transaksi database dan row locking bila perlu: 1.
Validasi status periode/slip. 2. Validasi semua slip yang wajib
diproses. 3. Saat submit, catat aktor/waktu dan event. 4. Saat approver
menyetujui, pastikan aktor memiliki permission dan bukan pembuat jika
segregation-of-duties diwajibkan. 5. Jika ada perubahan setelah submit,
ubah status ke draft/changes_requested dan minta approval ulang. 6.
Finalisasi hanya jika status approved. 7. Buat snapshot penerima, nomor
slip, PDF, dan hash. 8. Catat audit event. 9. Pengiriman hanya tersedia
untuk slip finalized.

Jika proses generate PDF gagal, jangan tandai slip final sebagai siap
dikirim. Gunakan status/error yang bisa dipulihkan dan operasi
idempotent.

## 8. PDF dan Private Storage

-   Disk `private` diarahkan ke folder di luar `public_html`, jika
    layout cPanel memungkinkan.
-   Jika tidak memungkinkan, gunakan folder yang diblokir web server dan
    uji akses langsung; solusi di luar web root tetap lebih disarankan.
-   File path dibuat oleh server, tidak menerima path dari pengguna.
-   Endpoint download memeriksa autentikasi, permission, status slip,
    dan audit log.
-   PDF dibuat dari snapshot final.
-   Hitung SHA-256 setelah file selesai dibuat.
-   Jangan memasukkan password PDF sebagai satu-satunya kontrol
    keamanan.

## 9. Integrasi Email dan WhatsApp

### Kontrak adapter

Buat interface konseptual `WhatsAppProviderInterface` dengan operasi: -
`sendDocument(recipient, filePath, caption, idempotencyKey)` -
`getMessageStatus(providerMessageId)` jika didukung -
`validateConfiguration()`

Normalisasi hasil ke status internal dan simpan kode error yang aman.
Endpoint callback harus memverifikasi signature/token provider,
membatasi laju, dan menghindari memproses callback yang sama dua kali.

### Email

-   Gunakan Laravel Mail dan SMTP.
-   Lampirkan PDF final.
-   Kirim satu email per karyawan.
-   Jika lampiran terlalu besar atau provider menolak, tandai gagal dan
    tampilkan alasan yang aman.
-   Hindari logging isi email atau lampiran.

### WhatsApp

-   Gunakan gateway pihak ketiga yang secara kontrak mendukung
    pengiriman dokumen.
-   Periksa status layanan, biaya, batas laju, template, persetujuan
    penerima, dan ketentuan penggunaan.
-   Simpan secret terenkripsi.
-   Jangan menaruh PDF sebagai base64 dalam database.
-   Hindari URL file publik; jika gateway memerlukan URL, gunakan URL
    signed berumur pendek atau mekanisme upload dokumen provider.

## 10. Queue dan cPanel

Gunakan database queue untuk menghindari dependensi Redis.

Strategi: - Job per slip dan kanal. - Atur `tries`, `timeout`, dan
backoff yang konservatif. - Proses dalam batch kecil agar tidak
melampaui batas waktu hosting. - Cegah dua cron worker memproses batch
yang sama jika memungkinkan. - Sediakan command artisan khusus, misalnya
`payslips:process-queue --limit=20`, yang menjalankan proses terbatas
dan keluar. - Alternatifnya, jalankan
`queue:work --stop-when-empty --max-time=50 --tries=3` melalui cron jika
didukung hosting. - Jalankan scheduler Laravel melalui cron
`schedule:run` setiap menit jika diperlukan. - Pantau `failed_jobs` dan
sediakan UI/log untuk retry terkontrol. - Jangan menjalankan pengiriman
massal sinkron dalam request web.

Contoh cron hanya setelah path PHP dan proyek dikonfirmasi dengan
penyedia hosting:

``` bash
* * * * * cd /home/USER/path-to-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Path PHP, batas waktu, dan format cron berbeda antarhosting; sesuaikan
berdasarkan panel/provider. Jangan menyalin contoh ini tanpa verifikasi.

## 11. Halaman Inertia

-   `Dashboard/Index`
-   `Employees/Index`, `Create`, `Edit`, `Show`
-   `PayrollPeriods/Index`, `Create`, `Show`
-   `Payslips/Create`, `Edit`, `Show`, `Preview`
-   `Approvals/Index`, `Show`
-   `Deliveries/Index`, `Show`
-   `Reports/Index`
-   `Users/Index`, `Create`, `Edit`
-   `Roles/Index`, `Edit`
-   `Settings/Company`, `Settings/Email`, `Settings/WhatsApp`,
    `Settings/PayslipTemplate`

Tampilkan nominal gaji hanya kepada role yang mempunyai izin melihat
data finansial.

## 12. Keamanan

-   HTTPS wajib.
-   Password di-hash oleh Laravel.
-   CSRF protection dan rate limiting.
-   Validasi dan otorisasi di backend.
-   Private storage dan kontrol download.
-   Enkripsi secret integration dengan Laravel encrypted cast atau
    mekanisme aman setara.
-   Jangan pernah log token, password, isi slip, atau file.
-   Batasi akses database dan kredensial hosting.
-   Audit tindakan sensitif.
-   Backup terenkripsi dan uji restore.
-   Tetapkan retensi serta prosedur insiden.
-   Gunakan data sintetis untuk development/staging.
-   Jangan gunakan data gaji produksi untuk screenshot/demo.

## 13. Testing

-   Unit test perhitungan earning/deduction/net.
-   Feature test RBAC untuk setiap route sensitif.
-   Test approval, rejection, resubmission, dan invalidasi approval.
-   Test unique constraint satu slip per karyawan/periode.
-   Test PDF privat dan larangan akses tanpa izin.
-   Test delivery state, retry, idempotency, callback duplikat, dan
    provider failure.
-   Test cron/queue pada batas waktu.
-   Test snapshot: perubahan master karyawan tidak mengubah slip final
    lama.
-   Test akses lintas pengguna/role.
