# Phase Prompts Antigravity IDE --- Kirim Slip Gaji Personalia

## Instruksi global untuk semua phase

Kamu bekerja pada proyek aplikasi **Kirim Slip Gaji Personalia** dengan
stack Laravel 13, React, Inertia.js, Tailwind CSS v4, dan MySQL 8, untuk
deployment shared hosting/cPanel.

Sebelum mengubah kode: 1. Periksa struktur repository, `composer.json`,
`package.json`, route, model, migration, UI, dan konfigurasi yang sudah
ada. 2. Baca `PRD.md`, `DESIGN.md`, `MIGRATIONS.md`, dan
`ACCEPTANCE_CRITERIA.md`. 3. Pertahankan pola dan dependency yang valid.
Jangan mengganti stack atau membuat ulang proyek jika tidak diperlukan.
4. Jangan menghapus data, migration produksi, atau fitur yang sudah ada.
5. Implementasikan hanya scope phase yang diminta. 6. Gunakan Form
Request, Policy/Gate, service layer, transaksi database, dan audit log
untuk aksi sensitif. 7. Jangan menyimpan PDF slip di public web root.
Jangan expose secret provider ke React. 8. Buat dan jalankan test yang
relevan. Jalankan formatter/linter yang tersedia. 9. Jangan mengklaim
selesai jika test gagal atau integrasi belum benar-benar diuji. Laporkan
keterbatasan secara eksplisit. 10. Setelah selesai, laporkan file yang
diubah, migration baru, command yang dijalankan, hasil test, dan langkah
manual yang masih diperlukan.

## Phase 1 --- Fondasi proyek dan autentikasi

**Prompt:**

Implementasikan Phase 1 sesuai PRD.md dan DESIGN.md. Audit proyek
terlebih dahulu. Siapkan fondasi Laravel 13 + React + Inertia.js +
Tailwind CSS v4 tanpa merusak konfigurasi yang sudah benar.
Implementasikan login/logout, status akun aktif/nonaktif, rate limiting
login, session security, layout aplikasi internal, navigasi dasar, dan
fondasi RBAC dengan roles/permissions yang dikelola server-side. Siapkan
audit log service dasar. Buat seeder role dan permission: Super Admin,
Personalia, Approver, Auditor. Jangan membuat akun karyawan sebagai user
login. Tambahkan test untuk autentikasi, akun nonaktif, dan akses route.
Pastikan UI responsif dan konsisten. Jangan implementasikan modul
payroll pada phase ini.

**Acceptance gate:** - Login/logout berjalan. - Akun nonaktif ditolak. -
Permission ditegakkan di backend. - Seeder dapat dijalankan dengan
aman. - Test lulus.

## Phase 2 --- Master karyawan

**Prompt:**

Implementasikan modul Master Karyawan sesuai PRD.md dan DESIGN.md. Buat
migration/model/controller/Form Request/Policy/service/React pages untuk
daftar, pencarian, filter, tambah, edit, detail, dan nonaktifkan
karyawan. Field: employee_number, full_name, department, position,
email, phone, hire_date, employment_status. Employee number harus unik.
Normalisasi nomor WhatsApp ke format internasional dengan validasi yang
masuk akal; jangan menganggap nomor valid hanya karena bisa diformat.
Karyawan dengan riwayat slip tidak boleh dihapus fisik melalui UI
normal. Catat perubahan sensitif pada audit log. Tambahkan pagination
dan test RBAC/validasi.

**Acceptance gate:** - CRUD dan pencarian berfungsi. - Duplikasi
employee number ditolak di aplikasi dan database. - Nonaktifkan tidak
menghapus riwayat. - Akses tanpa permission ditolak.

## Phase 3 --- Periode, komponen dinamis, dan input slip

**Prompt:**

