<div class="section-intro"><div><h2>Data pemasok</h2><p>Simpan informasi supplier untuk referensi nota pembelian.</p></div><span class="badge badge-green"><?= count($suppliers) ?> supplier</span></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3><?= $editing ? 'Ubah supplier' : 'Tambah supplier' ?></h3><p>Data supplier yang memiliki riwayat tidak dihapus.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_supplier"><input type="hidden" name="return_page" value="suppliers"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label class="span-2">Nama supplier<input name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" required></label>
            <label>Nomor telepon<input name="phone" maxlength="40" value="<?= e($editing['phone'] ?? '') ?>"></label>
            <label>Alamat<input name="address" maxlength="255" value="<?= e($editing['address'] ?? '') ?>"></label>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah supplier' ?></button><?php if ($editing): ?><a class="button button-quiet" href="?page=suppliers">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Informasi pembelian</h3><p>Pembelian memerlukan setidaknya satu supplier.</p></div><span class="stat-icon yellow">⇄</span></div><div class="callout"><strong>Alur pencatatan stok</strong><p>Simpan pembelian sebagai draft, lalu konfirmasi setelah memeriksa rincian. Hanya pembelian yang dikonfirmasi yang menambah stok dan memperbarui biaya rata-rata.</p><a class="text-link" href="?page=purchases">Buka pembelian →</a></div></section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar supplier</h3><p>Supplier aktif untuk pencatatan pembelian.</p></div></div>
    <?php if (!$suppliers): ?><div class="empty-state">Belum ada supplier.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Nama supplier</th><th>Telepon</th><th>Alamat</th><th>Dibuat</th><th></th></tr></thead><tbody>
    <?php foreach ($suppliers as $supplier): ?><tr><td class="strong"><?= e($supplier['name']) ?></td><td><?= e($supplier['phone'] ?: '—') ?></td><td><?= e($supplier['address'] ?: '—') ?></td><td><?= e(format_date($supplier['created_at'])) ?></td><td><a class="text-link" href="?page=suppliers&edit=<?= (int) $supplier['id'] ?>">Ubah</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
