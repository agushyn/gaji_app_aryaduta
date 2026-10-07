# PRD.md --- Aplikasi Kirim Slip Gaji Personalia

**Versi:** 1.0\
**Status:** Blueprint untuk implementasi\
**Stack:** Laravel 13, React, Inertia.js, Tailwind CSS v4, MySQL 8\
**Target deployment:** Shared hosting/cPanel\
**Bahasa UI:** Indonesia\
**Zona waktu default:** Asia/Jakarta\
**Mata uang default:** IDR

## 1. Ringkasan Produk

Aplikasi Kirim Slip Gaji Personalia adalah aplikasi web internal untuk
mengelola data karyawan, memasukkan komponen gaji secara manual, membuat
slip gaji PDF, meminta persetujuan atasan, dan mengirimkan slip yang
telah disetujui kepada karyawan melalui email dan WhatsApp.

Aplikasi **tidak menyediakan portal login karyawan**. Karyawan menerima
PDF melalui kanal yang dipilih. Semua akses aplikasi diberikan kepada
petugas internal yang memiliki akun dan hak akses sesuai tugasnya.

## 2. Tujuan

1.  Memusatkan data karyawan dan slip gaji bulanan.
2.  Mengurangi kesalahan perhitungan melalui kalkulasi backend dan
    validasi.
3.  Mewajibkan persetujuan atasan sebelum slip difinalisasi dan dikirim.
4.  Mengirim slip secara individual melalui email dan gateway WhatsApp
    pihak ketiga.
5.  Menyediakan status pengiriman, percobaan ulang, dan audit trail.
6.  Menjaga kerahasiaan data gaji dan dokumen PDF.

## 3. Di luar Cakupan MVP

-   Portal login mandiri untuk karyawan.
-   Perhitungan payroll penuh dari absensi, lembur, pajak, BPJS,
    pinjaman, atau sistem HRIS lain.
-   Integrasi otomatis dengan bank atau sistem akuntansi.
-   Impor massal Excel pada MVP; input data gaji dilakukan manual.
-   Aplikasi mobile native.
-   Pengiriman slip sebelum disetujui dan difinalisasi.

Komponen seperti pajak, BPJS, lembur, bonus, dan potongan dapat
dimasukkan sebagai item manual. Aplikasi tidak boleh mengklaim
perhitungan pajak atau kepatuhan payroll otomatis kecuali modul khusus
ditambahkan dan divalidasi.

## 4. Pengguna dan Role

### Super Admin

-   Mengelola akun pengguna, role, permission, pengaturan perusahaan,
    template, dan koneksi provider.
-   Melihat audit log.
-   Dapat melakukan tindakan operasional khusus yang dicatat di audit
    log.

### Personalia

-   Mengelola master karyawan.
-   Membuat periode gaji dan slip draft.
-   Menambah/mengubah komponen gaji dinamis.
-   Mengajukan periode/slip untuk review.
-   Melihat hasil persetujuan.
-   Memfinalisasi slip setelah disetujui, sesuai permission yang
    diberikan.
-   Memulai pengiriman slip final dan melakukan retry yang gagal.

### Atasan / Approver

-   Melihat periode dan slip yang diajukan untuk persetujuan.
-   Meninjau rincian dan total.
-   Menyetujui atau menolak dengan alasan.
-   Tidak dapat mengubah komponen gaji saat meninjau; perubahan harus
    dikembalikan kepada Personalia.

### Auditor

-   Akses baca sesuai ruang lingkup izin.
-   Melihat riwayat perubahan, persetujuan, finalisasi, dan pengiriman.
-   Tidak dapat mengubah data atau mengirim slip.

Prinsip pemisahan tugas: pembuat data tidak menjadi satu-satunya pihak
yang menyetujui data tersebut. Untuk MVP, sistem harus menyimpan pembuat
dan approver secara terpisah. Jika Super Admin melakukan override, wajib
ada alasan dan audit log.

## 5. Alur Utama

1.  Super Admin menyiapkan perusahaan, pengguna, role, template PDF,
    SMTP, dan gateway WhatsApp.
2.  Personalia membuat/memperbarui data karyawan dan memastikan email
    serta nomor WhatsApp benar.
3.  Personalia membuat periode gaji dengan kode unik, misalnya
    `2026-10`.
4.  Personalia membuat satu slip draft untuk setiap karyawan yang
    menerima gaji.
5.  Personalia memasukkan item penghasilan dan potongan. Backend
    menghitung total.
6.  Personalia meninjau daftar, lalu mengajukan slip/periode untuk
    review.
7.  Atasan meninjau slip, kemudian menyetujui atau menolak. Penolakan
    wajib disertai alasan.
8.  Jika ditolak, Personalia memperbaiki draft dan mengajukan ulang.
    Riwayat keputusan lama tetap tersimpan.
9.  Setelah disetujui, Personalia memfinalisasi slip. Sistem membuat
    snapshot data, nomor slip, PDF final, dan hash PDF.
