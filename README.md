# SI SMART UMKM & Dynamic HPP Tracker

Aplikasi untuk membantu UMKM mengelola produk, persediaan, HPP, biaya operasional, dan laporan biaya. Aplikasi menggunakan PHP Native dengan pemisahan MVC, MySQL, HTML5, CSS3, dan tanpa JavaScript atau framework. Antarmuka aplikasi tidak menyediakan fitur penjualan maupun pembelian.

## Fitur

- Login dengan peran pemilik dan admin/manajer; pemeriksaan akses dilakukan pada server.
- Dashboard jumlah produk aktif, biaya operasional, stok minimum, dan peringatan margin.
- Pengelolaan produk, kategori, bahan baku, supplier, dan akun operasional.
- Pencarian serta filter katalog berdasarkan nama, kategori, jenis, dan status; data biaya dapat dicari dengan kata kunci.
- Arsip aman untuk produk, bahan baku, supplier, dan pengguna. Produk, bahan baku, supplier, serta kategori dapat dihapus permanen hanya jika belum memiliki stok, transaksi, resep, atau histori terkait.
- Profil usaha yang dapat diubah pemilik dan dikelola dari menu Pengaturan.
- Resep kuliner dengan HPP dinamis dari biaya rata-rata bahan baku dan pencatatan biaya.
- Penyesuaian stok dengan alasan, histori mutasi, dan histori perubahan biaya.
- Pencatatan dan pembatalan biaya operasional yang diaudit, serta laporan biaya operasional.
- Prepared statements, password hashing, session, regenerasi ID sesi, CSRF, dan transaksi database.

## Persyaratan

- PHP 8.1 atau lebih baru dengan ekstensi `pdo_mysql` dan `mbstring`.
- MySQL 8.0+ atau MariaDB yang mendukung InnoDB.
- Apache (Laragon) atau server PHP lain yang dapat menjalankan `index.php`.

## Instalasi di Laragon

1. Aktifkan Apache dan MySQL pada Laragon.
2. Buat database beserta tabel dengan mengimpor [database/schema.sql](./database/schema.sql) melalui HeidiSQL/phpMyAdmin, atau jalankan dari terminal:

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
6. Masuk, tambahkan kategori dan produk/bahan, lalu gunakan pengelolaan stok dan biaya untuk memperbarui persediaan dan HPP. Produk kuliner dapat diberi resep untuk melihat perkiraan HPP.

## Alur perhitungan

- **Kuliner:** HPP/unit = jumlah (kuantitas bahan × biaya rata-rata bahan) ÷ hasil resep.
- Kuantitas hasil dan bahan resep menerima angka positif hingga tiga desimal dengan titik desimal (misalnya `0.5` atau `1.250`); baris resep yang kosong diabaikan, bukan divalidasi sebagai input stok.
- Pengubahan komposisi resep tidak menambah atau mengurangi stok bahan. Bahan yang dikeluarkan dari resep memerlukan konfirmasi dan HPP dihitung ulang dari komposisi yang tersimpan.
- **Retail:** HPP/unit mengikuti biaya rata-rata persediaan yang tercatat.
- Harga bahan pada formulir bahan baku menetapkan biaya rata-rata per satuan tanpa mengubah kuantitas stok; perubahan harga dicatat di riwayat biaya. Stok masuk melalui pembelian tetap menggunakan rata-rata tertimbang bergerak.
- Biaya nol dianggap belum tersedia. HPP resep yang memakai bahan tanpa biaya valid tidak ditampilkan sebagai Rp0 dan akan menunjukkan bahan yang perlu dilengkapi.
- **Harga rekomendasi:** HPP ÷ (1 − target margin). Aplikasi hanya memberi saran; harga jual tidak berubah otomatis.

## Struktur MVC

- `app/Controllers/` — routing, validasi, otorisasi, dan koordinasi aksi.
- `app/Models/` — operasi data serta transaksi bisnis MySQL.
- `app/Views/` — halaman HTML dan template.
- `app/Core/` — koneksi database, autentikasi, CSRF, bootstrap, dan helper.
- `database/schema.sql` — skema basis data.
- `database/schema.sql` memakai `CREATE TABLE IF NOT EXISTS`; skema ini tidak menghapus tabel atau data yang sudah ada. Skema tersebut untuk pemasangan baru, bukan pengganti proses migrasi ketika struktur instalasi lama berbeda.
- `database/migrations/20261020_add_inventory_movement_audit.sql` menambahkan arsip audit untuk edit/hapus koreksi stok manual.
- `public/assets/app.css` — tampilan responsif.

Database MySQL `si_smart_pos` mempertahankan tabel dan catatan transaksi lama. Halaman, navigasi, laporan penjualan, dan pembelian tidak tersedia pada antarmuka aplikasi. Struktur database dan histori lama tidak dihapus; sebagian tabel historis tetap dipakai untuk mencegah perubahan master data merusak catatan yang sudah tersimpan.

### Memperbarui instalasi yang sudah ada

Buat cadangan database sebelum mengubah struktur instalasi. Tinjau tabel dan kolom yang sudah ada di phpMyAdmin terlebih dahulu; jangan menghapus atau mengganti data lama. Untuk instalasi yang sudah berjalan, jalankan `database/migrations/20261020_add_inventory_movement_audit.sql` satu kali pada database `si_smart_pos`. Migrasi ini hanya menambahkan tabel audit dan tidak mengubah data stok lama. Migrasi sudah diterapkan pada MySQL lokal proyek ini.

Navigasi menggunakan elemen HTML `<details>` dan formulir memakai validasi PHP/CSRF; aplikasi tidak memerlukan JavaScript.

Edit saldo stok mencatat selisih sebagai mutasi koreksi. Edit/hapus koreksi manual memperbarui saldo dalam transaksi database dan menyimpan salinan audit; mutasi transaksi tetap terkunci.

## Catatan operasional

- Satuan resep dan stok harus konsisten. Konversi satuan otomatis belum dilakukan.
- Sistem memakai satu outlet dan satu mata uang rupiah.
- Jangan mengekspos halaman setup atau aplikasi ke internet sebelum akun pemilik dibuat dan kredensial database produksi diamankan.