Implementasikan periode gaji, master komponen dinamis, dan input slip
manual. Buat migration/model/service/Form Request/Policy/UI. Periode
memiliki period_code unik, start_date, end_date, dan status. Komponen
memiliki code, name, type earning/deduction, active flag, dan sort
order. Personalia dapat menambah dan menonaktifkan komponen; komponen
yang telah digunakan tidak dihapus fisik. Slip memiliki unique
constraint payroll_period_id + employee_id. Buat UI input rincian item
yang dinamis. Hitung total earning, deduction, dan net hanya di backend
menggunakan decimal, bukan float. Simpan header dan item dalam
transaksi. Jangan mengimplementasikan approval atau pengiriman sebelum
phase berikutnya. Tambahkan unit test perhitungan, validasi nominal, dan
duplikasi.

**Acceptance gate:** - Komponen dinamis dapat dikelola. - Total selalu
dihitung backend. - Basic salary tidak terhitung ganda. - Unique
constraint slip berjalan. - Unit test perhitungan lulus.

## Phase 4 --- Workflow review dan approval

**Prompt:**

Implementasikan workflow pengajuan dan persetujuan sesuai PRD.md.
Personalia dapat submit slip/periode untuk review. Approver dapat
melihat rincian dan memilih approve atau request changes; alasan wajib
untuk request changes. Simpan setiap keputusan di
payroll_approval_events dan audit log. Perubahan komponen/nominal
setelah submit harus membatalkan approval sebelumnya dan memerlukan
pengajuan ulang. Terapkan pemisahan tugas pembuat dan approver jika
diwajibkan konfigurasi. Semua transisi status dilakukan melalui service
dan transaksi, bukan update status bebas dari controller/frontend. Buat
halaman daftar review dan detail approval. Tambahkan test untuk transisi
sah/tidak sah, penolakan, pengajuan ulang, dan user tanpa permission.

**Acceptance gate:** - Approval dan request changes berjalan. - Alasan
penolakan wajib. - Perubahan data menginvalidasi approval lama. - Semua
event terekam. - Slip yang belum approved tidak dapat difinalisasi.

## Phase 5 --- Finalisasi, snapshot, dan PDF privat

**Prompt:**

Implementasikan finalisasi slip dan generator PDF. Finalisasi hanya
boleh dilakukan jika approval sah. Saat finalisasi, buat snapshot
identitas karyawan dan penerima, nomor slip unik, final timestamp, lalu
render PDF dari snapshot. Simpan file di private disk di luar public web
root jika memungkinkan. Buat endpoint preview/download yang melakukan
autentikasi, Policy, pemeriksaan status, dan audit log; jangan expose
URL publik permanen. Simpan SHA-256 PDF. Slip final tidak dapat diedit
langsung; sediakan struktur status void/revisi yang tercatat, tanpa
mengizinkan perubahan diam-diam. Tambahkan test bahwa PDF tidak bisa
diakses tanpa izin dan perubahan master karyawan tidak mengubah PDF
lama. Pastikan dependency PDF kompatibel dengan PHP/hosting target.

**Acceptance gate:** - PDF benar dan konsisten dengan snapshot. - PDF
tidak dapat diakses publik. - Finalisasi tanpa approval ditolak. - Hash
disimpan. - Test akses dan snapshot lulus.

## Phase 6 --- Email SMTP dan delivery tracking

**Prompt:**

Implementasikan pengiriman email melalui Laravel Mail dengan SMTP yang
dapat dikonfigurasi. Buat payslip_deliveries dan delivery_attempts bila
belum tersedia. Buat satu delivery record per payslip dan channel; kirim
individual, tanpa CC/BCC massal. Lampirkan PDF final. Buat job
asynchronous, idempotency key, retry terbatas, backoff, timeout, status,
error summary yang disanitasi, dan tampilan log. Jangan mengirim slip
yang belum finalized. Jangan log isi email, nominal, attachment,
password, atau SMTP secret. Tambahkan halaman pengaturan email dengan
secret masking dan tombol uji koneksi yang tidak mengirim data gaji.
Test job success/failure/retry dan duplicate dispatch.

