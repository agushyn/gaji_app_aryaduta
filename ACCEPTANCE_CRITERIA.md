# Acceptance Criteria & Test Plan

## A. Authentication dan RBAC

-   [ ] Pengguna yang tidak login diarahkan ke halaman login.
-   [ ] Akun nonaktif tidak dapat login atau menggunakan sesi lama.
-   [ ] Pengguna tanpa permission menerima 403 dari backend walaupun
    mengakses URL langsung.
-   [ ] Menu frontend disesuaikan dengan permission, tetapi otorisasi
    tetap diuji di server.
-   [ ] Login memiliki rate limiting dan session diregenerasi setelah
    autentikasi.

## B. Master karyawan

-   [ ] Nomor karyawan wajib unik.
-   [ ] Email dan nomor WhatsApp divalidasi bila diisi.
-   [ ] Nomor WhatsApp dinormalisasi ke format internasional.
-   [ ] Karyawan dengan riwayat slip tidak dapat dihapus melalui UI
    normal.
-   [ ] Perubahan data karyawan tidak mengubah snapshot slip final.

## C. Komponen dan perhitungan

-   [ ] Personalia dapat menambah, mengubah, mengurutkan, dan
    menonaktifkan komponen.
-   [ ] Komponen memiliki tipe earning atau deduction.
-   [ ] Nominal negatif ditolak.
-   [ ] Total earning, deduction, dan net dihitung ulang oleh backend.
-   [ ] Net = total earning - total deduction.
-   [ ] Basic salary tidak dihitung dua kali.
-   [ ] Satu karyawan hanya memiliki satu slip per periode.
-   [ ] Update header dan item dilakukan secara transaksional.

## D. Approval

-   [ ] Personalia dapat mengajukan slip/periode untuk review.
-   [ ] Approver dapat melihat rincian sebelum memutuskan.
-   [ ] Approver dapat menyetujui atau meminta perubahan dengan alasan.
-   [ ] Perubahan setelah pengajuan membatalkan approval sebelumnya.
-   [ ] Semua keputusan tersimpan di `payroll_approval_events`.
-   [ ] Slip tidak dapat difinalisasi tanpa approval yang sah.
-   [ ] Pemisahan pembuat dan approver ditegakkan bila konfigurasi
    mewajibkannya.

## E. PDF dan keamanan

-   [ ] PDF hanya dibuat dari snapshot final.
-   [ ] PDF berisi identitas perusahaan, karyawan, periode, rincian,
    total, dan nomor slip.
-   [ ] PDF disimpan di private storage.
-   [ ] URL langsung tanpa otorisasi tidak dapat membuka PDF.
-   [ ] Pengguna tanpa permission tidak dapat mengunduh PDF.
-   [ ] Hash SHA-256 tersimpan setelah file selesai dibuat.
-   [ ] Aksi unduh dicatat di audit log.
-   [ ] Tidak ada token atau nominal gaji yang tidak diperlukan di log.

## F. Email dan WhatsApp

-   [ ] Setiap delivery memiliki kanal dan penerima yang jelas.
-   [ ] Email dikirim individual tanpa CC/BCC massal.
-   [ ] PDF dilampirkan pada email bila batas ukuran provider
    mengizinkan.
-   [ ] WhatsApp menggunakan adapter gateway pihak ketiga.
-   [ ] Pengiriman dokumen PDF diuji terhadap provider aktual.
-   [ ] Secret tidak dikirim ke frontend atau dicatat di log.
-   [ ] Status sent tidak disamakan dengan delivered.
-   [ ] Kegagalan satu kanal tidak mengubah status kanal lainnya.
-   [ ] Nomor/email invalid ditandai tanpa retry otomatis tanpa batas.

## G. Queue dan retry

-   [ ] Request web tidak menunggu seluruh pengiriman massal selesai.
-   [ ] Satu job menangani satu slip dan satu kanal.
-   [ ] Job gagal dicatat dan terlihat oleh petugas berizin.
-   [ ] Retry terbatas dan setiap attempt tercatat.
-   [ ] Retry tidak membuat delivery record duplikat.
-   [ ] Callback provider duplikat tidak memproses perubahan status
    berulang secara merusak.
-   [ ] Cron cPanel memproses batch sesuai batas waktu hosting.
-   [ ] Pengiriman dapat dilanjutkan setelah proses terhenti.

## H. Audit dan laporan

-   [ ] Perubahan gaji, approval, finalisasi, download, send, retry, dan
    perubahan setting dicatat.
-   [ ] Auditor tidak dapat mengubah data.
-   [ ] Laporan dapat difilter menurut periode, departemen, status, dan
    kanal.
-   [ ] Ekspor data finansial dibatasi oleh permission.
-   [ ] Audit log tidak memuat secret atau PDF.

## I. Deployment cPanel

-   [ ] PHP dan extension memenuhi persyaratan Laravel 13 dan dependency
    proyek.
-   [ ] Document root mengarah ke direktori `public` aplikasi, bukan
    root proyek.
-   [ ] `.env` dan source code sensitif tidak dapat diakses publik.
-   [ ] APP_DEBUG=false dan HTTPS aktif.
-   [ ] Storage privat berada di luar web root atau diblokir dan diuji.
-   [ ] Queue database, cron, failed jobs, dan log berfungsi.
-   [ ] Backup database dan file diuji melalui proses restore.
-   [ ] `config:cache` dan `route:cache` hanya dijalankan setelah
    konfigurasi valid.
-   [ ] UAT dilakukan dengan data sintetis sebelum data produksi.

## Skenario UAT end-to-end

1.  Buat karyawan uji dengan email dan nomor WhatsApp uji yang
    terkontrol.
2.  Buat periode dan slip dengan beberapa earning serta deduction.
3.  Verifikasi perhitungan secara manual.
4.  Ajukan slip untuk review.
5.  Tolak dengan alasan, ubah nilai, lalu ajukan ulang.
6.  Approve dan finalisasi.
7.  Verifikasi PDF dan hash.
8.  Kirim email dan WhatsApp ke penerima uji.
9.  Simulasikan provider gagal, lalu retry.
10. Pastikan status dan attempt tercatat benar.
11. Coba akses PDF tanpa login dan dengan role tanpa izin; keduanya
    harus ditolak.
12. Periksa audit log, backup, dan pemulihan.
