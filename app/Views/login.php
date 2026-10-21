<section class="auth-card">
    <a class="brand auth-brand" href="?page=login"><span class="brand-mark">S</span><span>SMART<span class="brand-light">UMKM</span><small>PENGELOLAAN USAHA</small></span></a>
    <div class="auth-heading"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h1>Masuk ke akun Anda</h1><p>Kelola produk, persediaan, HPP, dan biaya usaha dengan lebih mudah.</p></div>
    <form method="post" class="form-stack">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="action" value="login">
        <label>Email<input type="email" name="email" autocomplete="username" required autofocus></label>
        <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button button-primary button-wide" type="submit">Masuk ke dashboard <span>→</span></button>
    </form>
    <?php if ($registrationOpen): ?><div class="auth-hint">Pertama kali menggunakan aplikasi? <a href="?page=setup">Buat akun pemilik</a></div>
    <?php else: ?><div class="auth-hint">Akun pemilik sudah dibuat. Untuk akun admin baru, masuk sebagai pemilik lalu buka menu <strong>Pengguna &amp; akses</strong>.</div><?php endif; ?>
</section>
