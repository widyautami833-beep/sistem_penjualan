-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Nov 2026 pada 06.44
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_toko`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CariPelanggan` (IN `p_id` INT)   BEGIN
    SELECT *
    FROM tbl_pelanggan
    WHERE id_pelanggan = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `HitungPelanggan` (OUT `jumlah` INT)   BEGIN
    SELECT COUNT(*)
    INTO jumlah
    FROM tbl_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LaporanTransaksi` (IN `p_id_pelanggan` INT)   BEGIN
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        p.kota,
        t.total_belanja
    FROM tbl_transaksi t
    INNER JOIN tbl_pelanggan p
    ON t.id_pelanggan = p.id_pelanggan
    WHERE p.id_pelanggan = p_id_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `TambahPelanggan` (IN `p_nama` VARCHAR(100), IN `p_kota` VARCHAR(50))   BEGIN
    INSERT INTO tbl_pelanggan
    (nama_pelanggan, kota)
    VALUES
    (p_nama, p_kota);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `TampilPelanggan` ()   BEGIN
    SELECT * FROM tbl_pelanggan;
END$$

--
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `HitungDiskon` (`harga` DECIMAL(15,2), `diskon` DECIMAL(5,2)) RETURNS DECIMAL(15,2) DETERMINISTIC BEGIN
    DECLARE hasil DECIMAL(15,2);

    SET hasil = harga - (harga * diskon / 100);

    RETURN hasil;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungJumlahTransaksi` (`p_id_pelanggan` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE jumlah INT;

    SELECT COUNT(*)
    INTO jumlah
    FROM tbl_transaksi
    WHERE id_pelanggan = p_id_pelanggan;

    RETURN jumlah;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungPajak` (`harga` DECIMAL(15,2)) RETURNS DECIMAL(15,2) DETERMINISTIC BEGIN
    DECLARE pajak DECIMAL(15,2);

    SET pajak = harga * 0.11;

    RETURN pajak;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungTotal` (`harga` DECIMAL(15,2), `jumlah` INT) RETURNS DECIMAL(15,2) DETERMINISTIC BEGIN
    DECLARE hasil DECIMAL(15,2);

    SET hasil = harga * jumlah;

    RETURN hasil;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungTotalBelanja` (`p_id_pelanggan` INT) RETURNS DECIMAL(15,2) DETERMINISTIC BEGIN
    DECLARE total DECIMAL(15,2);

    SELECT COALESCE(SUM(total_belanja), 0)
    INTO total
    FROM tbl_transaksi
    WHERE id_pelanggan = p_id_pelanggan;

    RETURN total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KategoriBelanja` (`p_total` DECIMAL(15,2)) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
    DECLARE kategori VARCHAR(20);

    IF p_total >= 1000000 THEN
        SET kategori = 'Besar';
    ELSEIF p_total >= 500000 THEN
        SET kategori = 'Sedang';
    ELSE
        SET kategori = 'Kecil';
    END IF;

    RETURN kategori;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KonversiNilai` (`nilai` INT) RETURNS CHAR(1) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
    DECLARE hasil CHAR(1);

    IF nilai < 0 OR nilai > 100 THEN
        SET hasil = 'X';
    ELSEIF nilai >= 90 THEN
        SET hasil = 'A';
    ELSEIF nilai >= 80 THEN
        SET hasil = 'B';
    ELSEIF nilai >= 70 THEN
        SET hasil = 'C';
    ELSEIF nilai >= 60 THEN
        SET hasil = 'D';
    ELSE
        SET hasil = 'E';
    END IF;

    RETURN hasil;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_hapus_pelanggan`
--

CREATE TABLE `log_hapus_pelanggan` (
  `id_log` int(11) NOT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `nama_pelanggan` varchar(100) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL,
  `waktu_hapus` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_hapus_pelanggan`
--

INSERT INTO `log_hapus_pelanggan` (`id_log`, `id_pelanggan`, `nama_pelanggan`, `kota`, `waktu_hapus`) VALUES
(1, 15, 'Pelanggan Percobaan', 'Ponorogo', '2026-11-09 10:59:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_transaksi`
--

CREATE TABLE `log_transaksi` (
  `id_log` int(11) NOT NULL,
  `id_transaksi` int(11) DEFAULT NULL,
  `aktivitas` varchar(50) DEFAULT NULL,
  `waktu` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_transaksi`
--

INSERT INTO `log_transaksi` (`id_log`, `id_transaksi`, `aktivitas`, `waktu`) VALUES
(1, 21, 'Transaksi ditambahkan', '2026-11-09 10:58:20');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_cabang`
--

CREATE TABLE `tbl_cabang` (
  `id_cabang` int(11) NOT NULL,
  `nama_cabang` varchar(100) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_cabang`
--

INSERT INTO `tbl_cabang` (`id_cabang`, `nama_cabang`, `kota`) VALUES
(1, 'Cabang Utama', 'Ponorogo'),
(2, 'Cabang Madiun', 'Madiun'),
(3, 'Cabang Surabaya', 'Surabaya'),
(4, 'Cabang Pacitan', 'Pacitan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_detail_transaksi`
--

CREATE TABLE `tbl_detail_transaksi` (
  `id_detail` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_detail_transaksi`
--

INSERT INTO `tbl_detail_transaksi` (`id_detail`, `id_transaksi`, `nama_produk`, `jumlah`, `harga`) VALUES
(1, 1, 'Laptop', 1, 250000.00),
(2, 2, 'Mouse', 2, 175000.00),
(3, 3, 'Keyboard', 1, 150000.00),
(4, 4, 'Monitor', 1, 500000.00),
(5, 5, 'Flashdisk', 5, 55000.00),
(6, 6, 'Printer', 1, 400000.00),
(7, 7, 'Mouse', 1, 175000.00),
(8, 8, 'Laptop', 1, 600000.00),
(9, 9, 'Keyboard', 2, 162500.00),
(10, 10, 'Printer', 1, 450000.00),
(11, 11, 'Monitor', 1, 550000.00),
(12, 12, 'Mouse', 1, 225000.00),
(13, 13, 'Flashdisk', 1, 125000.00),
(14, 14, 'Laptop', 1, 700000.00),
(15, 15, 'Keyboard', 2, 150000.00),
(16, 16, 'Mouse', 1, 275000.00),
(17, 17, 'Printer', 2, 400000.00),
(18, 18, 'Flashdisk', 3, 50000.00),
(19, 19, 'Monitor', 1, 350000.00),
(20, 20, 'Laptop', 1, 425000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_pelanggan`
--

CREATE TABLE `tbl_pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `kota` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_pelanggan`
--

INSERT INTO `tbl_pelanggan` (`id_pelanggan`, `nama_pelanggan`, `kota`) VALUES
(1, 'Andi', 'Surabaya'),
(2, 'Budi', 'Madiun'),
(3, 'Citra', 'Surabaya'),
(4, 'Dewi', 'Ponorogo'),
(5, 'Eko', 'Magetan'),
(6, 'Fajar', 'Madiun'),
(7, 'Gita', 'Surabaya'),
(8, 'Hani', 'Ponorogo'),
(9, 'Indra', 'Ngawi'),
(10, 'Joko', 'Magetan'),
(11, 'Dewi Lestari', 'Ponorogo'),
(12, 'Rina', 'Madiun'),
(13, 'Rina', 'Madiun'),
(14, 'Rina', 'Madiun');

--
-- Trigger `tbl_pelanggan`
--
DELIMITER $$
CREATE TRIGGER `SebelumTambahPelanggan` BEFORE INSERT ON `tbl_pelanggan` FOR EACH ROW BEGIN
    IF NEW.nama_pelanggan IS NULL
       OR TRIM(NEW.nama_pelanggan) = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nama pelanggan tidak boleh kosong';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `SebelumUpdatePelanggan` BEFORE UPDATE ON `tbl_pelanggan` FOR EACH ROW BEGIN
    IF NEW.nama_pelanggan IS NULL
       OR TRIM(NEW.nama_pelanggan) = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nama pelanggan tidak boleh kosong';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `SetelahHapusPelanggan` AFTER DELETE ON `tbl_pelanggan` FOR EACH ROW BEGIN
    INSERT INTO log_hapus_pelanggan
        (id_pelanggan, nama_pelanggan, kota)
    VALUES
        (OLD.id_pelanggan, OLD.nama_pelanggan, OLD.kota);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi`
--

CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `total_belanja` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi`
--

INSERT INTO `tbl_transaksi` (`id_transaksi`, `tanggal`, `id_pelanggan`, `total_belanja`) VALUES
(1, '2026-10-01', 1, 250000.00),
(2, '2026-10-02', 2, 350000.00),
(3, '2026-10-03', 1, 150000.00),
(4, '2026-10-04', 3, 500000.00),
(5, '2026-10-05', 4, 275000.00),
(6, '2026-10-06', 5, 400000.00),
(7, '2026-10-07', 6, 175000.00),
(8, '2026-10-08', 7, 600000.00),
(9, '2026-10-09', 8, 325000.00),
(10, '2026-10-10', 9, 450000.00),
(11, '2026-10-11', 10, 550000.00),
(12, '2026-10-12', 2, 225000.00),
(13, '2026-10-13', 3, 125000.00),
(14, '2026-10-14', 4, 700000.00),
(15, '2026-10-15', 5, 300000.00),
(16, '2026-10-16', 6, 275000.00),
(17, '2026-10-17', 7, 800000.00),
(18, '2026-10-18', 8, 150000.00),
(19, '2026-10-19', 1, 350000.00),
(20, '2026-10-20', 10, 425000.00),
(21, '2026-11-09', 1, 150000.00);

--
-- Trigger `tbl_transaksi`
--
DELIMITER $$
CREATE TRIGGER `CekTotalTransaksi` BEFORE INSERT ON `tbl_transaksi` FOR EACH ROW BEGIN
    IF NEW.total_belanja < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `SetelahTambahTransaksi` AFTER INSERT ON `tbl_transaksi` FOR EACH ROW BEGIN
    INSERT INTO log_transaksi
        (id_transaksi, aktivitas)
    VALUES
        (NEW.id_transaksi, 'Transaksi ditambahkan');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_total_belanja`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_total_belanja` (
`id_pelanggan` int(11)
,`nama_pelanggan` varchar(100)
,`total_transaksi` bigint(21)
,`total_belanja` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_transaksi_pelanggan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_transaksi_pelanggan` (
`id_transaksi` int(11)
,`tanggal` date
,`nama_pelanggan` varchar(100)
,`kota` varchar(50)
,`total_belanja` decimal(12,2)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `view_total_belanja`
--
DROP TABLE IF EXISTS `view_total_belanja`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_total_belanja`  AS SELECT `p`.`id_pelanggan` AS `id_pelanggan`, `p`.`nama_pelanggan` AS `nama_pelanggan`, count(`t`.`id_transaksi`) AS `total_transaksi`, coalesce(sum(`t`.`total_belanja`),0) AS `total_belanja` FROM (`tbl_pelanggan` `p` left join `tbl_transaksi` `t` on(`p`.`id_pelanggan` = `t`.`id_pelanggan`)) GROUP BY `p`.`id_pelanggan`, `p`.`nama_pelanggan` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_transaksi_pelanggan`
--
DROP TABLE IF EXISTS `view_transaksi_pelanggan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_transaksi_pelanggan`  AS SELECT `t`.`id_transaksi` AS `id_transaksi`, `t`.`tanggal` AS `tanggal`, `p`.`nama_pelanggan` AS `nama_pelanggan`, `p`.`kota` AS `kota`, `t`.`total_belanja` AS `total_belanja` FROM (`tbl_transaksi` `t` join `tbl_pelanggan` `p` on(`t`.`id_pelanggan` = `p`.`id_pelanggan`)) ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `log_hapus_pelanggan`
--
ALTER TABLE `log_hapus_pelanggan`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `log_transaksi`
--
ALTER TABLE `log_transaksi`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `tbl_cabang`
--
ALTER TABLE `tbl_cabang`
  ADD PRIMARY KEY (`id_cabang`);

--
-- Indeks untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indeks untuk tabel `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indeks untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `log_hapus_pelanggan`
--
ALTER TABLE `log_hapus_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `log_transaksi`
--
ALTER TABLE `log_transaksi`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `tbl_cabang`
--
ALTER TABLE `tbl_cabang`
  MODIFY `id_cabang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD CONSTRAINT `tbl_detail_transaksi_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `tbl_transaksi` (`id_transaksi`);

--
-- Ketidakleluasaan untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD CONSTRAINT `tbl_transaksi_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
