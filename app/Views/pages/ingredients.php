<div class="section-intro"><div><h2>Persediaan bahan baku</h2><p>Biaya rata-rata bergerak diperbarui setelah pembelian dikonfirmasi.</p></div><a class="button button-yellow" href="?page=purchases">＋ Catat pembelian</a></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3><?= $editing ? 'Ubah bahan baku' : 'Tambah bahan baku' ?></h3><p>Jumlah stok dan biaya masuk melalui pembelian.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_ingredient"><input type="hidden" name="return_page" value="ingredients"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label class="span-2">Nama bahan<input name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" required></label>
            <label>Satuan dasar<input name="unit" maxlength="30" placeholder="kg, gr, liter, pcs" value="<?= e($editing['unit'] ?? '') ?>" required></label>
            <label>Stok minimum<input type="number" name="minimum_stock" min="0" step="0.001" value="<?= e($editing['minimum_stock'] ?? '0') ?>" required></label>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah bahan' ?></button><?php if ($editing): ?><a class="button button-quiet" href="?page=ingredients">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Petunjuk satuan</h3><p>Gunakan satuan yang konsisten antara resep dan pembelian.</p></div><span class="stat-icon green">i</span></div>
        <div class="callout"><strong>Contoh konversi</strong><p>Jika resep memakai gram, masukkan pembelian dalam gram juga; 1 kg dicatat sebagai 1.000 gr. Sistem tidak mengonversi satuan secara otomatis.</p></div>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar bahan baku</h3><p>Nilai persediaan dihitung dengan metode rata-rata tertimbang bergerak.</p></div></div>
    <?php if (!$ingredients): ?><div class="empty-state">Belum ada bahan baku yang terdaftar.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Nama bahan</th><th>Stok tersedia</th><th>Stok minimum</th><th>Biaya rata-rata / satuan</th><th>Nilai persediaan</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($ingredients as $ingredient): ?><tr><td class="strong"><?= e($ingredient['name']) ?></td><td><?= e($ingredient['stock']) ?> <?= e($ingredient['unit']) ?></td><td><?= e($ingredient['minimum_stock']) ?> <?= e($ingredient['unit']) ?></td><td><?= rupiah($ingredient['average_cost']) ?></td><td><?= rupiah((float) $ingredient['stock'] * (float) $ingredient['average_cost']) ?></td><td><span class="badge <?= (float) $ingredient['stock'] <= (float) $ingredient['minimum_stock'] ? 'badge-warning' : 'badge-green' ?>"><?= (float) $ingredient['stock'] <= (float) $ingredient['minimum_stock'] ? 'Perlu restok' : 'Aman' ?></span></td><td><a class="text-link" href="?page=ingredients&edit=<?= (int) $ingredient['id'] ?>">Ubah</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
