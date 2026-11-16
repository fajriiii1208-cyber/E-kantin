CREATE TABLE IF NOT EXISTS kantin (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  deskripsi VARCHAR(255) DEFAULT NULL,
  lokasi VARCHAR(150) DEFAULT NULL,
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kategori (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  ikon VARCHAR(10) NOT NULL DEFAULT '🍽️'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','kantin','siswa') NOT NULL DEFAULT 'siswa',
  no_hp VARCHAR(20) DEFAULT NULL,
  kantin_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_kantin FOREIGN KEY (kantin_id) REFERENCES kantin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kantin_id INT UNSIGNED NOT NULL,
  kategori_id INT UNSIGNED DEFAULT NULL,
  nama VARCHAR(100) NOT NULL,
  deskripsi VARCHAR(255) DEFAULT NULL,
  harga INT UNSIGNED NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  emoji VARCHAR(10) NOT NULL DEFAULT '🍽️',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_menu_kantin FOREIGN KEY (kantin_id) REFERENCES kantin(id) ON DELETE CASCADE,
  CONSTRAINT fk_menu_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesanan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  kantin_id INT UNSIGNED NOT NULL,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  metode ENUM('tunai','qris') NOT NULL DEFAULT 'tunai',
  status ENUM('menunggu','diproses','siap','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu',
  status_bayar ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
  catatan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pesanan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pesanan_kantin FOREIGN KEY (kantin_id) REFERENCES kantin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesanan_detail (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED DEFAULT NULL,
  nama_menu VARCHAR(100) NOT NULL,
  harga INT UNSIGNED NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  subtotal INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_detail_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
  CONSTRAINT fk_detail_menu FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kategori (id, nama, ikon) VALUES
  (1, 'Makanan Berat', '🍛'),
  (2, 'Mie & Bakso', '🍜'),
  (3, 'Snack & Gorengan', '🍟'),
  (4, 'Minuman', '🥤'),
  (5, 'Dessert', '🍮');

INSERT INTO kantin (id, nama, deskripsi, lokasi, is_open) VALUES
  (1, 'Kantin Bu Sari', 'Masakan rumahan dan gorengan hangat setiap hari.', 'Gedung A · Lantai 1', 1),
  (2, 'Kantin Pak Budi', 'Mie, bakso, dan minuman segar favorit mahasiswa.', 'Gedung B · Lantai 2', 1);

INSERT INTO users (id, nama, email, password, role, no_hp, kantin_id) VALUES
  (1, 'Admin E-Kantin', 'admin@ekantin.test', '$2y$10$d6g.B90LSB7K7iErAnuDOejuy6jkmcUPkzepxdql9G7kb5ztZrBiG', 'admin', '081200000001', NULL),
  (2, 'Sari Wulandari', 'sari@ekantin.test', '$2y$10$6g0lc1Om/SBppolypXWq0uyAIUeuHzqDnsyjAgrtQFfFxSaxRs7ie', 'kantin', '081200000002', 1),
  (3, 'Budi Santoso', 'budi@ekantin.test', '$2y$10$6g0lc1Om/SBppolypXWq0uyAIUeuHzqDnsyjAgrtQFfFxSaxRs7ie', 'kantin', '081200000003', 2),
  (4, 'Andi Pratama', 'siswa@ekantin.test', '$2y$10$1Tm2ulkUM3QcQb5faApN3uhwk65WcVFx8fp.uoyPAx1Ck6Wcv3rcq', 'siswa', '081200000004', NULL),
  (5, 'Rina Lestari', 'rina@ekantin.test', '$2y$10$1Tm2ulkUM3QcQb5faApN3uhwk65WcVFx8fp.uoyPAx1Ck6Wcv3rcq', 'siswa', '081200000005', NULL);

INSERT INTO menu (id, kantin_id, kategori_id, nama, deskripsi, harga, stok, emoji, is_active) VALUES
  (1, 1, 1, 'Nasi Ayam Geprek', 'Ayam crispy dengan sambal bawang pedas, plus nasi hangat.', 15000, 40, '🍗', 1),
  (2, 1, 1, 'Nasi Rendang Daging', 'Rendang empuk masak santan dengan nasi putih.', 18000, 25, '🍛', 1),
  (3, 1, 1, 'Tahu Tempe Penyet', 'Tahu dan tempe goreng dengan sambal terasi.', 6000, 50, '🍢', 1),
  (4, 1, 3, 'Pisang Goreng (3 pcs)', 'Pisang goreng renyah, cocok untuk camilan.', 5000, 35, '🍌', 1),
  (5, 1, 4, 'Es Teh Manis', 'Teh manis dingin segar.', 4000, 100, '🧋', 1),
  (6, 1, 4, 'Air Mineral', 'Air mineral botol 600 ml.', 3000, 80, '💧', 1),
  (7, 2, 2, 'Mie Ayam Spesial', 'Mie ayam dengan pangsit goreng dan kuah kaldu.', 12000, 30, '🍜', 1),
  (8, 2, 2, 'Bakso Urat', 'Bakso urat sapi dengan kuah gurih dan mie.', 13000, 30, '🍲', 1),
  (9, 2, 1, 'Soto Ayam', 'Soto ayam kampung dengan koya dan telur.', 11000, 20, '🍲', 1),
  (10, 2, 4, 'Es Jeruk Peras', 'Jeruk peras asli dengan es batu.', 5000, 60, '🍊', 1),
  (11, 2, 3, 'Roti Bakar Cokelat', 'Roti bakar isi cokelat dan keju.', 8000, 25, '🍞', 1),
  (12, 2, 1, 'Nasi Goreng Kampung', 'Nasi goreng dengan telur, sosis, dan kerupuk.', 14000, 25, '🍚', 1);

INSERT INTO pesanan (id, kode, user_id, kantin_id, total, metode, status, status_bayar, catatan, created_at, updated_at) VALUES
  (1, 'EKDemo001', 4, 1, 19000, 'tunai', 'selesai', 'lunas', 'Sambal dipisah ya', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
  (2, 'EKDemo002', 5, 2, 24000, 'qris', 'menunggu', 'belum', NULL, NOW(), NOW());

INSERT INTO pesanan_detail (pesanan_id, menu_id, nama_menu, harga, qty, subtotal) VALUES
  (1, 1, 'Nasi Ayam Geprek', 15000, 1, 15000),
  (1, 5, 'Es Teh Manis', 4000, 1, 4000),
  (2, 7, 'Mie Ayam Spesial', 12000, 2, 24000);
