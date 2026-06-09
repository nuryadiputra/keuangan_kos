USE sikukos;

INSERT INTO users (id_user, nama, username, password, created_at) VALUES
(1, 'Pemilik Kos', 'pemilik_kos', CONCAT('sha256:', SHA2('kos_123', 256)), NOW())
ON DUPLICATE KEY UPDATE
  nama = VALUES(nama),
  password = VALUES(password);

INSERT INTO kamar
  (id_kamar, nomor_kamar, nama_penyewa, status_kamar, harga_sewa, status_pembayaran)
VALUES
  (1, '01', 'Diya', 'terisi', 600000.00, 'lunas'),
  (2, '02', 'Qalbi Nura', 'terisi', 600000.00, 'lunas'),
  (3, '03', 'Diko', 'terisi', 600000.00, 'belum_bayar'),
  (4, '04', 'Darussalam', 'terisi', 600000.00, 'lunas'),
  (5, '05', 'Dayat', 'terisi', 600000.00, 'lunas'),
  (6, '06', 'Al-Ikhsan', 'terisi', 600000.00, 'belum_bayar'),
  (7, '07', 'Ijan', 'terisi', 600000.00, 'belum_bayar'),
  (8, '08', 'Maisan', 'terisi', 600000.00, 'belum_bayar')
ON DUPLICATE KEY UPDATE
  nomor_kamar = VALUES(nomor_kamar),
  nama_penyewa = VALUES(nama_penyewa),
  status_kamar = VALUES(status_kamar),
  harga_sewa = VALUES(harga_sewa),
  status_pembayaran = VALUES(status_pembayaran);

INSERT INTO kategori (id_kategori, nama_kategori, tipe_kategori) VALUES
  (1, 'Sewa Bulanan', 'pemasukan'),
  (2, 'Wi-Fi', 'pengeluaran'),
  (3, 'Listrik & Air', 'pengeluaran'),
  (4, 'Perbaikan Fasilitas', 'pengeluaran'),
  (5, 'Lain-lain', 'keduanya')
ON DUPLICATE KEY UPDATE
  nama_kategori = VALUES(nama_kategori),
  tipe_kategori = VALUES(tipe_kategori);

INSERT INTO transaksi
  (
    id_transaksi,
    id_user,
    id_kamar,
    id_kategori,
    tanggal,
    tipe_transaksi,
    nominal,
    metode_pembayaran,
    keterangan
  )
VALUES
  (1, 1, 1, 1, '2026-05-01', 'pemasukan', 600000.00, 'Transfer Bank', 'Sewa Mei 2026 - Budi Santoso'),
  (2, 1, 2, 1, '2026-05-01', 'pemasukan', 600000.00, 'QRIS', 'Sewa Mei 2026 - Sari Dewi'),
  (3, 1, 4, 1, '2026-05-02', 'pemasukan', 600000.00, 'Transfer Bank', 'Sewa Mei 2026 - Rina Wati'),
  (4, 1, 5, 1, '2026-05-02', 'pemasukan', 600000.00, 'Tunai', 'Sewa Mei 2026 - Doni Pratama'),
  (5, 1, NULL, 3, '2026-05-03', 'pengeluaran', 350000.00, 'Transfer Bank', 'Tagihan listrik April 2026'),
  (6, 1, 6, 4, '2026-05-10', 'pengeluaran', 85000.00, 'Tunai', 'Perbaikan kran kamar mandi'),
  (7, 1, 7, 1, '2026-05-12', 'pemasukan', 600000.00, 'OVO / GoPay', 'Sewa Mei 2026 - Laila Nur (terlambat)'),
  (8, 1, NULL, 3, '2026-05-14', 'pengeluaran', 45000.00, 'Tunai', 'Isi ulang air galon lobby')
ON DUPLICATE KEY UPDATE
  id_user = VALUES(id_user),
  id_kamar = VALUES(id_kamar),
  id_kategori = VALUES(id_kategori),
  tanggal = VALUES(tanggal),
  tipe_transaksi = VALUES(tipe_transaksi),
  nominal = VALUES(nominal),
  metode_pembayaran = VALUES(metode_pembayaran),
  keterangan = VALUES(keterangan);
