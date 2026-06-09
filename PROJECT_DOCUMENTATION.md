# Dokumentasi Project SiKuKos

## 1. Gambaran Umum

SiKuKos adalah aplikasi pencatatan keuangan kos berbasis web. Frontend berada dalam satu file utama `keuangan_kos.html`, sedangkan data diambil dari MySQL melalui API PHP di folder `api/`.

Aplikasi ini digunakan untuk login pemilik kos, melihat dashboard bulanan, memantau data kamar, mencatat transaksi, melihat riwayat transaksi, dan membuat laporan keuangan per bulan.

## 2. Struktur Project

```text
keuangan_kos.html          Halaman utama aplikasi
database.js                Konfigurasi API, data master, dan utilitas global
app.js                     Login, navigasi halaman, dan dashboard
modul_kamar.js             Render data kamar dan statistik kamar
modul_transaksi.js         Input transaksi, ringkasan tanggal, riwayat, pagination
modul_laporan.js           Laporan bulanan, saldo, status pembayaran kamar
api/                       Backend PHP REST API
api/config/database.php    Konfigurasi koneksi MySQL
api/controllers/           Logic endpoint API
api/helpers/               Helper response JSON, validasi input, dan perhitungan sewa
api/helpers/rent.php       Helper status pembayaran sewa, sisa tagihan, dan titipan bulan berikutnya
sql/schema.sql             Struktur database dan tabel
sql/seed.sql               Data awal untuk demo
```

## 3. Kebutuhan Sistem

Pastikan komputer sudah memiliki:

- PHP 8 atau lebih baru dengan ekstensi `pdo_mysql`
- MySQL atau MariaDB
- Browser modern seperti Chrome atau Edge
- Terminal PowerShell atau CMD

Project ini tidak membutuhkan `npm install` karena tidak memakai build tool Node.js.

## 4. Setup Database MySQL

Jalankan MySQL terlebih dahulu. Jika memakai XAMPP, buka **XAMPP Control Panel**, lalu klik **Start** pada MySQL.

Import database dari terminal di folder project:

```powershell
mysql -u root -p < .\sql\schema.sql
mysql -u root -p < .\sql\seed.sql
```

Jika user MySQL root tidak memakai password, langsung tekan Enter saat diminta password.

Alternatif via phpMyAdmin:

1. Buka `http://localhost/phpmyadmin`.
2. Import file `sql/schema.sql`.
3. Import file `sql/seed.sql`.
4. Pastikan database bernama `sikukos` sudah muncul.

Data login awal dari seed:

```text
Username: pemilik_kos
Password: kos_123
```

## 5. Konfigurasi Koneksi Database

Konfigurasi default ada di `api/config/database.php`:

```text
DB_HOST = localhost
DB_PORT = 3306
DB_NAME = sikukos
DB_USER = root
DB_PASS = kosong
```

Jika konfigurasi MySQL berbeda, set environment variable sebelum menjalankan server:

```powershell
$env:DB_USER="root"
$env:DB_PASS="password_mysql_anda"
php -S localhost:8000 -t .
```

## 6. Menjalankan Aplikasi

Dari folder project, jalankan server PHP:

```powershell
cd C:\project\keuangan_kos
php -S localhost:8000 -t .
```

Buka aplikasi melalui URL:

```text
http://localhost:8000/keuangan_kos.html
```

Jangan hanya membuka `http://localhost:8000/` karena project tidak memakai `index.html`.

Jika perubahan JavaScript belum terlihat, lakukan hard refresh browser dengan `Ctrl + F5`.

## 7. Struktur Database

Database utama bernama `sikukos` dan terdiri dari tabel berikut:

- `users`: menyimpan akun login pemilik kos.
- `kamar`: menyimpan nomor kamar, penyewa, status kamar, harga sewa, dan status pembayaran dasar.
- `kategori`: menyimpan kategori transaksi, misalnya `Sewa Bulanan`, `Wi-Fi`, `Listrik & Air`.
- `transaksi`: menyimpan semua pemasukan dan pengeluaran.

Relasi utamanya:

- `transaksi.id_user` terhubung ke `users.id_user`.
- `transaksi.id_kamar` terhubung ke `kamar.id_kamar`, boleh `NULL` untuk pengeluaran umum.
- `transaksi.id_kategori` terhubung ke `kategori.id_kategori`.

