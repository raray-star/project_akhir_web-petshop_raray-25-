-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 25, 2026 at 11:37 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pet_shop`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking_grooming`
--

CREATE TABLE `booking_grooming` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `hewan_id` int NOT NULL,
  `layanan_id` int NOT NULL,
  `jadwal_id` int NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` enum('Menunggu Pembayaran','Menunggu Konfirmasi','Dikonfirmasi','Sedang Grooming','Selesai','Dibatalkan') DEFAULT 'Menunggu Pembayaran',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `booking_grooming`
--

INSERT INTO `booking_grooming` (`id`, `user_id`, `hewan_id`, `layanan_id`, `jadwal_id`, `total`, `status`, `created_at`) VALUES
(1, 2, 1, 3, 13, '20000.00', 'Selesai', '2026-10-21 13:17:11'),
(2, 2, 1, 1, 14, '50000.00', 'Selesai', '2026-10-21 13:35:43'),
(3, 2, 1, 2, 18, '80000.00', 'Selesai', '2026-10-23 14:21:12'),
(4, 2, 1, 3, 25, '20000.00', 'Selesai', '2026-10-25 09:43:43');

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` int NOT NULL,
  `pesanan_id` int NOT NULL,
  `produk_id` int NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `jumlah` int NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id`, `pesanan_id`, `produk_id`, `harga`, `jumlah`, `subtotal`) VALUES
(23, 22, 3, '18000.00', 1, '18000.00'),
(25, 24, 7, '12000.00', 1, '12000.00'),
(26, 25, 8, '20000.00', 1, '20000.00'),
(27, 26, 8, '20000.00', 1, '20000.00'),
(28, 27, 8, '20000.00', 1, '20000.00');

-- --------------------------------------------------------

--
-- Table structure for table `hewan`
--

CREATE TABLE `hewan` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nama_hewan` varchar(100) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `ras` varchar(100) DEFAULT NULL,
  `umur` varchar(30) DEFAULT NULL,
  `jenis_kelamin` enum('Jantan','Betina') DEFAULT NULL,
  `catatan` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hewan`
--

INSERT INTO `hewan` (`id`, `user_id`, `nama_hewan`, `jenis`, `ras`, `umur`, `jenis_kelamin`, `catatan`) VALUES
(1, 2, 'Hewan Peliharaan', 'Kucing', 'persia', '1', 'Betina', '');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_grooming`
--

CREATE TABLE `jadwal_grooming` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `status` enum('Tersedia','Dibooking') DEFAULT 'Tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jadwal_grooming`
--