**Acceptance gate:** - Pengiriman email hanya untuk slip final. - Job
tidak menahan request web. - Status dan attempt tercatat. - Retry tidak
menggandakan delivery. - Secret tidak terekspos.

## Phase 7 --- WhatsApp gateway pihak ketiga

**Prompt:**

Implementasikan integrasi WhatsApp melalui adapter/provider interface
agar vendor dapat diganti. Jangan mengunci kode pada satu vendor.
Sediakan konfigurasi endpoint, sender identifier, secret terenkripsi,
test connection, dan status konfigurasi. Implementasikan pengiriman
dokumen PDF hanya jika API provider aktual mendukungnya; verifikasi
dokumentasi dan kontrak provider yang dipilih sebelum menganggap
integrasi berfungsi. Jika provider memerlukan upload media atau signed
URL, gunakan mekanisme privat dan kedaluwarsa, bukan URL publik
permanen. Verifikasi signature/token callback jika callback tersedia.
Normalisasi status provider menjadi
queued/processing/sent/delivered/failed/unknown. Terapkan rate limit,
idempotency, error sanitization, dan jangan log token atau konten
sensitif. Tambahkan test dengan fake HTTP client dan dokumentasikan
env/config yang diperlukan. Jangan mengklaim pengiriman real berhasil
tanpa kredensial dan tes nyata yang diotorisasi.

**Acceptance gate:** - Adapter terpisah dari business logic. - Secret
dienkripsi dan tidak tampil kembali. - Dokumen dikirim sesuai kemampuan
provider. - Status tidak mengklaim delivered tanpa bukti provider. -
Test fake HTTP lulus.

## Phase 8 --- Pengiriman massal, laporan, hardening, dan cPanel

**Prompt:**

Selesaikan pengiriman massal, dashboard status, laporan, retry selektif,
audit lengkap, dan deployment cPanel. Buat halaman pemilihan periode,
kanal, dan penerima dengan pratinjau jumlah penerima serta tujuan. Buat
job terpisah per slip/kanal. Proses queue database melalui cron dengan
batch dan timeout yang cocok untuk shared hosting; jangan bergantung
pada daemon worker permanen. Tambahkan command artisan yang aman untuk
memproses antrean terbatas jika diperlukan, dan dokumentasikan cron yang
harus disesuaikan dengan path PHP hosting. Sediakan laporan filter
periode/departemen/status/channel dan batasi ekspor nominal menurut
permission. Audit semua aksi sensitif. Uji backup/restore, storage
privat, batas file, rate limit, retry, dan akses silang. Jalankan
seluruh test suite, lint, build frontend, dan siapkan checklist UAT.
Jangan melakukan deployment produksi tanpa persetujuan eksplisit.

**Acceptance gate:** - Preview penerima akurat. - Email dan WhatsApp
memiliki status independen. - Queue berjalan dengan cron terbatas. -
Retry selektif tidak menggandakan pengiriman. - Audit dan laporan
berfungsi. - Checklist deployment dan UAT tersedia.

## Prompt audit akhir --- sebelum go-live

Lakukan audit menyeluruh terhadap implementasi dibandingkan `PRD.md`,
`DESIGN.md`, `MIGRATIONS.md`, dan `ACCEPTANCE_CRITERIA.md`. Periksa
semua route dan endpoint sensitif, RBAC server-side, kalkulasi gaji,
approval, snapshot, storage PDF, download, SMTP, WhatsApp adapter,
queue, retry/idempotency, audit log, secret management, cron cPanel,
backup/restore, dan kebocoran data pada log/response. Jalankan test
suite, lint, dan production build. Buat laporan berisi temuan
berdasarkan tingkat risiko, file terkait, bukti test, perbaikan yang
dilakukan, dan sisa risiko. Jangan menandai go-live jika ada akses
silang slip, PDF publik, pengiriman sebelum approval/finalisasi, atau
secret terekspos.