## 8. Alur Sistem

Alur aplikasi dimulai dari login. Frontend mengirim username dan password ke endpoint `POST /login`. Backend mencari user di tabel `users`, lalu memverifikasi password menggunakan `password_verify()`.

Password default demo adalah `kos_123`. Pada seed, password default disimpan dengan format `sha256:` agar SQL dapat mengisi password tanpa membuat hash manual. Setelah login berhasil, backend otomatis menyimpan ulang password tersebut dengan `password_hash()`. Jika database masih memakai hash lama dari password demo `123`, login memakai `kos_123` tetap diterima lalu backend menyimpan ulang password ke default baru.

Setelah login berhasil, frontend memanggil data master dari API:

- `GET /kamar`
- `GET /kategori`

Data tersebut disimpan sementara di variabel JavaScript `kamarData` dan `kategoriData`, lalu digunakan untuk mengisi tampilan kamar, dropdown kamar, dan dropdown kategori.

Setiap halaman tidak mengambil data dari dummy JavaScript lagi. Angka dashboard, ringkasan, riwayat, dan laporan dihitung dari tabel MySQL melalui API.

Catatan penting untuk alur pembayaran: status pembayaran sewa yang tampil di aplikasi tidak hanya membaca kolom `kamar.status_pembayaran`. Aplikasi juga menghitung ulang total pembayaran dari tabel `transaksi` dengan kategori `Sewa Bulanan`, lalu menentukan apakah kamar `lunas`, `sebagian`, atau `belum_bayar` untuk bulan yang sedang dilihat.

## 9. Fitur Utama

### Login

Login memakai akun dari tabel `users`. Password di database disimpan dalam bentuk hash, bukan teks biasa.

Di sidebar bagian bawah, setelah login, tersedia dua aksi akun:



Fitur ubah password memakai endpoint `POST /password/change`, sedangkan reset default memakai endpoint `POST /password/reset-default`. Password baru disimpan memakai `password_hash()`.

### Dashboard

Dashboard menampilkan data bulan berjalan:

- Total uang masuk bulan ini
- Total uang keluar bulan ini
- Laba atau rugi bulan ini
- Jumlah kamar terisi dibanding total kamar
- Grafik uang masuk vs uang keluar per rentang tanggal
- Lima transaksi terbaru bulan ini
- Arahan tindakan jika ada kamar yang belum lunas bulan ini

Data dashboard berasal dari endpoint:

```text
GET /dashboard?bulan=YYYY-MM
```

### Data Kamar

Halaman ini menampilkan statistik kamar dan kartu kamar. Total kamar dihitung dari jumlah row tabel `kamar`, bukan angka statis. Jika tabel kamar kosong, total akan menjadi `0`.

Di halaman Data Kamar, status pembayaran diberi keterangan singkat agar orang awam paham arti `Lunas`, `Sebagian`, dan `Belum Bayar`. Pada form tambah/edit kamar, status pembayaran ditampilkan sebagai informasi otomatis dari transaksi sewa, bukan input utama yang harus dipilih manual.

Status kamar:

- `kosong`: belum ada penyewa
- `terisi + lunas`: pembayaran sudah lunas
- `terisi + sebagian`: pembayaran masuk sebagian dan masih ada sisa tagihan
- `terisi + belum_bayar`: masuk tunggakan

Status `sebagian` adalah status hasil perhitungan API. Database tetap menyimpan status dasar `lunas` atau `belum_bayar`, sedangkan nominal detail seperti `total_bayar`, `sisa_tagihan`, dan `kredit_bulan_depan` dihitung dari transaksi kategori `Sewa Bulanan`.

### Input Transaksi

Form transaksi mengirim data ke:

```text
POST /transaksi
```

Kategori transaksi mengikuti jenis catatan:

- Uang Masuk: `Sewa Bulanan`, `Lain-lain`
- Uang Keluar: `Wi-Fi`, `Listrik & Air`, `Perbaikan Fasilitas`, `Lain-lain`

Metode pembayaran tetap hardcode di frontend karena nilainya sudah dibatasi juga di database.

Perbedaan alur transaksi:

- Pembayaran penghuni: pilih `Uang Masuk`, kategori `Sewa Bulanan`, lalu pilih kamar. Alur ini memengaruhi status pembayaran kamar.
- Operasional kos: pilih `Uang Keluar`, lalu pilih kategori seperti `Wi-Fi`, `Listrik & Air`, atau `Perbaikan Fasilitas`. Alur ini dicatat sebagai biaya operasional kos.

