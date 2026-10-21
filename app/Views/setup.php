<section class="auth-card">
    <a class="brand auth-brand" href="?page=setup"><span class="brand-mark">S</span><span>SMART<span class="brand-light">UMKM</span><small>SETUP PEMILIK</small></span></a>
    <?php if ($setupClosed): ?>
    <div class="auth-heading"><span class="eyebrow">PENDAFTARAN AWAL SELESAI</span><h1>Akun pemilik sudah dibuat</h1><p>Untuk keamanan, pembuatan akun pemilik hanya tersedia satu kali. Masuk menggunakan akun pemilik, lalu buka menu <strong>Pengguna &amp; akses</strong> untuk menambahkan akun admin.</p></div>
    <a class="button button-primary button-wide" href="?page=login">Kembali ke halaman masuk <span>→</span></a>
    <?php else: ?>
    <div class="auth-heading"><span class="eyebrow">PENGATURAN AWAL</span><h1>Buat akun pemilik</h1><p>Akun ini memiliki akses penuh. Setelah dibuat, halaman setup akan dikunci.</p></div>
    <form method="post" class="form-stack">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="action" value="setup">
        <label>Nama pemilik<input type="text" name="name" maxlength="120" required autofocus></label>
        <label>Email<input type="email" name="email" autocomplete="email" required></label>
        <label>Kata sandi <span class="muted">(minimal 10 karakter)</span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
        <button class="button button-primary button-wide" type="submit">Buat akun pemilik <span>→</span></button>
    </form>
    <?php endif; ?>
</section>
