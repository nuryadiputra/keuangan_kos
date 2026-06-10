# Test Case - SiKuKos
**Nama:** Imam Mutashawwir  
**NIM:** 701240188  
**Modul yang Diuji:** Autentikasi (Login & Logout)

---

## TC-01: Login dengan kredensial yang benar

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-01 |
| **Nama Test** | Login berhasil dengan username & password valid |
| **Kondisi Awal** | Pengguna berada di halaman login, belum login |
| **Langkah Pengujian** | 1. Buka halaman login SiKuKos <br> 2. Masukkan username: `pemilik_kos` <br> 3. Masukkan password: `123` <br> 4. Klik tombol "Masuk ke Sistem" |
| **Hasil yang Diharapkan** | Sistem berhasil memvalidasi kredensial dan mengarahkan pengguna ke halaman Dashboard |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-02: Login dengan password yang salah

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-02 |
| **Nama Test** | Login gagal dengan password tidak valid |
| **Kondisi Awal** | Pengguna berada di halaman login |
| **Langkah Pengujian** | 1. Buka halaman login SiKuKos <br> 2. Masukkan username: `pemilik_kos` <br> 3. Masukkan password: `passwordsalah` <br> 4. Klik tombol "Masuk ke Sistem" |
| **Hasil yang Diharapkan** | Sistem menolak akses dan menampilkan pesan error "Username atau Password salah" |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |

---

## TC-03: Logout dari sistem

| Field | Detail |
|-------|--------|
| **ID Test Case** | TC-03 |
| **Nama Test** | Logout berhasil mengakhiri sesi pengguna |
| **Kondisi Awal** | Pengguna sudah login dan berada di halaman Dashboard |
| **Langkah Pengujian** | 1. Klik tombol "Keluar" di pojok kanan atas <br> 2. Konfirmasi logout jika ada dialog |
| **Hasil yang Diharapkan** | Sesi pengguna berakhir dan sistem mengarahkan kembali ke halaman Login |
| **Hasil Aktual** | *(Diisi saat pengujian)* |
| **Status** | *(Lulus / Gagal)* |
