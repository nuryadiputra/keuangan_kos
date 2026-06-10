# Test Case - SiKuKos
**Nama:** Muhammad Nuryadi Putra Pratama  
**NIM:** 701240216  
**Modul yang Diuji:** Manajemen Data Kamar & Kategori

---

## TC-01: Tambah data kamar baru berhasil

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-01 |
| **Nama Test** | Menambahkan data kamar kos baru ke sistem |
| **Kondisi Awal** | Pengguna sudah login sebagai Admin dan berada di halaman Data Kamar |
| **Langkah Pengujian** | 1. Klik menu "Data Kamar" di sidebar <br> 2. Klik tombol "Tambah Kamar" <br> 3. Isi Nomor Kamar: `K.05` <br> 4. Pilih Kategori: `Eksklusif (AC)` <br> 5. Isi Harga Sewa: `1200000` <br> 6. Pilih Status: `Kosong` <br> 7. Klik tombol "Simpan" |
| **Hasil yang Diharapkan** | Data kamar baru berhasil tersimpan ke database MySQL, muncul notifikasi sukses, dan data tampil di tabel |
| **Hasil Aktual** | Data kamar K.05 berhasil tersimpan dan langsung muncul di tabel utama |
| **Status** | Lulus |

---

## TC-02: Tambah kategori kamar baru

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-02 |
| **Nama Test** | Menambahkan tipe/kategori fasilitas kamar baru |
| **Kondisi Awal** | Pengguna sudah login sebagai Admin dan berada di halaman Kategori Kamar |
| **Langkah Pengujian** | 1. Klik menu "Kategori Kamar" <br> 2. Klik tombol "Tambah Kategori" <br> 3. Isi Nama Kategori: `VIP` <br> 4. Klik tombol "Simpan" |
| **Hasil yang Diharapkan** | Kategori baru tersimpan dan otomatis muncul sebagai pilihan saat menambahkan data kamar baru |
| **Hasil Aktual** | Kategori 'VIP' berhasil ditambahkan ke dalam opsi pilihan menu kamar |
| **Status** | Lulus |

---

## TC-03: Validasi input nomor kamar duplikat

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-03 |
| **Nama Test** | Sistem menolak input nomor kamar yang sudah terdaftar |
| **Kondisi Awal** | Pengguna sudah login dan kamar dengan nomor `K.05` sudah ada di sistem |
| **Langkah Pengujian** | 1. Klik tombol "Tambah Kamar" <br> 2. Isi Nomor Kamar yang sama: `K.05` <br> 3. Isi field lainnya dengan lengkap <br> 4. Klik tombol "Simpan" |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan error "Nomor kamar sudah terdaftar" dan menolak penyimpanan data |
| **Hasil Aktual** | Sistem memunculkan notifikasi peringatan dan menggagalkan proses simpan |
| **Status** | Lulus |