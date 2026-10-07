# Checklist Pengujian Penerimaan Pengguna (User Acceptance Testing / UAT)
## Aplikasi: Kirim Slip Gaji Personalia

Dokumen ini memuat skenario pengujian fungsional, keamanan, operasional antrean, dan pelaporan untuk memvalidasi kesiapan sistem dari Phase 1 hingga Phase 8 sebelum rilis produksi.

---

## Modul 1: Autentikasi dan Keamanan Sesi (Phase 1)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 1.1 | Login Akun Aktif | Masukkan email dan password pengguna aktif (Super Admin, Personalia, Approver, Auditor). | Berhasil masuk ke dashboard sesuai role masing-masing. | [ ] |
| 1.2 | Login Akun Nonaktif | Coba login menggunakan akun dengan status `is_active = false`. | Sistem menolak dengan pesan "Akun Anda telah dinonaktifkan." | [ ] |
| 1.3 | Rate Limiting Login | Coba login dengan kredensial salah 5 kali berturut-turut. | Sistem memblokir sementara percobaan berikutnya (HTTP 429 Too Many Requests). | [ ] |
| 1.4 | Pemisahan Akses Karyawan | Karyawan biasa mencoba login ke sistem. | Ditolak karena karyawan tidak memiliki akun login aplikasi. | [ ] |
| 1.5 | Logout dan Invalidasi Sesi | Klik menu Keluar/Logout di navigasi atas. | Sesi terhapus, token CSRF di-regenerate, kembali ke halaman login. | [ ] |

---

## Modul 2: Master Karyawan dan Normalisasi Kontak (Phase 2)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 2.1 | Tambah Karyawan Baru | Isi data NIK, Nama, Departemen, Jabatan, Email, dan No. HP/WhatsApp. | Data tersimpan, nomor WA dinormalisasi ke format internasional (misal: 0812 -> 62812). | [ ] |
| 2.2 | Validasi Duplikasi NIK | Tambah karyawan dengan NIK yang sudah ada di database. | Sistem menolak dengan pesan validasi "Nomor induk karyawan sudah digunakan." | [ ] |
| 2.3 | Validasi Format WhatsApp | Masukkan nomor HP acak yang tidak valid (misal: "abc" atau kurang dari 8 digit). | Validasi gagal dan menolak penyimpanan. | [ ] |
| 2.4 | Nonaktifkan Karyawan | Ubah status karyawan aktif menjadi nonaktif. | Karyawan tidak muncul di picker aktif, namun riwayat slip gaji lama tetap utuh. | [ ] |
| 2.5 | Proteksi Penghapusan Fisik | Coba hapus data karyawan yang sudah memiliki riwayat slip gaji. | Sistem mencegah penghapusan fisik dari database untuk menjaga integritas riwayat. | [ ] |

---

## Modul 3: Periode Gaji dan Komponen Dinamis (Phase 3)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 3.1 | Pembuatan Periode Gaji | Buat periode baru dengan kode unik, tanggal mulai, dan tanggal selesai. | Periode berstatus `DRAFT` berhasil dibuat. | [ ] |
| 3.2 | Pengelolaan Komponen Gaji | Buat komponen bertipe Earning (Tunjangan) dan Deduction (Potongan). | Komponen dinamis aktif dan tersusun rapi sesuai sort order. | [ ] |
| 3.3 | Perhitungan Matematika Gaji | Input komponen pendapatan dan potongan untuk karyawan. | Gaji kotor, total potongan, dan gaji bersih dihitung presisi oleh backend (BCMath). | [ ] |
| 3.4 | Pencegahan Duplikasi Slip | Input slip kedua untuk karyawan yang sama pada periode yang sama. | Ditolak oleh constraint database (`payroll_period_id` + `employee_id`). | [ ] |

---

## Modul 4: Workflow Review dan Approval (Phase 4)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 4.1 | Pengajuan Review | Personalia klik "Ajukan untuk Ditinjau" pada periode berstatus Draft. | Status periode beralih menjadi `SUBMITTED`, audit log mencatat event pengajuan. | [ ] |
| 4.2 | Penolakan / Permintaan Perubahan | Approver memilih "Minta Perubahan" dengan mengisi alasan wajib. | Status beralih menjadi `CHANGES_REQUESTED`. Alasan terekam di approval events. | [ ] |
| 4.3 | Invalidasi Approval Lama | Edit komponen slip setelah persetujuan diberikan. | Sistem mewajibkan pengajuan ulang dan membatalkan status approval sebelumnya. | [ ] |
| 4.4 | Persetujuan Final Periode | Approver menyetujui periode pengajuan. | Status beralih menjadi `APPROVED`. Periode siap untuk tahap finalisasi. | [ ] |

---

## Modul 5: Finalisasi, Snapshot, dan Dokumen PDF Privat (Phase 5)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 5.1 | Finalisasi Periode | Personalia melakukan aksi "Finalisasi Periode" pada periode yang sudah Approved. | Snapshot nominal dan data karyawan tersimpan statis, status periode beralih ke `FINALIZED`. | [ ] |
| 5.2 | Generate Dokumen PDF | Generate PDF slip gaji karyawan. | PDF terbuat sesuai template resmi perusahaan (kop surat, rincian 2 kolom, 3 tanda tangan). | [ ] |
| 5.3 | Keamanan Storage Privat | Akses URL file PDF langsung melalui browser tanpa login. | Web server menolak akses langsung (file berada di luar public web root). | [ ] |
| 5.4 | Preview PDF Terproteksi | Unduh / preview PDF melalui aplikasi dengan otentikasi. | Hanya pengguna berizin yang dapat mengunduh file melalui controller internal terproteksi. | [ ] |

