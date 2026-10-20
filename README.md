# SI SMART POS & Dynamic HPP Tracker

Aplikasi kasir dan pencatatan biaya untuk UMKM kuliner maupun retail. Aplikasi menggunakan PHP Native dengan pemisahan MVC, MySQL, HTML5, CSS3, dan tanpa JavaScript atau framework.

## Fitur

- Login dengan peran pemilik, admin/manajer, dan kasir; pemeriksaan akses dilakukan pada server.
- Dashboard omzet, laba kotor, biaya operasional, laba bersih, jumlah transaksi, stok minimum, dan peringatan margin rendah.
- Pengelolaan produk, kategori, bahan baku, supplier, dan akun operasional.
- Resep kuliner dengan HPP dinamis dari biaya rata-rata bahan baku.
- Pembelian draft dan konfirmasi. Konfirmasi memperbarui stok serta biaya rata-rata tertimbang bergerak dalam transaksi MySQL.
- POS tanpa JavaScript: input keranjang, pratinjau subtotal/diskon/HPP, konfirmasi pembayaran, dan nota.
- Penjualan ritel mengurangi stok barang; penjualan kuliner mengurangi bahan baku berdasarkan resep. Keduanya menyimpan snapshot HPP historis.
- Penyesuaian stok dengan alasan, histori mutasi, dan histori perubahan biaya.
- Pencatatan dan pembatalan biaya operasional yang diaudit, serta laporan omzet, HPP, laba kotor/bersih, margin, produk terlaris, dan pembelian per periode.
- Prepared statements, password hashing, session, regenerasi ID sesi, CSRF, dan transaksi database.

## Persyaratan

- PHP 8.1 atau lebih baru dengan ekstensi `pdo_mysql` dan `mbstring`.
- MySQL 8.0+ atau MariaDB yang mendukung InnoDB.
- Apache (Laragon) atau server PHP lain yang dapat menjalankan `index.php`.

## Instalasi di Laragon

1. Aktifkan Apache dan MySQL pada Laragon.
2. Buat database dengan mengimpor [database/schema.sql](./database/schema.sql) melalui HeidiSQL/phpMyAdmin, atau jalankan dari terminal:

   ```powershell
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root < database\schema.sql
   ```

   Sesuaikan lokasi `mysql.exe` dengan versi Laragon yang terpasang.
3. Periksa kredensial pada [config/config.php](./config/config.php). Nilai bawaan sesuai instalasi Laragon standar (`localhost:3306`, database `si_smart_pos`, pengguna `root`, kata sandi kosong). Konfigurasi dapat ditimpa dengan environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`.
4. Buka folder aplikasi melalui virtual host Laragon. Document root harus menunjuk ke folder proyek ini, yang berisi `index.php`. Alternatif untuk pengembangan lokal:

   ```powershell
   php -S 127.0.0.1:8000
   ```

   Lalu buka `http://127.0.0.1:8000`.
5. Pilih **Buat akun pemilik** atau buka `?page=setup`, kemudian buat akun pemilik. Halaman setup otomatis dikunci setelah akun pertama ada.
6. Masuk, tambahkan kategori dan produk/bahan, catat pembelian, lalu konfirmasi pembelian agar stok tersedia. Produk kuliner perlu memiliki resep sebelum dapat dijual.

## Alur perhitungan

- **Kuliner:** HPP/unit = jumlah (kuantitas bahan × biaya rata-rata bahan) ÷ hasil resep.
- **Retail:** HPP/unit menggunakan biaya rata-rata persediaan yang diperbarui saat pembelian dikonfirmasi.
- **Laba kotor:** omzet setelah diskon dikurangi HPP pada saat penjualan. Nilai HPP disimpan pada baris transaksi agar perubahan biaya berikutnya tidak mengubah histori.
- **Laba bersih:** laba kotor dikurangi biaya operasional berstatus aktif pada periode tersebut. Pembatalan biaya menyimpan alasan dan tidak menghapus histori.
- **Harga rekomendasi:** HPP ÷ (1 − target margin). Aplikasi hanya memberi saran; harga jual tidak berubah otomatis.
- Pembelian yang masih draft dapat dibatalkan. Pembelian terkonfirmasi tidak dapat dibatalkan agar histori persediaan dan biaya tidak berubah tanpa proses retur yang terpisah.

## Struktur MVC

- `app/Controllers/` — routing, validasi, otorisasi, dan koordinasi aksi.
- `app/Models/` — operasi data serta transaksi bisnis MySQL.
- `app/Views/` — halaman HTML dan template.
- `app/Core/` — koneksi database, autentikasi, CSRF, bootstrap, dan helper.
- `database/schema.sql` — skema basis data.
- `database/migrations/` — migrasi SQL aditif untuk database yang sudah terpasang.
- `public/assets/app.css` — tampilan responsif.

Database MySQL `si_smart_pos` menggunakan 15 tabel InnoDB, termasuk tabel biaya operasional. Detail penjualan dan pembelian menyimpan nama/satuan serta harga/HPP snapshot, sehingga perubahan katalog tidak mengubah nota atau laporan historis.

### Memperbarui instalasi yang sudah ada

Jangan mengimpor ulang skema lama untuk mencoba mengganti database atau menghapus tabel. Untuk memperbarui instalasi yang sudah memiliki tabel, buat cadangan database terlebih dahulu, lalu jalankan migrasi satu kali melalui phpMyAdmin atau MySQL:

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe --host=127.0.0.1 --port=3306 --user=root si_smart_pos < database\migrations\20261019_expand_operational_schema.sql
```

Migrasi ini menambahkan relasi item persediaan yang bertipe jelas, snapshot nama/satuan pada histori, dan tabel biaya operasional. Migrasi tidak menghapus tabel, pengguna, maupun transaksi lama. Jangan jalankan file migrasi yang sama lebih dari satu kali; setelah migrasi berhasil, `database/schema.sql` mencerminkan struktur lengkap untuk instalasi baru.

## Catatan operasional

- Satuan resep dan pembelian harus konsisten. Konversi satuan otomatis belum dilakukan.
- Sistem memakai satu outlet dan satu mata uang rupiah.
- Retur penjualan, multi-outlet, pajak, dan cetak termal belum termasuk cakupan versi ini.
- Jangan mengekspos halaman setup atau aplikasi ke internet sebelum akun pemilik dibuat dan kredensial database produksi diamankan.