10. Personalia memilih kanal email, WhatsApp, atau keduanya; sistem
    menampilkan pratinjau jumlah penerima dan tujuan.
11. Sistem memasukkan satu job untuk setiap slip dan kanal ke antrean.
12. Status serta setiap percobaan pengiriman dicatat. Kegagalan dapat
    di-retry secara selektif.
13. Personalia memeriksa laporan akhir periode.

## 6. Status dan Transisi

### Status periode

-   `draft`
-   `in_review`
-   `changes_requested`
-   `approved`
-   `finalized`
-   `closed`

Transisi harus dilakukan melalui service backend, bukan update status
bebas dari frontend.

### Status slip

-   `draft`
-   `pending_review`
-   `changes_requested`
-   `approved`
-   `finalized`
-   `voided`

Slip yang sudah final tidak boleh diedit langsung. Koreksi dilakukan
melalui prosedur revisi/void yang tercatat dan membutuhkan persetujuan
ulang.

### Status pengiriman

-   `queued`
-   `processing`
-   `sent`
-   `delivered`
-   `failed`
-   `unknown`
-   `cancelled`

`sent` berarti provider menerima permintaan atau mengembalikan
penerimaan sesuai kontraknya; status ini tidak selalu berarti pesan
diterima/dibaca karyawan. `delivered` hanya digunakan jika provider
memberikan callback/status yang dapat dipercaya. Jika status belum dapat
dipastikan, gunakan `unknown`.

## 7. Kebutuhan Fungsional

### FR-01 Authentication

-   Login, logout, reset password yang aman, dan penonaktifan akun.
-   Batasi percobaan login.
-   Terapkan session timeout dan regenerasi session setelah login.

### FR-02 RBAC

-   Role dan permission dikelola dari backend.
-   Setiap route dan aksi sensitif diperiksa di backend.
-   Menyembunyikan tombol di React bukan kontrol keamanan yang cukup.

### FR-03 Master Karyawan

-   CRUD, pencarian, filter status/departemen, dan penonaktifan.
-   Nomor karyawan harus unik.
-   Validasi email dan nomor WhatsApp.
-   Karyawan yang memiliki riwayat slip tidak dihapus secara fisik
    melalui UI normal.

### FR-04 Periode Gaji

-   Buat dan kelola periode.
-   Cegah kode periode duplikat.
-   Periode tidak boleh ditutup jika masih ada slip yang belum
    diselesaikan, kecuali override beralasan dan tercatat.

### FR-05 Komponen Gaji Dinamis

-   Personalia dapat mengelola master komponen: kode, nama, jenis
    (`earning`/`deduction`), aktif/nonaktif, dan urutan tampilan.
-   Setiap slip memiliki snapshot nama/kode/jenis komponen dan
    nominalnya.
-   Item nominal harus nonnegatif; jenis menentukan apakah item ditambah
    atau dikurangkan.
-   Komponen yang telah digunakan tidak boleh dihapus secara fisik;
    nonaktifkan saja.

### FR-06 Input Slip

-   Buat slip draft per karyawan dan periode.
-   Input item penghasilan/potongan secara manual.
-   Hitung total penghasilan, total potongan, dan gaji bersih di
    backend.
-   Terapkan unique constraint satu slip per karyawan per periode.
-   Gunakan transaksi database saat menyimpan header dan item.

### FR-07 Review dan Persetujuan

-   Personalia mengajukan slip/periode.
-   Atasan melihat ringkasan dan rincian, lalu approve atau request
    changes.
-   Penolakan/perubahan wajib memiliki alasan.
-   Catat approver, waktu, keputusan, dan komentar.
-   Jika isi gaji berubah setelah pengajuan, persetujuan lama tidak
    berlaku dan harus diajukan ulang.

### FR-08 PDF

-   Generate PDF dari snapshot final, bukan data master yang bisa
    berubah.
-   PDF berisi identitas perusahaan, nomor slip, periode, identitas
    karyawan, rincian earning/deduction, total, dan catatan yang
    diperlukan.
-   Simpan di private storage.
-   Catat hash SHA-256 untuk pemeriksaan integritas.
-   PDF tidak boleh memiliki URL publik permanen.

### FR-09 Email

-   Kirim email individual melalui SMTP yang dapat dikonfigurasi.
-   PDF dilampirkan jika ukuran dan kebijakan provider mengizinkan.
-   Jangan menggunakan CC/BCC untuk pengiriman massal.
-   Catat ID provider bila tersedia, status, waktu, dan error yang telah
    disanitasi.

### FR-10 WhatsApp

-   Integrasi melalui gateway pihak ketiga menggunakan
    adapter/interface.
-   Mendukung pengiriman dokumen PDF jika gateway mendukungnya.
-   Konfigurasi endpoint, kredensial, sender/device ID, dan opsi
    provider harus disimpan secara aman.
