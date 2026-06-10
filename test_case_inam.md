# Test Case - SiKuKos
**Nama:** In'am Ittaqi  
**NIM:** 701240198  
**Modul yang Diuji:** Dashboard & Laporan Keuangan

---

## TC-01: Menampilkan ringkasan dashboard

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-01 |
| **Nama Test** | Dashboard menampilkan ringkasan keuangan dengan benar |
| **Kondisi Awal** | Pengguna sudah login dan sudah ada data transaksi tersimpan |
| **Langkah Pengujian** | 1. Klik menu "Dashboard" di sidebar <br> 2. Amati 4 kartu ringkasan yang muncul |
| **Hasil yang Diharapkan** | Sistem menampilkan kartu: Total Pemasukan, Total Pengeluaran, Laba Bersih, dan Kamar Terisi dengan angka yang sesuai data |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-02: Filter laporan keuangan berdasarkan periode

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-02 |
| **Nama Test** | Laporan keuangan berhasil difilter berdasarkan bulan & tahun |
| **Kondisi Awal** | Pengguna sudah login dan berada di halaman Laporan Keuangan |
| **Langkah Pengujian** | 1. Klik menu "Laporan Keuangan" di sidebar <br> 2. Pilih periode: `Mei 2026` pada dropdown filter <br> 3. Amati hasil laporan yang ditampilkan |
| **Hasil yang Diharapkan** | Sistem menampilkan Laporan Laba-Rugi, Rekapitulasi Saldo, dan Status Pembayaran Kamar sesuai periode yang dipilih |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-03: Cetak laporan keuangan ke PDF

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-03 |
| **Nama Test** | Fitur cetak laporan menghasilkan dokumen PDF |
| **Kondisi Awal** | Pengguna sudah login, berada di halaman Laporan Keuangan, dan sudah memilih periode |
| **Langkah Pengujian** | 1. Pilih periode laporan yang diinginkan <br> 2. Klik tombol "Cetak Laporan" <br> 3. Amati dokumen yang dihasilkan |
| **Hasil yang Diharapkan** | Sistem memproses dan menghasilkan dokumen laporan dalam format PDF yang siap dicetak |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |
.