Kolom `Catatan / Keterangan` boleh dikosongkan. Jika kosong, frontend otomatis membuat keterangan berdasarkan pilihan jenis catatan, kategori, kamar, dan bulan transaksi. Contoh: `Sewa Bulanan Kamar 01 - Mei 2026` atau `Biaya Wi-Fi`.

Aturan khusus pembayaran sewa:

- Kategori `Sewa Bulanan` wajib memilih kamar.
- Hanya transaksi `pemasukan` dengan kategori `Sewa Bulanan` yang memengaruhi status pembayaran kamar.
- Bulan tagihan diambil dari bulan pada field `Tanggal Transaksi`.
- Jika ingin mencatat pembayaran sewa Mei, tanggal transaksi harus berada di bulan Mei, misalnya `2026-05-01` sampai `2026-05-31`.
- Saat memilih `Uang Masuk`, kategori `Sewa Bulanan`, dan nomor kamar, form menampilkan panel tagihan sewa.
- Panel tagihan sewa menampilkan periode tagihan, harga sewa, nominal yang sudah dibayar, sisa tagihan, status setelah input, dan titipan bulan berikutnya.

Jika nominal pembayaran lebih besar dari sisa tagihan bulan tersebut, kelebihan pembayaran tidak hilang. Kelebihan itu dicatat sebagai `Titipan Bulan Berikutnya` di tampilan. Secara teknis, titipan dihitung dari akumulasi pembayaran sewa yang melebihi tagihan bulan berjalan, lalu otomatis mengurangi tagihan bulan berikutnya.

Ringkasan `Input Hari Ini` memakai tanggal sistem hari ini, bukan tanggal pada field `Tanggal Transaksi`. Jadi perubahan tanggal transaksi hanya untuk data transaksi yang akan disimpan, sedangkan card ringkasan tetap menunjukkan transaksi untuk hari berjalan.

Validasi form dibuat spesifik agar mudah dipahami. Contoh pesan: `Pilih tanggal transaksi.`, `Pilih jenis catatan.`, `Pilih kategori.`, `Masukkan jumlah lebih dari Rp 0.`, atau `Pilih kamar untuk pembayaran sewa.`

Flow pembayaran sewa saat demo:

1. Buka halaman `Input Transaksi`.
2. Pilih `Tanggal Transaksi` sesuai bulan tagihan. Contoh pembayaran sewa Mei memakai tanggal di bulan Mei.
3. Pilih jenis catatan `Uang Masuk`.
4. Pilih kategori `Sewa Bulanan`.
5. Pilih nomor kamar.
6. Sistem menampilkan panel tagihan sewa untuk kamar dan periode tersebut.
7. Isi nominal pembayaran dan keterangan.
8. Setelah transaksi disimpan, sistem menghitung ulang status kamar:
   - `Belum Bayar` jika belum ada pembayaran sewa pada bulan itu.
   - `Sebagian` jika pembayaran sudah masuk tetapi belum mencapai harga sewa.
   - `Lunas` jika pembayaran sudah sama atau lebih besar dari harga sewa.
9. Jika nominal melebihi sisa tagihan, kelebihan tampil sebagai `Titipan Bulan Berikutnya` dan mengurangi tagihan bulan setelahnya.

### Riwayat Transaksi

Daftar transaksi mengambil data dari:

```text
GET /transaksi?jenis=pemasukan&kategori=1&page=1&limit=8
```

Fitur yang tersedia:

- Filter semua catatan, uang masuk, atau uang keluar
- Filter kategori transaksi
- Pagination 8 transaksi per halaman
- Total pemasukan, pengeluaran, dan saldo bersih berdasarkan filter aktif

### Laporan Keuangan

Laporan mengambil data dari:

```text
GET /laporan?bulan=YYYY-MM
```

Laporan menampilkan:

- Subtitle periode laporan sesuai bulan yang dipilih
- Total pendapatan per kategori
- Total pengeluaran per kategori
- Laba/rugi bulan yang dipilih dengan label `Laba Bersih Bulan Ini` atau `Rugi Bersih Bulan Ini`
- Saldo awal bulan
- Saldo akhir bulan
- Catatan bahwa saldo awal dihitung dari transaksi sebelum bulan laporan
- Status pembayaran kamar pada bulan tersebut

