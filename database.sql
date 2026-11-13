-- database.sql
-- Import file ini melalui phpMyAdmin (tab "Import").

CREATE DATABASE IF NOT EXISTS penelitian_dosen_fip
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE penelitian_dosen_fip;

-- ---------------------------------------------------
-- Tabel users: akun login & registrasi
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_lengkap  VARCHAR(100) NOT NULL,
    username      VARCHAR(30)  NOT NULL,
    password      VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- Tabel penelitian: data penelitian dosen
-- user_id = akun yang menginput data (relasi ke users)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS penelitian (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    nama_dosen          VARCHAR(150) NOT NULL,
    nip_nidn            VARCHAR(25)  NOT NULL,
    judul_penelitian    VARCHAR(500) NOT NULL,
    dana                DECIMAL(15,2) NOT NULL DEFAULT 0,
    halaman_pengesahan  VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_penelitian_nama (nama_dosen),
    KEY idx_penelitian_user (user_id),
    CONSTRAINT fk_penelitian_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- Tabel perpustakaan: transaksi peminjaman & pengembalian buku
-- user_id = akun yang menginput data (relasi ke users)
-- Aman diimpor ulang (CREATE TABLE IF NOT EXISTS) tanpa menghapus data lama.
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS perpustakaan (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NOT NULL,
    nama               VARCHAR(150) NOT NULL,
    nim                VARCHAR(20)  NOT NULL,
    jenis_transaksi    ENUM('Meminjam Buku','Mengembalikan Buku') NOT NULL,
    judul_buku         VARCHAR(255) NOT NULL,
    tanggal_transaksi  DATE NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_perpus_nama (nama),
    KEY idx_perpus_nim (nim),
    KEY idx_perpus_jenis (jenis_transaksi),
    KEY idx_perpus_user (user_id),
    CONSTRAINT fk_perpus_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
