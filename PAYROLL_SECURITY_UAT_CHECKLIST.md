# PANDUAN DAN CHECKLIST USER ACCEPTANCE TESTING (UAT)
## MODUL PAYROLL SECURITY — KIRIM SLIP GAJI PERSONALIA

**Aplikasi:** Kirim Slip Gaji Personalia  
**Modul:** Payroll Security (`/payroll-security`, `/payroll-security/reports`)  
**Target Pengguna:** Personalia (HRD), Approver (Atasan/Pimpinan), Auditor, Super Admin  
**Tanggal Penyusunan:** 11 November 2026  
**Status:** **READY FOR TESTING (SIAP DIGUNAKAN OLEH USER)**  

---

## 1. Petunjuk Umum Pelaksanaan UAT

1. **Lingkungan Pengujian**:
   - Seluruh pengujian UAT wajib menggunakan data simulasi/dummy (bukan data gaji riil produksi).
   - Pengujian dapat diakses melalui browser pada komputer kerja atau laptop.
2. **Kriteria Status**:
   - **PASS**: Fitur berjalan sesuai instruksi dan hasil perhitungan akurat.
   - **FAIL**: Terdapat kesalahan fungsi atau deviasi angka perhitungan.
   - **BLOCKED**: Pengujian terhambat oleh masalah sistem atau hak akses yang tidak sesuai.
3. **Pemisahan Temuan**:
   - Temuan teknis (bug, layout rusak, error 500) dicatat pada kolom *Catatan / Temuan*.
   - Keputusan bisnis (kebijakan tarif lembur libur, toleransi jam) dicatat secara terpisah untuk ditindaklanjuti oleh manajemen.

---

## 2. Checklist Pengujian Per Role

### A. Role Personalia (HRD)

| No | Kode Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status (PASS/FAIL) | Catatan / Temuan |
|:---|:---|:---|:---|:---:|:---|
| 1 | `UAT-HRD-01` | Membuka menu **/payroll-security** dan memilih periode payroll aktif. | Daftar seluruh petugas Security aktif tampil rapi dalam matriks tabel ringkas. | [ ] PASS | |
| 2 | `UAT-HRD-02` | Mengisi jumlah **Hari Masuk**, **OFF**, **Sakit**, **Izin**, **Cuti**, dan **Alfa**. | Jam Hadir otomatis terhitung ($8 \times \text{Hari Masuk}$). Jika total hari melebihi hari kalender periode, sistem menampilkan pesan validasi. | [ ] PASS | |
| 3 | `UAT-HRD-03` | Mengisi hari dan jam **Lembur Biasa (REGULAR)**. | Perhitungan lembur jam pertama (1.5x) dan jam lanjutan (2.0x) terhitung otomatis di background. | [ ] PASS | |
| 4 | `UAT-HRD-04` | Mengisi hari dan jam **Lembur Libur (OFF_HOLIDAY)** tanpa centang konfirmasi tarif. | Sistem menjaga proteksi tarif: biaya lembur libur bernilai Rp 0 dan diberi keterangan *Menunggu Konfirmasi Tarif*. | [ ] PASS | |
| 5 | `UAT-HRD-05` | Mengisi hari dan jam **Lembur Libur** dengan mencentang konfirmasi tarif. | Biaya lembur libur terhitung sesuai tarif/multiplier yang disetujui. | [ ] PASS | |
| 6 | `UAT-HRD-06` | Menekan tombol **Simpan Semua Data** (Bulk Save). | Seluruh perubahan tersimpan dalam transaksi database dan muncul pesan sukses. | [ ] PASS | |
| 7 | `UAT-HRD-07` | Menekan tombol **Sync ke Slip Gaji**. | Komponen `OVERTIME` pada slip gaji terbarui otomatis dengan rincian jam reguler vs libur pada catatan slip. | [ ] PASS | |
| 8 | `UAT-HRD-08` | Mengajukan periode gaji melalui alur **Submission**. | Status periode berubah menjadi `in_review` dan terkunci dari pengeditan langsung. | [ ] PASS | |

---

### B. Role Approver (Atasan / Pimpinan)

