CREATE DATABASE IF NOT EXISTS sikukos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sikukos;

CREATE TABLE IF NOT EXISTS users (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kamar (
  id_kamar INT AUTO_INCREMENT PRIMARY KEY,
  nomor_kamar VARCHAR(10) NOT NULL UNIQUE,
  nama_penyewa VARCHAR(100) NULL,
  status_kamar VARCHAR(20) NOT NULL,
  harga_sewa DECIMAL(12,2) NULL,
  status_pembayaran VARCHAR(20) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_kamar_status
    CHECK (status_kamar IN ('terisi', 'kosong')),
  CONSTRAINT chk_kamar_status_pembayaran
    CHECK (status_pembayaran IN ('lunas', 'belum_bayar'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kategori (
  id_kategori INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL UNIQUE,
  tipe_kategori VARCHAR(30) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_kategori_tipe
    CHECK (tipe_kategori IN ('pemasukan', 'pengeluaran', 'keduanya'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaksi (
  id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  id_kamar INT NULL,
  id_kategori INT NOT NULL,
  tanggal DATE NOT NULL,
  tipe_transaksi VARCHAR(30) NOT NULL,
  nominal DECIMAL(12,2) NOT NULL,
  metode_pembayaran VARCHAR(50) NOT NULL,
  keterangan TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_transaksi_user
    FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON UPDATE CASCADE,
  CONSTRAINT fk_transaksi_kamar
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_transaksi_kategori
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori)
    ON UPDATE CASCADE,
  CONSTRAINT chk_transaksi_tipe
    CHECK (tipe_transaksi IN ('pemasukan', 'pengeluaran')),
  CONSTRAINT chk_transaksi_nominal
    CHECK (nominal > 0),
  CONSTRAINT chk_transaksi_metode
    CHECK (metode_pembayaran IN ('Tunai', 'Transfer Bank', 'QRIS', 'OVO / GoPay')),
  INDEX idx_transaksi_tanggal (tanggal),
  INDEX idx_transaksi_tipe (tipe_transaksi),
  INDEX idx_transaksi_kategori (id_kategori),
  INDEX idx_transaksi_kamar (id_kamar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
