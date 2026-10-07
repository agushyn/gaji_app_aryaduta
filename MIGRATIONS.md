# Rancangan Migration Laravel --- Kirim Slip Gaji

Dokumen ini adalah urutan migration dan kontrak skema. Implementasikan
sebagai migration Laravel, lalu sesuaikan tipe kolom dan sintaks dengan
versi Laravel 13 serta MySQL 8 yang digunakan.

## Urutan migration

1.  `create_users_table` (atau gunakan migration Laravel default, lalu
    tambahkan `is_active`, `last_login_at`).
2.  `create_roles_table`
3.  `create_permissions_table`
4.  `create_role_user_table`
5.  `create_permission_role_table`
6.  `create_employees_table`
7.  `create_payroll_periods_table`
8.  `create_pay_components_table`
9.  `create_payslips_table`
10. `create_payslip_items_table`
11. `create_payslip_deliveries_table`
12. `create_delivery_attempts_table`
13. `create_payroll_approval_events_table`
14. `create_audit_logs_table`
15. `create_company_settings_table`
16. `create_integration_settings_table`
17. Pastikan migration `sessions`, `jobs`, `job_batches`, `failed_jobs`,
    `cache`, `cache_locks`, dan `password_reset_tokens` tersedia jika
    driver terkait digunakan.

## Kontrak kolom dan constraint

### `employees`

-   `id`: big integer primary key
-   `employee_number`: string(50), unique
-   `full_name`: string(190)
-   `department`, `position`: nullable string(150)
-   `email`: nullable string(190)
-   `phone`: nullable string(30)
-   `hire_date`: nullable date
-   `employment_status`: string(30), default `active`
-   timestamps
-   index `employment_status`, `department`

### `payroll_periods`

-   `id`
-   `period_code`: string(20), unique
-   `start_date`, `end_date`: date
-   `status`: string(30), default `draft`
-   `created_by`: FK users, restrict delete
-   `submitted_by`, `approved_by`, `finalized_by`: nullable FK users,
    nullOnDelete atau restrict sesuai kebijakan audit
-   `submitted_at`, `approved_at`, `finalized_at`, `closed_at`: nullable
    timestamps
-   `approval_comment`: nullable text
-   timestamps
-   index `status`, `start_date`

### `pay_components`

-   `id`
-   `code`: string(50), unique
-   `name`: string(150)
-   `type`: string(20) dengan validasi aplikasi `earning` atau
    `deduction`
-   `is_active`: boolean default true
-   `sort_order`: small integer default 0
-   `created_by`: nullable FK users
-   timestamps

### `payslips`

-   `id`
-   `payroll_period_id`: FK payroll_periods, restrict delete
-   `employee_id`: FK employees, restrict delete
-   `slip_number`: string(80), unique
-   `employee_number_snapshot`: string(50)
-   `employee_name_snapshot`: string(190)
-   `department_snapshot`, `position_snapshot`: nullable string(150)
-   `email_snapshot`: nullable string(190)
-   `phone_snapshot`: nullable string(30)
-   `basic_salary`, `total_earnings`, `total_deductions`, `net_salary`:
    decimal(15,2), default 0
-   `currency`: char(3), default `IDR`
-   `pdf_path`: nullable string(500)
-   `pdf_sha256`: nullable char(64)
-   `pdf_generated_at`: nullable timestamp
-   `status`: string(30), default `draft`
-   `created_by`: FK users, restrict delete
-   `submitted_by`, `approved_by`, `finalized_by`, `voided_by`: nullable
    FK users
-   `submitted_at`, `approved_at`, `finalized_at`, `voided_at`: nullable
    timestamps
-   `approval_comment`, `void_reason`: nullable text
-   timestamps
-   unique (`payroll_period_id`, `employee_id`)
-   index (`status`, `payroll_period_id`)

### `payslip_items`

-   `id`
-   `payslip_id`: FK payslips, cascade delete hanya untuk draft; untuk
    sistem yang mempertahankan audit, larang penghapusan item final
    melalui service.