| No | Kode Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status (PASS/FAIL) | Catatan / Temuan |
|:---|:---|:---|:---|:---:|:---|
| 1 | `UAT-APP-01` | Membuka menu **/approvals** dan melihat daftar pengajuan payroll Security. | Muncul daftar pengajuan berstatus `Menunggu Persetujuan` (in_review). | [ ] PASS | |
| 2 | `UAT-APP-02` | Meninjau rincian gaji, komponen lembur, dan potongan tiap Security. | Data gaji, rincian lembur, dan take home pay terlihat jelas dan konsisten. | [ ] PASS | |
| 3 | `UAT-APP-03` | Menekan tombol **Minta Perubahan (Request Changes)** dengan mengisi alasan revisi. | Periode kembali ke status `changes_requested`, HRD dapat mengedit kembali, dan histori tersimpan. | [ ] PASS | |
| 4 | `UAT-APP-04` | Menyetujui pengajuan payroll (**Approve**) setelah revisi HRD. | Status periode dan seluruh slip gaji berubah menjadi `approved`. | [ ] PASS | |
| 5 | `UAT-APP-05` | Memverifikasi Segregation of Duties (Anti Self-Approval). | Pengguna yang mengajukan payroll dilarang menyetujui pengajuannya sendiri. | [ ] PASS | |

---

### C. Role Auditor (Pemeriksa Kepatuhan)

| No | Kode Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status (PASS/FAIL) | Catatan / Temuan |
|:---|:---|:---|:---|:---:|:---|
| 1 | `UAT-AUD-01` | Membuka dashboard **/payroll-security/reports**. | Menampilkan ringkasan metrik finansial, status slip gaji, dan peringatan kekurangan jam kerja. | [ ] PASS | |
| 2 | `UAT-AUD-02` | Memeriksa tab **Rincian Karyawan (24 Kolom)**. | Seluruh parameter kehadiran, perhitungan jam kerja, kekurangan jam, lembur, dan slip gaji tersaji lengkap. | [ ] PASS | |
| 3 | `UAT-AUD-03` | Memeriksa tab **Kekurangan Jam & Carry-Forward**. | Transparansi carry-forward antar-periode dan kompensasi (offset) jam lembur dapat ditelusuri per individu. | [ ] PASS | |
| 4 | `UAT-AUD-04` | Menekan tombol **Jalankan Rekonsiliasi** pada tab Rekonsiliasi. | 10 poin uji integritas data dijalankan secara *read-only* tanpa memutasi data, berstatus `MATCH` (hijau) atau `MISMATCH` (merah). | [ ] PASS | |
| 5 | `UAT-AUD-05` | Mengunduh laporan via tombol **Export Excel (.xlsx)** dan **Export CSV (.csv)**. | File terunduh sempurna, format angka/mata uang rapi, dan karakter formula berbahaya otomatis disanitasi. | [ ] PASS | |
| 6 | `UAT-AUD-06` | Mencoba melakukan pengeditan data kehadiran atau konfigurasi rumus. | Sistem menolak akses dengan respons `403 Forbidden` (hak akses strictly read-only). | [ ] PASS | |

---

### D. Role Super Admin (Administrator Sistem)

| No | Kode Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status (PASS/FAIL) | Catatan / Temuan |
|:---|:---|:---|:---|:---:|:---|
| 1 | `UAT-ADM-01` | Membuka modal **Aturan Rumus** dan memeriksa parameter aktif (Upah/Jam, Jam Pertama, Multiplier). | Parameter formula terkonfigurasi sesuai standar perusahaan dan tercatat nomor versinya. | [ ] PASS | |
| 2 | `UAT-ADM-02` | Memeriksa menu **Audit Log** untuk aktivitas modul Security. | Setiap aksi (update kehadiran, bulk save, sync slip, export, run reconciliation) tercatat waktu, aktor, dan detail perubahannya. | [ ] PASS | |
| 3 | `UAT-ADM-03` | Memverifikasi proteksi payroll berstatus `finalized`. | Begitu periode difinalisasi, seluruh data terkunci permanen, PDF slip terstempel SHA-256, dan perubahan data master tidak merusak slip lama. | [ ] PASS | |

---

## 3. Lembar Pengesahan UAT

| Peran Penguji | Nama Penguji | Tanda Tangan / Konfirmasi | Tanggal |
|:---|:---|:---|:---|
| **Personalia (HRD)** | ____________________ | [ ] Disetujui / [ ] Revisi | ____/____/2026 |
| **Approver (Atasan)** | ____________________ | [ ] Disetujui / [ ] Revisi | ____/____/2026 |
| **Auditor Internal** | ____________________ | [ ] Disetujui / [ ] Revisi | ____/____/2026 |
| **Super Admin** | ____________________ | [ ] Disetujui / [ ] Revisi | ____/____/2026 |