INSERT INTO `jadwal_grooming` (`id`, `tanggal`, `jam`, `status`) VALUES
(1, '2026-10-07', '09:00:00', 'Tersedia'),
(2, '2026-10-07', '10:30:00', 'Tersedia'),
(3, '2026-10-07', '13:00:00', 'Tersedia'),
(4, '2026-10-07', '14:30:00', 'Tersedia'),
(5, '2026-10-08', '09:00:00', 'Tersedia'),
(6, '2026-10-08', '10:30:00', 'Tersedia'),
(7, '2026-10-08', '13:00:00', 'Tersedia'),
(8, '2026-10-08', '14:30:00', 'Tersedia'),
(9, '2026-10-22', '09:00:00', 'Tersedia'),
(10, '2026-10-22', '10:30:00', 'Tersedia'),
(11, '2026-10-22', '13:00:00', 'Tersedia'),
(12, '2026-10-22', '14:30:00', 'Tersedia'),
(13, '2026-10-23', '09:00:00', 'Dibooking'),
(14, '2026-10-23', '10:30:00', 'Dibooking'),
(15, '2026-10-23', '13:00:00', 'Tersedia'),
(16, '2026-10-23', '14:30:00', 'Tersedia'),
(17, '2026-10-24', '09:00:00', 'Tersedia'),
(18, '2026-10-24', '10:30:00', 'Dibooking'),
(19, '2026-10-24', '13:00:00', 'Tersedia'),
(20, '2026-10-24', '14:30:00', 'Tersedia'),
(21, '2026-10-26', '15:00:00', 'Tersedia'),
(22, '2026-10-26', '14:00:00', 'Tersedia'),
(23, '2026-10-26', '13:00:00', 'Tersedia'),
(24, '2026-10-26', '11:00:00', 'Tersedia'),
(25, '2026-10-26', '10:00:00', 'Dibooking'),
(26, '2026-10-26', '09:00:00', 'Tersedia'),
(27, '2026-10-27', '15:00:00', 'Tersedia'),
(28, '2026-10-27', '14:00:00', 'Tersedia'),
(29, '2026-10-27', '13:00:00', 'Tersedia'),
(30, '2026-10-27', '11:00:00', 'Tersedia'),
(31, '2026-10-27', '10:00:00', 'Tersedia'),
(32, '2026-10-27', '09:00:00', 'Tersedia'),
(33, '2026-10-28', '15:00:00', 'Tersedia'),
(34, '2026-10-28', '14:00:00', 'Tersedia'),
(35, '2026-10-28', '13:00:00', 'Tersedia'),
(36, '2026-10-28', '11:00:00', 'Tersedia'),
(37, '2026-10-28', '10:00:00', 'Tersedia'),
(38, '2026-10-28', '09:00:00', 'Tersedia'),
(39, '2026-10-29', '15:00:00', 'Tersedia'),
(40, '2026-10-29', '14:00:00', 'Tersedia'),
(41, '2026-10-29', '13:00:00', 'Tersedia'),
(42, '2026-10-29', '11:00:00', 'Tersedia'),
(43, '2026-10-29', '10:00:00', 'Tersedia'),
(44, '2026-10-29', '09:00:00', 'Tersedia'),
(45, '2026-10-30', '15:00:00', 'Tersedia'),
(46, '2026-10-30', '14:00:00', 'Tersedia'),
(47, '2026-10-30', '13:00:00', 'Tersedia'),
(48, '2026-10-30', '11:00:00', 'Tersedia'),
(49, '2026-10-30', '10:00:00', 'Tersedia'),
(50, '2026-10-30', '09:00:00', 'Tersedia'),
(51, '2026-10-31', '15:00:00', 'Tersedia'),
(52, '2026-10-31', '14:00:00', 'Tersedia'),
(53, '2026-10-31', '13:00:00', 'Tersedia'),
(54, '2026-10-31', '11:00:00', 'Tersedia'),
(55, '2026-10-31', '10:00:00', 'Tersedia'),
(56, '2026-10-31', '09:00:00', 'Tersedia'),
(57, '2026-11-01', '15:00:00', 'Tersedia'),
(58, '2026-11-01', '14:00:00', 'Tersedia'),
(59, '2026-11-01', '13:00:00', 'Tersedia'),
(60, '2026-11-01', '11:00:00', 'Tersedia'),
(61, '2026-11-01', '10:00:00', 'Tersedia'),
(62, '2026-11-01', '09:00:00', 'Tersedia'),
(63, '2026-11-02', '15:00:00', 'Tersedia'),
(64, '2026-11-02', '14:00:00', 'Tersedia'),
(65, '2026-11-02', '13:00:00', 'Tersedia'),
(66, '2026-11-02', '11:00:00', 'Tersedia'),
(67, '2026-11-02', '10:00:00', 'Tersedia'),
(68, '2026-11-02', '09:00:00', 'Tersedia'),
(69, '2026-11-03', '15:00:00', 'Tersedia'),
(70, '2026-11-03', '14:00:00', 'Tersedia'),
(71, '2026-11-03', '13:00:00', 'Tersedia'),
(72, '2026-11-03', '11:00:00', 'Tersedia'),
(73, '2026-11-03', '10:00:00', 'Tersedia'),
(74, '2026-11-03', '09:00:00', 'Tersedia'),
(75, '2026-11-04', '15:00:00', 'Tersedia'),
(76, '2026-11-04', '14:00:00', 'Tersedia'),
(77, '2026-11-04', '13:00:00', 'Tersedia'),
(78, '2026-11-04', '11:00:00', 'Tersedia'),
(79, '2026-11-04', '10:00:00', 'Tersedia'),
(80, '2026-11-04', '09:00:00', 'Tersedia'),
(81, '2026-11-05', '15:00:00', 'Tersedia'),
(82, '2026-11-05', '14:00:00', 'Tersedia'),
(83, '2026-11-05', '13:00:00', 'Tersedia'),
(84, '2026-11-05', '11:00:00', 'Tersedia'),
(85, '2026-11-05', '10:00:00', 'Tersedia'),
(86, '2026-11-05', '09:00:00', 'Tersedia'),
(87, '2026-11-06', '15:00:00', 'Tersedia'),
(88, '2026-11-06', '14:00:00', 'Tersedia'),
(89, '2026-11-06', '13:00:00', 'Tersedia'),
(90, '2026-11-06', '11:00:00', 'Tersedia'),
(91, '2026-11-06', '10:00:00', 'Tersedia'),
(92, '2026-11-06', '09:00:00', 'Tersedia'),
(93, '2026-11-07', '15:00:00', 'Tersedia'),
(94, '2026-11-07', '14:00:00', 'Tersedia'),
(95, '2026-11-07', '13:00:00', 'Tersedia'),
(96, '2026-11-07', '11:00:00', 'Tersedia'),
(97, '2026-11-07', '10:00:00', 'Tersedia'),
(98, '2026-11-07', '09:00:00', 'Tersedia'),
(99, '2026-11-08', '15:00:00', 'Tersedia'),
(100, '2026-11-08', '14:00:00', 'Tersedia'),
(101, '2026-11-08', '13:00:00', 'Tersedia'),
(102, '2026-11-08', '11:00:00', 'Tersedia'),
(103, '2026-11-08', '10:00:00', 'Tersedia'),
(104, '2026-11-08', '09:00:00', 'Tersedia');

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(1, 'Makanan'),
(2, 'Snack'),
(3, 'Vitamin'),
(4, 'Pasir'),
(5, 'Shampo'),
(6, 'Mainan'),
(7, 'Aksesoris'),
(8, 'Tempat Makan'),
(9, 'Kandang');