-   `pay_component_id`: nullable FK pay_components, restrict delete
-   `item_code_snapshot`: string(50)
-   `description_snapshot`: string(190)
-   `item_type`: string(20)
-   `amount`: decimal(15,2)
-   `sort_order`: small integer default 0
-   timestamps
-   index (`payslip_id`, `sort_order`)

Catatan: foreign key tidak dapat menerapkan aturan "cascade hanya jika
parent draft" secara langsung. Terapkan aturan tersebut di
service/policy dan pertimbangkan `restrictOnDelete` untuk menjaga
integritas.

### `payslip_deliveries`

-   `id`
-   `payslip_id`: FK payslips, restrict delete
-   `channel`: string(20)
-   `recipient`: string(190)
-   `status`: string(30), default `queued`
-   `provider_message_id`: nullable string(190)
-   `idempotency_key`: string(100), unique
-   `attempt_count`: unsigned small integer, default 0
-   `last_attempt_at`, `sent_at`, `delivered_at`: nullable timestamps
-   `last_error_code`: nullable string(100)
-   `last_error_summary`: nullable text
-   timestamps
-   unique (`payslip_id`, `channel`)
-   index (`status`, `channel`)

### `delivery_attempts`

-   `id`
-   `payslip_delivery_id`: FK payslip_deliveries, restrict delete
-   `attempt_number`: unsigned small integer
-   `status`: string(30)
-   `provider_message_id`: nullable string(190)
-   `error_code`: nullable string(100)
-   `error_summary`: nullable text
-   `started_at`, `finished_at`: nullable timestamps
-   timestamps
-   unique (`payslip_delivery_id`, `attempt_number`)

### `payroll_approval_events`

-   `id`
-   `payroll_period_id`: FK payroll_periods, restrict delete
-   `payslip_id`: nullable FK payslips, restrict delete
-   `actor_user_id`: FK users, restrict delete
-   `decision`: string(30)
-   `comment`: nullable text
-   `created_at`: timestamp
-   index (`payroll_period_id`, `created_at`)

### `audit_logs`

-   `id`
-   `user_id`: nullable FK users
-   `action`: string(100)
-   `subject_type`: string(150)
-   `subject_id`: string(100)
-   `changes_json`: nullable JSON
-   `ip_address`: nullable string(45)
-   `user_agent`: nullable string(500)
-   `created_at`: timestamp
-   index (`subject_type`, `subject_id`)
-   index (`user_id`, `created_at`)

### `company_settings`

-   `id`
-   `key`: string(100), unique
-   `value`: nullable text
-   timestamps

### `integration_settings`

-   `id`
-   `provider`: string(100)
-   `is_active`: boolean default false
-   `endpoint`: nullable string(500)
-   `sender_identifier`: nullable string(190)
-   `config_json`: nullable JSON untuk konfigurasi non-rahasia
-   `secret_encrypted`: nullable longText untuk secret yang telah
    dienkripsi oleh aplikasi
-   `last_tested_at`: nullable timestamp
-   `last_test_status`: nullable string(30)
-   timestamps

Batasi siapa yang bisa mengubah integrasi. Jangan pernah menampilkan
`secret_encrypted` atau nilai secret hasil dekripsi ke frontend.

## Migration rules

-   Gunakan `decimal(15,2)` untuk nominal IDR.
-   Gunakan foreign key dan unique constraints di database.
-   Pastikan panjang indeks cocok dengan konfigurasi MySQL hosting.
-   Hindari enum database untuk status yang mungkin berkembang; gunakan
    string dengan PHP enum dan validasi aplikasi.
-   Gunakan `restrictOnDelete` untuk data finansial historis.
    Penonaktifan lebih aman daripada penghapusan.
-   Tambahkan index berdasarkan query aktual setelah profiling.
-   Jangan mengedit migration yang sudah dijalankan di produksi; buat
    migration baru.
-   Jalankan `php artisan migrate --pretend` dan backup sebelum migrasi
    produksi.