---

## Modul 6: Pengiriman Email SMTP dan Tracking (Phase 6)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 6.1 | Uji Koneksi SMTP | Akses pengaturan email dan jalankan tes koneksi SMTP. | Tes koneksi berhasil tanpa mengirimkan data gaji atau membocorkan secret password. | [ ] |
| 6.2 | Pengiriman Slip via Email | Kirim slip gaji individual via email dengan PDF terlampir. | Job asynchronous ter-dispatch, email terkirim, riwayat delivery tercatat di database. | [ ] |
| 6.3 | Idempotensi Job Email | Dispatch ulang job email yang sama dengan idempotency key identik. | Sistem tidak mengirim ulang email ganda, mengembalikan record pengiriman yang ada. | [ ] |
| 6.4 | Penanganan Kegagalan Email | Simulasikan error SMTP (koneksi putus atau alamat email salah). | Attempt tercatat, status menjadi `FAILED`, pesan error disanitasi tanpa mengekspos secret. | [ ] |

---

## Modul 7: Integrasi WhatsApp Gateway Multi-Vendor (Phase 7)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 7.1 | Konfigurasi Gateway | Atur provider WhatsApp (Fonnte / Wablas) dan simpan API token terenkripsi. | Token tersimpan aman terenkripsi di database, antarmuka melakukan masking rahasia. | [ ] |
| 7.2 | Uji Koneksi WA | Jalankan tes ping koneksi WhatsApp melalui form konfigurasi. | Status koneksi gateway tervalidasi via HTTP client tanpa mengirim pesan aktual. | [ ] |
| 7.3 | Pengiriman Slip via WA | Kirim slip gaji ke nomor WhatsApp penerima dengan dokumen PDF terlampir. | Job WA ter-dispatch terpisah, status terisolasi dari kanal email. | [ ] |
| 7.4 | Normalisasi Status WA | Provider merespons status pesan (queued, sent, failed). | Sistem menormalkan status ke enum `DeliveryStatus` internal secara konsisten. | [ ] |

---

## Modul 8: Pengiriman Massal, Dashboard, Laporan, dan Hardening (Phase 8)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|:---:|
| 8.1 | Pratinjau Pengiriman Massal | Buka halaman Pengiriman Massal, pilih periode dan kanal (Email, WhatsApp, atau Keduanya). | Kartu summary menampilkan jumlah siap kirim, kontak valid, email kosong, dan WA kosong secara akurat. | [ ] |
| 8.2 | Dispatch Job Terpisah | Klik "Proses Pengiriman Massal" untuk 10 slip dengan kanal Keduanya. | Terbuat 20 record job terpisah (10 email + 10 WhatsApp) dengan status independen. | [ ] |
| 8.3 | Filter Selektif Penerima | Pilih departemen tertentu atau gunakan checkbox individual pada tabel pratinjau. | Hanya slip yang dipilih yang di-dispatch ke antrean. | [ ] |
| 8.4 | Pemrosesan Antrean cPanel Cron | Jalankan perintah `php artisan payroll:process-queue --limit=30 --timeout=50`. | Job antrean diproses per batch dengan atomic lock, proses keluar bersih saat antrean kosong. | [ ] |
| 8.5 | Retry Selektif Gagal | Klik "Retry Semua Gagal" pada halaman pengiriman. | Hanya delivery berstatus `FAILED` yang di-reset ke `QUEUED`; pengiriman berstatus `SENT` atau `DELIVERED` tidak digandakan. | [ ] |
| 8.6 | Dashboard Laporan Pengiriman | Buka menu Laporan Pengiriman dan gunakan filter status, periode, kanal, dan departemen. | Metrik kartu ringkasan, persentase keberhasilan, dan distribusi departemen terhitung akurat. | [ ] |
| 8.7 | Ekspor CSV Role Personalia | Personalia (memiliki izin `payslips.financial-view`) klik "Ekspor CSV". | File CSV terunduh dengan nominal take home pay terisi angka asli (Rp). | [ ] |
| 8.8 | Ekspor CSV Role Auditor | Auditor (tidak memiliki izin `payslips.financial-view`) klik "Ekspor CSV". | File CSV terunduh dengan kolom take home pay dimasking tulisan `[TERBATAS]`. | [ ] |
| 8.9 | Audit Trail Terpusat | Periksa menu Audit Log setelah melakukan pengiriman massal dan ekspor laporan. | Aksi `deliveries.bulk_dispatched`, `deliveries.bulk_retried`, dan `reports.delivery_exported` tercatat lengkap dengan identitas aktor. | [ ] |

---

## Tanda Tangan Persetujuan UAT

Pengujian UAT dinyatakan **LULUS** jika seluruh skenario kritis di atas telah terverifikasi dengan hasil yang diharapkan dan tidak ditemukan celah keamanan data.

- **Tanggal Pengujian**: ________________________
- **Perwakilan Personalia**: ________________________ (Tanda Tangan: __________)
- **Perwakilan Approver / Pimpinan**: ________________________ (Tanda Tangan: __________)
- **Administrator Teknis**: ________________________ (Tanda Tangan: __________)