-- --------------------------------------------------------

--
-- Table structure for table `keranjang`
--

CREATE TABLE `keranjang` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `produk_id` int NOT NULL,
  `jumlah` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `layanan_grooming`
--

CREATE TABLE `layanan_grooming` (
  `id` int NOT NULL,
  `nama_layanan` varchar(150) NOT NULL,
  `jenis_hewan` varchar(50) NOT NULL,
  `deskripsi` text,
  `harga` decimal(12,2) NOT NULL,
  `durasi` int NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `layanan_grooming`
--

INSERT INTO `layanan_grooming` (`id`, `nama_layanan`, `jenis_hewan`, `deskripsi`, `harga`, `durasi`, `gambar`, `created_at`) VALUES
(1, 'Grooming Basic', 'Kucing', 'Mandi, pengeringan, dan penyisiran bulu.', '50000.00', 60, 'grooming-basic.jpg', '2026-10-14 12:13:21'),
(2, 'Grooming Full', 'Kucing', 'Mandi, pengeringan, penyisiran, dan perawatan bulu.', '80000.00', 90, 'grooming-full.jpg', '2026-10-14 12:13:21'),
(3, 'Potong Kuku', 'Kucing', 'Perawatan dan pemotongan kuku.', '20000.00', 20, 'potong-kuku.jpg', '2026-10-14 12:13:21'),
(4, 'Grooming Anjing', 'Anjing', 'Mandi, pengeringan, dan perawatan bulu anjing.', '100000.00', 120, 'grooming-anjing.jpg', '2026-10-14 12:13:21');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int NOT NULL,
  `pesanan_id` int NOT NULL,
  `tanggal_pembayaran` datetime DEFAULT CURRENT_TIMESTAMP,
  `jumlah` decimal(12,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu Verifikasi','Diterima','Ditolak') DEFAULT 'Menunggu Verifikasi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nama_penerima` varchar(100) DEFAULT NULL,
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(12,2) NOT NULL,
  `alamat` text NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `metode_pembayaran` varchar(50) NOT NULL,
  `status` enum('Menunggu Pembayaran','Menunggu Verifikasi','Diproses','Dikirim','Selesai','Dibatalkan') DEFAULT 'Menunggu Pembayaran',
  `status_pembayaran` enum('Belum Bayar','Lunas') NOT NULL DEFAULT 'Belum Bayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `user_id`, `nama_penerima`, `tanggal`, `total`, `alamat`, `no_hp`, `metode_pembayaran`, `status`, `status_pembayaran`) VALUES
(22, 2, 'raraa', '2026-10-23 15:23:01', '18000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas'),
(23, 2, 'raraa', '2026-10-23 15:27:00', '28000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas'),
(24, 2, 'raraa', '2026-10-23 19:02:28', '12000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas'),
(25, 2, 'raraa', '2026-10-25 16:37:51', '20000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas'),
(26, 2, 'raraa', '2026-10-25 18:30:50', '20000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas'),
(27, 2, 'raraa', '2026-10-25 18:31:15', '20000.00', 'Klaten', '0812345678', 'QRIS', 'Selesai', 'Lunas');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int NOT NULL,
  `kategori_id` int NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `deskripsi` text,
  `harga` decimal(12,2) NOT NULL,
  `stok` int NOT NULL DEFAULT '0',
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `kategori_id`, `nama_produk`, `deskripsi`, `harga`, `stok`, `gambar`, `created_at`) VALUES
(1, 1, 'Whiskas Adult Tuna', 'Makanan kucing rasa tuna.', '35000.00', 20, 'whiskas.jpg', '2026-10-14 12:13:21'),
(2, 1, 'Royal Canin Kitten', 'Makanan khusus anak kucing.', '85000.00', 15, 'royal-canin.jpg', '2026-10-14 12:13:21'),
(3, 2, 'Snack Kucing Tuna', 'Snack kucing rasa tuna.', '18000.00', 24, 'snack-tuna.jpg', '2026-10-14 12:13:21'),
(4, 3, 'Vitamin Kucing', 'Vitamin untuk membantu menjaga kesehatan kucing.', '25000.00', 15, 'vitamin-kucing.jpg', '2026-10-14 12:13:21'),
(5, 4, 'Pasir Kucing', 'Pasir kucing dengan daya serap baik.', '30000.00', 20, 'pasir-kucing.jpg', '2026-10-14 12:13:21'),
(7, 6, 'Bola Mainan Kucing', 'Mainan bola untuk kucing.', '12000.00', 28, 'bola-kucing.jpg', '2026-10-14 12:13:21'),
(8, 7, 'Kalung Kucing', 'Kalung nyaman untuk hewan peliharaan.', '20000.00', 13, 'kalung-kucing.jpg', '2026-10-14 12:13:21'),
(9, 8, 'Tempat Makan Kucing', 'Tempat makan dan minum untuk kucing.', '25000.00', 10, 'tempat-makan.jpg', '2026-10-14 12:13:21'),
(10, 9, 'Kandang Kucing', 'Kandang kucing dengan ukuran sedang.', '250000.00', 10, 'kandang-kucing.jpg', '2026-10-14 12:13:21'),
(14, 5, 'Shampo Kucing', 'Shampo Khusus Untuk Hewan Peliharaan.', '28000.00', 20, '6d7f8de3cfc3e73ceca65ecd.jpg', '2026-10-25 11:23:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelanggan') DEFAULT 'pelanggan',
  `alamat` text,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `alamat`, `no_hp`, `created_at`) VALUES
(1, 'Admin Pet Shop', 'admin@petshop.com', '$2y$10$Dx2l44OFA9Tv1Hu7mV/3BOu7Qpn.8w5TUojuEL8KXYZ2c/gSnq2/2', 'admin', 'Pet Shop', '081234567890', '2026-10-14 12:13:20'),
(2, 'raraa', 'raraya25@gmail.com', '$2y$10$unUMNCgWVak7EVA1Dhkise5w.ImzjLIKlTN37YhGsEzTQJUTimg0m', 'pelanggan', 'Klaten', '0812345678', '2026-10-21 12:02:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking_grooming`
--
ALTER TABLE `booking_grooming`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hewan_id` (`hewan_id`),
  ADD KEY `layanan_id` (`layanan_id`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `hewan`
--
ALTER TABLE `hewan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `jadwal_grooming`
--
ALTER TABLE `jadwal_grooming`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `layanan_grooming`
--
ALTER TABLE `layanan_grooming`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking_grooming`
--
ALTER TABLE `booking_grooming`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `hewan`
--
ALTER TABLE `hewan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jadwal_grooming`
--
ALTER TABLE `jadwal_grooming`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `layanan_grooming`
--
ALTER TABLE `layanan_grooming`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_grooming`
--
ALTER TABLE `booking_grooming`
  ADD CONSTRAINT `booking_grooming_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_grooming_ibfk_2` FOREIGN KEY (`hewan_id`) REFERENCES `hewan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_grooming_ibfk_3` FOREIGN KEY (`layanan_id`) REFERENCES `layanan_grooming` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_grooming_ibfk_4` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_grooming` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hewan`
--
ALTER TABLE `hewan`
  ADD CONSTRAINT `hewan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `keranjang_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