Saldo awal bulan dihitung dari semua transaksi sebelum tanggal awal bulan yang dipilih:

```text
saldo_awal = total pemasukan sebelum bulan ini - total pengeluaran sebelum bulan ini
```

Status pembayaran kamar pada laporan dihitung dari transaksi kategori `Sewa Bulanan` pada bulan laporan. Jika pembayaran belum mencapai harga sewa, status menjadi `Sebagian` atau `Belum Bayar`. Pada tabel status kamar, status `Sebagian` dan `Belum Bayar` juga menampilkan sisa tagihan agar alasan status lebih jelas tanpa menambah banyak kolom baru.

Dropdown bulan laporan tidak hardcode. Frontend mengambil daftar bulan dari endpoint `GET /laporan/bulan`, berdasarkan bulan yang ada di tabel `transaksi`, lalu tetap menambahkan bulan berjalan jika belum ada transaksi bulan ini.

Saat tombol `Cetak Laporan` ditekan, CSS print hanya menampilkan halaman laporan. Sidebar, topbar, tombol, dan dropdown disembunyikan.

## 10. Validasi dan Keamanan

Backend memakai validasi di `api/helpers/validation.php` untuk memastikan input sesuai format. Contohnya:

- Harga sewa kamar wajib bernilai positif dan input frontend menerima nominal rupiah biasa, misalnya `120000`.

- Tanggal harus format `YYYY-MM-DD`
- Nominal harus angka positif
- Jenis transaksi hanya `pemasukan` atau `pengeluaran`
- Metode pembayaran hanya nilai yang diizinkan
- Kategori harus sesuai dengan jenis transaksi
- Password baru minimal 6 karakter
- Konfirmasi password baru harus sama

Query database memakai PDO prepared statement untuk mengurangi risiko SQL injection. Frontend juga memakai `escapeHtml()` sebelum menampilkan teks dari database agar input pengguna tidak langsung dirender sebagai HTML.

## 11. Endpoint API Penting

```text
POST /login
POST /password/change
POST /password/reset-default
GET  /kamar
GET  /kategori
GET  /dashboard?bulan=YYYY-MM
POST /transaksi
GET  /ringkasan-hari-ini?tanggal=YYYY-MM-DD
GET  /sewa/status?id_kamar=ID&tanggal=YYYY-MM-DD
GET  /transaksi?jenis=&kategori=&page=&limit=
GET  /laporan?bulan=YYYY-MM
GET  /laporan/bulan
```

Frontend memanggil endpoint melalui `database.js` dengan base URL:

```javascript
const API_BASE_URL = 'api/index.php';
```

## 12. Cara Menjelaskan Flow Saat Presentasi

Urutan penjelasan yang disarankan:

1. Jelaskan bahwa data tersimpan di MySQL, bukan dummy JavaScript.
2. Tunjukkan tabel utama: `users`, `kamar`, `kategori`, `transaksi`.
3. Jelaskan frontend memanggil PHP API lewat `fetch()`.
4. Jelaskan API mengambil data dari MySQL memakai PDO.
5. Tunjukkan login, dashboard, input transaksi, riwayat, dan laporan.
6. Tekankan bahwa semua angka dihitung dari database sesuai filter tanggal atau bulan.
7. Saat menjelaskan pembayaran sewa, tekankan bahwa kategori `Sewa Bulanan` menjadi dasar status kamar.
8. Jelaskan status pembayaran:
   - `Belum Bayar`: belum ada pembayaran sewa untuk bulan tersebut.
   - `Sebagian`: pembayaran masuk, tetapi belum mencapai harga sewa.
   - `Lunas`: total pembayaran sudah sama atau lebih besar dari harga sewa.
   - `Titipan Bulan Berikutnya`: kelebihan bayar yang otomatis mengurangi tagihan bulan depan.
9. Saat menjelaskan laporan, tunjukkan saldo, laba-rugi, dan status pembayaran kamar per bulan.
10. Jelaskan fitur akun di sidebar bawah: admin dapat mengubah password setelah login atau reset password ke default `kos_123`.

Dengan alur ini, audiens dapat memahami bahwa aplikasi sudah memiliki pemisahan sederhana antara frontend, backend API, dan database.
