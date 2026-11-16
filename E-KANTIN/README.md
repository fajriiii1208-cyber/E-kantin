# 🍱 E-Kantin

Aplikasi web pemesanan makanan kantin kampus dengan **3 peran**: siswa, pengelola kantin, dan admin. Dibuat dengan **PHP native + MySQL**, tanpa framework, sehingga cocok untuk proyek kuliah, tugas akhir, atau prototipe kantin digital.

![Stack](https://img.shields.io/badge/PHP-8.x-777bb4) ![Database](https://img.shields.io/badge/MySQL-MariaDB-4479a1) ![License](https://img.shields.io/badge/use-local%20project-orange)

---

## ✨ Fitur

### 🎓 Siswa
- Lihat semua menu dari seluruh kantin, dengan pencarian, filter kategori/kantin, dan urutan harga.
- Keranjang belanja (bisa berisi menu dari beberapa kantin sekaligus).
- Checkout dengan metode **Tunai** atau **QRIS**, plus catatan khusus untuk kantin.
- Pesanan otomatis dipecah per kantin.
- Lacak status pesanan: **Menunggu → Diproses → Siap diambil → Selesai**.
- Batalkan pesanan selama masih menunggu (stok otomatis dikembalikan).
- Riwayat pesanan dan detail pembayaran.
- Registrasi akun mandiri.

### 👩‍🍳 Pengelola Kantin
- Dashboard statistik: pesanan hari ini, omzet, pesanan menunggu, menu aktif.
- **Stok & ketersediaan**: perbarui jumlah porsi, tandai habis, aktif/nonaktifkan menu dengan sekali klik.
- Kelola pesanan: terima, proses, tandai siap, selesaikan, atau batalkan.
- Tandai pembayaran lunas/belum lunas.
- CRUD menu: nama, deskripsi, kategori, harga, stok, emoji, status aktif.
- Atur profil kantin (nama, deskripsi, lokasi, buka/tutup).
- Menu terlaris dan ringkasan performa.

### 🛡️ Admin
- Dashboard ringkasan seluruh platform.
- Kelola pengguna: tambah, edit, atur peran, reset password, hapus (dengan proteksi data).
- Kelola kantin + sekaligus buat akun pengelolanya.
- Kelola kategori menu.
- Laporan penjualan dengan filter tanggal, kantin, dan status.
- Ekspor laporan ke **CSV** (kompatibel Excel).

### 🔐 Keamanan dasar
- Password di-hash dengan `password_hash()` (bcrypt).
- Semua query memakai **prepared statement** (anti SQL injection).
- **CSRF token** pada seluruh form POST.
- Escape HTML pada semua output (anti XSS).
- Role guard di setiap halaman.
- Transaksi database + validasi stok saat checkout/pembatalan.

---

## 🚀 Cara Menjalankan

### Opsi A — XAMPP (disarankan)
1. Pastikan **Apache** dan **MySQL** sudah berjalan dari XAMPP Control Panel.
2. Salin folder proyek ini ke `C:\xampp\htdocs\E-KANTIN`.
3. Buka browser: **http://localhost/E-KANTIN**
4. Database `e_kantin` akan **dibuat otomatis** saat pertama kali dibuka, lengkap dengan data demo.

> Jika ingin membuat database manual: buat database `e_kantin`, lalu import `database.sql` melalui phpMyAdmin.

### Opsi B — PHP built-in server
```bash
cd E-KANTIN
php -S localhost:8000
```
Lalu buka **http://localhost:8000**.

### Konfigurasi database
Default (XAMPP): host `127.0.0.1`, user `root`, password kosong, database `e_kantin`.
Ubah di `config.php` atau lewat environment variable:

```
EKANTIN_DB_HOST, EKANTIN_DB_NAME, EKANTIN_DB_USER, EKANTIN_DB_PASS
```

---

## 🔑 Akun Demo

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@ekantin.test` | `admin123` |
| Pengelola Kantin | `sari@ekantin.test` | `kantin123` |
| Pengelola Kantin | `budi@ekantin.test` | `kantin123` |
| Siswa | `siswa@ekantin.test` | `siswa123` |
| Siswa | `rina@ekantin.test` | `siswa123` |

Tombol **Isi** pada halaman login bisa mengisi kredensial demo secara otomatis.

> ⚠️ Ganti password akun demo sebelum dipakai di lingkungan nyata.

---

## 🧭 Alur Singkat

1. **Siswa** login → pilih menu → tambah ke keranjang → checkout (tunai/QRIS).
2. **Pengelola kantin** melihat pesanan masuk → terima & proses → tandai siap.
3. Siswa mengambil pesanan dengan menunjukkan kode, lalu kantin menandai **Selesai** dan **Lunas**.
4. **Admin** memantau seluruh transaksi lewat laporan dan bisa mengekspor CSV.

Kode pesanan berformat `EK` + tanggal + 6 karakter acak, contoh: `EK261102A1C122`.

---

## 📁 Struktur Folder

```
E-KANTIN/
├── index.php               # Katalog menu (publik & siswa)
├── login.php               # Masuk
├── register.php            # Daftar siswa
├── logout.php
├── keranjang.php           # Keranjang + checkout
├── pesanan.php             # Daftar & detail pesanan siswa
├── akun.php                # Profil & ganti password
├── config.php              # Koneksi DB, session, base URL
├── database.sql            # Skema + data demo
├── includes/
│   ├── functions.php       # Helper, auth, CSRF, keranjang
│   ├── header.php          # Layout atas + navigasi
│   └── footer.php
├── kantin/                 # Area pengelola kantin
│   ├── index.php           # Dashboard
│   ├── pesanan.php         # Kelola pesanan
│   ├── stok.php            # Stok & ketersediaan menu
│   ├── menu.php            # CRUD menu
│   └── profil.php          # Profil kantin
├── admin/                  # Area administrator
│   ├── index.php           # Dashboard
│   ├── pengguna.php        # Kelola akun
│   ├── kantin.php          # Kelola kantin
│   ├── kategori.php        # Kelola kategori
│   └── laporan.php         # Laporan + ekspor CSV
├── assets/
│   ├── style.css           # Design system & responsive
│   └── app.js              # Interaksi UI
└── backup-mockup/          # File mockup lama (cadangan)
```

---

## 🧪 Reset Data Demo

Hapus database lalu buka aplikasi kembali — data demo akan dibuat ulang:

```sql
DROP DATABASE e_kantin;
```

Atau lewat phpMyAdmin: pilih database `e_kantin` → **Drop** → buka ulang `http://localhost/E-KANTIN`.

---

## 🛠️ Catatan Pengembangan

- Membutuhkan **PHP 8.0+** (menggunakan `match`, `str_starts_with`, dan tipe data modern).
- Tidak memakai framework/CDN, semua aset lokal sehingga aman dijalankan offline.
- Tampilan responsif untuk desktop, tablet, dan ponsel.
- Data stok berkurang saat checkout dan kembali saat pesanan dibatalkan.
- Riwayat pesanan menyimpan nama & harga menu saat transaksi, sehingga aman meski menu diubah/dihapus.

---

Dibuat untuk mempermudah antrean kantin kampus. Selamat mencoba! 🍜