-   Rahasia tidak boleh dikirim ke browser atau dicatat di log.
-   Patuhi ketentuan provider, consent, template, batas laju, dan
    kebijakan pesan yang berlaku.
-   Jangan mengasumsikan semua gateway menyediakan status delivered.

### FR-11 Antrean dan Retry

-   Pengiriman dilakukan asynchronous.
-   Satu pekerjaan per slip dan kanal.
-   Retry terbatas dengan jeda meningkat, sesuai kemampuan hosting.
-   Jangan retry otomatis untuk kesalahan permanen seperti nomor tidak
    valid atau kredensial salah.
-   Gunakan idempotency/deduplication untuk mencegah duplikasi.
-   Sediakan retry manual untuk record gagal.
-   Pada shared hosting, queue diproses melalui cron dengan batch
    terbatas; jangan bergantung pada worker daemon yang selalu hidup.

### FR-12 Dashboard dan Laporan

-   Ringkasan jumlah slip berdasarkan status.
-   Status pengiriman per kanal.
-   Filter periode, departemen, karyawan, kanal, dan status.
-   Ekspor laporan operasional tanpa menyertakan nominal gaji kecuali
    pengguna memiliki permission yang sesuai.
-   Laporan tidak boleh menampilkan kredensial provider atau data
    sensitif yang tidak diperlukan.

### FR-13 Audit

-   Catat pembuatan/perubahan karyawan, komponen, slip, pengajuan,
    keputusan, finalisasi, unduhan, pengiriman, retry, dan perubahan
    pengaturan.
-   Audit log tidak dapat diubah melalui UI normal.
-   Jangan menyimpan password, token, PDF, atau isi lengkap slip ke log.

## 8. Kebutuhan Nonfungsional

-   Framework: Laravel 13; frontend React + Inertia.js; Tailwind CSS v4;
    MySQL 8.
-   UI responsif untuk desktop dan tablet.
-   Validasi server-side pada semua input.
-   Gunakan decimal untuk nominal uang, bukan float.
-   Terapkan foreign key, unique constraints, dan indeks.
-   Semua koneksi eksternal melalui HTTPS.
-   Simpan file slip di luar web root atau gunakan private disk dengan
    controller yang mengotorisasi akses.
-   Backup database dan file terenkripsi; uji pemulihan.
-   Tetapkan retensi data berdasarkan kebijakan perusahaan dan ketentuan
    yang berlaku.
-   Waktu aplikasi disimpan konsisten dan ditampilkan dalam zona waktu
    Asia/Jakarta.
-   Gunakan pagination untuk daftar besar.

## 9. Aturan Bisnis dan Keamanan

1.  Karyawan penerima bukan akun aplikasi.
2.  Tidak ada slip yang dapat dikirim sebelum finalisasi.
3.  Finalisasi hanya dapat dilakukan setelah persetujuan.
4.  Perubahan pada slip yang diajukan membatalkan approval sebelumnya.
5.  Slip final bersifat immutable; koreksi harus tercatat.
6.  Email dan WhatsApp mempunyai status independen.
7.  Nomor tujuan di-snapshot saat finalisasi atau saat proses pengiriman
    disetujui, agar perubahan kontak setelahnya tidak diam-diam mengubah
    penerima.
8.  Aksi download PDF oleh petugas dicatat.
9.  Tautan dokumen tidak boleh permanen/publik.
10. Uji akses silang dan kebocoran PDF merupakan syarat rilis.

## 10. Risiko dan Mitigasi

  -----------------------------------------------------------------------
  Risiko                              Mitigasi
  ----------------------------------- -----------------------------------
  Salah nominal                       Backend calculation, preview,
                                      approval, test cases

  Salah penerima                      Validasi kontak, snapshot penerima,
                                      preview sebelum kirim

  Pesan ganda                         Idempotency, unique delivery
                                      record, retry selektif

  Kebocoran PDF                       Private storage, authorization,
                                      HTTPS, audit

  Provider down                       Queue, retry terbatas, status
                                      unknown, laporan gagal

  Batas shared hosting                Cron batch, timeout/memory checks,
                                      uji volume

  Perubahan slip setelah approval     Invalidate approval dan ajukan
                                      ulang

  Kehilangan data                     Backup terpisah dan uji restore
  -----------------------------------------------------------------------

## 11. Definisi MVP Selesai

MVP dinyatakan siap jika: - Semua role dan permission utama lulus
pengujian. - Personalia dapat membuat slip manual dengan komponen
dinamis. - Approval dan penolakan beralasan berfungsi. - Slip final
menghasilkan PDF privat yang konsisten. - Email dan WhatsApp dapat
diproses melalui antrean dan status tercatat. - Retry tidak menggandakan
pengiriman secara tidak sengaja. - Audit log tersedia untuk tindakan
sensitif. - Deployment cPanel, cron, backup, dan pemulihan telah
diuji. - UAT personalia menyetujui alur dari pembuatan periode sampai
laporan pengiriman.
