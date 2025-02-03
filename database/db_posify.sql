-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 01 Feb 2025 pada 15.11
-- Versi server: 10.11.10-MariaDB-cll-lve
-- Versi PHP: 8.3.15


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `muhq7689_dbposify`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `checkout`
--

CREATE TABLE `checkout` (
  `id` int(11) NOT NULL,
  `id_keranjang` int(11) DEFAULT NULL,
  `id_voucher` int(11) DEFAULT NULL,
  `id_pelanggan` int(10) DEFAULT NULL,
  `metode_pengiriman` varchar(255) DEFAULT 'Ditempat',
  `biaya_pengiriman` int(100) DEFAULT 0,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `total_harga` decimal(10,2) DEFAULT NULL,
  `status` enum('sementara','checkout') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Struktur dari tabel `keranjang`
--

CREATE TABLE `keranjang` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `total_produk` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `langganantoko`
--

CREATE TABLE `langganantoko` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `id_langganan` int(11) DEFAULT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_berakhir` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Struktur dari tabel `laporankeuangan`
--

CREATE TABLE `laporankeuangan` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `omset_penjualan` decimal(15,2) DEFAULT NULL,
  `biaya_komisi` decimal(15,2) DEFAULT NULL,
  `total_diskon` decimal(15,2) DEFAULT NULL,
  `total_bersih` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `midtrans_log`
--

CREATE TABLE `midtrans_log` (
  `id` int(11) NOT NULL,
  `id_pembayaran` int(11) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT NULL,
  `raw_response` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `paketlangganan`
--

CREATE TABLE `paketlangganan` (
  `id` int(11) NOT NULL,
  `nama` enum('premium','standard') DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` decimal(10,2) DEFAULT NULL,
  `durasi` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `nama_pelanggan` varchar(255) DEFAULT NULL,
  `nomor_telepon` varchar(20) DEFAULT NULL,
  `email` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `id_checkout` int(11) DEFAULT NULL,
  `metode` enum('tunai','non-tunai','debit') DEFAULT NULL,
  `nominal` int(11) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `waktu_pembayaran` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `nama_produk` varchar(255) DEFAULT NULL,
  `harga_modal` decimal(10,2) DEFAULT NULL,
  `harga_jual` decimal(10,2) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `gambar` text DEFAULT NULL,
  `stok` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `produkkeranjang`
--

CREATE TABLE `produkkeranjang` (
  `id` int(11) NOT NULL,
  `id_keranjang` int(11) DEFAULT NULL,
  `id_produk` int(11) DEFAULT NULL,
  `kuantitas` int(11) DEFAULT NULL,
  `harga_produk` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `token` text DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `toko`
--

CREATE TABLE `toko` (
  `id` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `nama_toko` varchar(255) DEFAULT NULL,
  `nomor_rekening` varchar(255) DEFAULT NULL,
  `logo` text DEFAULT NULL,
  `nomor_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `id_pembayaran` int(11) DEFAULT NULL,
  `nomor_order` varchar(255) DEFAULT NULL,
  `waktu_transaksi` datetime DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksilaporan`
--

CREATE TABLE `transaksilaporan` (
  `id` int(11) NOT NULL,
  `id_transaksi` int(11) DEFAULT NULL,
  `id_laporan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Admin','User') DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `reset_requested` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `voucher`
--

CREATE TABLE `voucher` (
  `id` int(11) NOT NULL,
  `id_toko` int(11) DEFAULT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `nilai_diskon` int(100) DEFAULT NULL,
  `minimal_belanja` int(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `checkout`
--
ALTER TABLE `checkout`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_keranjang` (`id_keranjang`),
  ADD KEY `id_voucher` (`id_voucher`),
  ADD KEY `checkout_ibfk_3` (`id_pelanggan`);

--
-- Indeks untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- Indeks untuk tabel `langganantoko`
--
ALTER TABLE `langganantoko`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- Indeks untuk tabel `laporankeuangan`
--
ALTER TABLE `laporankeuangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- Indeks untuk tabel `midtrans_log`
--
ALTER TABLE `midtrans_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembayaran` (`id_pembayaran`);

--
-- Indeks untuk tabel `paketlangganan`
--
ALTER TABLE `paketlangganan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_checkout` (`id_checkout`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- Indeks untuk tabel `produkkeranjang`
--
ALTER TABLE `produkkeranjang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_keranjang` (`id_keranjang`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `toko`
--
ALTER TABLE `toko`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_order` (`nomor_order`),
  ADD KEY `id_pembayaran` (`id_pembayaran`);

--
-- Indeks untuk tabel `transaksilaporan`
--
ALTER TABLE `transaksilaporan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_laporan` (`id_laporan`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_toko` (`id_toko`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `checkout`
--
ALTER TABLE `checkout`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `langganantoko`
--
ALTER TABLE `langganantoko`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `laporankeuangan`
--
ALTER TABLE `laporankeuangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `midtrans_log`
--
ALTER TABLE `midtrans_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `paketlangganan`
--
ALTER TABLE `paketlangganan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `produkkeranjang`
--
ALTER TABLE `produkkeranjang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `toko`
--
ALTER TABLE `toko`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksilaporan`
--
ALTER TABLE `transaksilaporan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `voucher`
--
ALTER TABLE `voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `checkout`
--
ALTER TABLE `checkout`
  ADD CONSTRAINT `checkout_ibfk_1` FOREIGN KEY (`id_keranjang`) REFERENCES `keranjang` (`id`),
  ADD CONSTRAINT `checkout_ibfk_2` FOREIGN KEY (`id_voucher`) REFERENCES `voucher` (`id`),
  ADD CONSTRAINT `checkout_ibfk_3` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`);

--
-- Ketidakleluasaan untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);

--
-- Ketidakleluasaan untuk tabel `langganantoko`
--
ALTER TABLE `langganantoko`
  ADD CONSTRAINT `langganantoko_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);

--
-- Ketidakleluasaan untuk tabel `laporankeuangan`
--
ALTER TABLE `laporankeuangan`
  ADD CONSTRAINT `laporankeuangan_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);

--
-- Ketidakleluasaan untuk tabel `midtrans_log`
--
ALTER TABLE `midtrans_log`
  ADD CONSTRAINT `midtrans_log_ibfk_1` FOREIGN KEY (`id_pembayaran`) REFERENCES `pembayaran` (`id`);

--
-- Ketidakleluasaan untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD CONSTRAINT `pelanggan_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_checkout`) REFERENCES `checkout` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);

--
-- Ketidakleluasaan untuk tabel `produkkeranjang`
--
ALTER TABLE `produkkeranjang`
  ADD CONSTRAINT `produkkeranjang_ibfk_1` FOREIGN KEY (`id_keranjang`) REFERENCES `keranjang` (`id`),
  ADD CONSTRAINT `produkkeranjang_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `toko`
--
ALTER TABLE `toko`
  ADD CONSTRAINT `toko_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_pembayaran`) REFERENCES `pembayaran` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksilaporan`
--
ALTER TABLE `transaksilaporan`
  ADD CONSTRAINT `transaksilaporan_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `transaksilaporan_ibfk_2` FOREIGN KEY (`id_laporan`) REFERENCES `laporankeuangan` (`id`);

--
-- Ketidakleluasaan untuk tabel `voucher`
--
ALTER TABLE `voucher`
  ADD CONSTRAINT `voucher_ibfk_1` FOREIGN KEY (`id_toko`) REFERENCES `toko` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
