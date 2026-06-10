# Test Case - SiKuKos
**Nama:** M. Dariel Arza  
**NIM:** 701240206  
**Modul yang Diuji:** Manajemen Transaksi (Input & Riwayat)

---

## TC-01: Input transaksi pemasukan berhasil

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-01 |
| **Nama Test** | Mencatat transaksi pemasukan sewa kamar |.
| **Kondisi Awal** | Pengguna sudah login dan berada di halaman Input Transaksi |
| **Langkah Pengujian** | 1. Klik menu "Input Transaksi" di sidebar <br> 2. Isi Tanggal Transaksi: `09/06/2026` <br> 3. Pilih Jenis Transaksi: `Pemasukan` <br> 4. Pilih Nomor Kamar: `K.01` <br> 5. Pilih Kategori: `Sewa Bulanan` <br> 6. Isi Jumlah: `800000` <br> 7. Pilih Metode Pembayaran: `Tunai` <br> 8. Klik tombol "Simpan Transaksi" |
| **Hasil yang Diharapkan** | Data transaksi tersimpan dan panel "Ringkasan Input Hari Ini" langsung terupdate |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-02: Input transaksi dengan nominal kosong

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-02 |
| **Nama Test** | Validasi gagal jika nominal transaksi tidak diisi |
| **Kondisi Awal** | Pengguna sudah login dan berada di halaman Input Transaksi |
| **Langkah Pengujian** | 1. Isi semua field kecuali kolom Jumlah (dikosongkan) <br> 2. Klik tombol "Simpan Transaksi" |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan error validasi dan tidak menyimpan data |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-03: Melihat riwayat transaksi

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-03 |
| **Nama Test** | Menampilkan seluruh riwayat transaksi kas |
| **Kondisi Awal** | Pengguna sudah login dan sudah ada data transaksi tersimpan |
| **Langkah Pengujian** | 1. Klik menu "Riwayat Transaksi" di sidebar <br> 2. Amati tabel yang ditampilkan |
| **Hasil yang Diharapkan** | Sistem menampilkan tabel berisi semua transaksi beserta Total Pemasukan, Total Pengeluaran, dan Saldo Bersih di bagian bawah |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |
