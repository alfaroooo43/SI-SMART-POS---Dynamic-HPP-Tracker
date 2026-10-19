<div class="section-intro"><div><h2>Pengelolaan pengguna</h2><p>Atur akun operasional. Pemilik memiliki kontrol atas pengaturan dan hak akses.</p></div><span class="badge badge-green"><?= count($users) ?> akun</span></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3><?= $editing ? 'Ubah akun' : 'Tambah akun' ?></h3><p>Akun baru hanya dapat diberi peran admin atau kasir.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="return_page" value="users"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label class="span-2">Nama lengkap<input name="name" maxlength="120" value="<?= e($editing['name'] ?? '') ?>" required></label>
            <label class="span-2">Email<input type="email" name="email" maxlength="190" value="<?= e($editing['email'] ?? '') ?>" required></label>
            <label>Peran<select name="role" required><?php foreach (['admin' => 'Admin / Manajer', 'cashier' => 'Kasir'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editing['role'] ?? 'cashier') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Kata sandi <?= $editing ? '<span class="muted">(opsional)</span>' : '<span class="muted">(min. 10 karakter)</span>' ?><input type="password" name="password" minlength="<?= $editing ? '0' : '10' ?>" autocomplete="new-password" <?= $editing ? '' : 'required' ?>></label>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Buat akun' ?></button><?php if ($editing): ?><a class="button button-quiet" href="?page=users">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Hak akses</h3><p>Akses diperiksa ulang oleh server pada setiap tindakan.</p></div><span class="stat-icon blue">♙</span></div>
        <ul class="access-list"><li><strong>Pemilik</strong><span>Semua fitur dan pengaturan pengguna</span></li><li><strong>Admin</strong><span>Produk, resep, supplier, stok, pembelian, laporan</span></li><li><strong>Kasir</strong><span>POS, transaksi, dan nota miliknya sendiri</span></li></ul>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar akun</h3><p>Akun pemilik tidak dapat dinonaktifkan dari halaman ini.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Dibuat</th><th></th></tr></thead><tbody>
<?php foreach ($users as $account): ?><tr><td class="strong"><?= e($account['name']) ?></td><td><?= e($account['email']) ?></td><td><span class="badge <?= $account['role'] === 'owner' ? 'badge-yellow' : ($account['role'] === 'admin' ? 'badge-blue' : 'badge-gray') ?>"><?= e(ucfirst($account['role'])) ?></span></td><td><span class="badge <?= $account['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $account['active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td><?= e(format_date($account['created_at'])) ?></td><td><?php if ($account['role'] !== 'owner'): ?><div class="actions-cell"><a class="text-link" href="?page=users&edit=<?= (int) $account['id'] ?>">Ubah</a><form method="post"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="return_page" value="users"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="active" value="<?= $account['active'] ? '0' : '1' ?>"><button class="text-button" type="submit"><?= $account['active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></div><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>
