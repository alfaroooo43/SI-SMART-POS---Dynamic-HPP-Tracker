<div class="section-intro"><div><h2>Kelola katalog produk</h2><p>Atur harga jual, jenis produk, dan ambang keuntungan.</p></div><span class="badge badge-green"><?= count($products) ?> produk</span></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3><?= $editing ? 'Ubah produk' : 'Tambah produk' ?></h3><p>HPP dihitung dari biaya terbaru yang tercatat.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="return_page" value="products">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label class="span-2">Nama produk<input name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" required></label>
            <label>Kategori<select name="category_id"><option value="">Tanpa kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Jenis produk
                <?php if ($editing): ?><input type="hidden" name="type" value="<?= e($editing['type']) ?>"><input value="<?= $editing['type'] === 'culinary' ? 'Kuliner · menggunakan resep' : 'Retail · stok barang' ?>" disabled>
                <?php else: ?><select name="type" required><option value="culinary">Kuliner · menggunakan resep</option><option value="retail">Retail · stok barang</option></select><?php endif; ?>
            </label>
            <label>Satuan jual<input name="unit" maxlength="30" value="<?= e($editing['unit'] ?? 'pcs') ?>" required></label>
            <label>Harga jual (Rp)<input type="number" name="selling_price" min="0" step="0.01" value="<?= e($editing['selling_price'] ?? '0') ?>" required></label>
            <label>Stok minimum<input type="number" name="minimum_stock" min="0" step="0.001" value="<?= e($editing['minimum_stock'] ?? '0') ?>" required><small>Stok retail dikelola lewat pembelian.</small></label>
            <label>Margin minimum (%)<input type="number" name="min_margin" min="0" max="99" step="0.1" value="<?= e($editing['min_margin'] ?? '20') ?>" required></label>
            <label>Target margin (%)<input type="number" name="target_margin" min="0" max="99" step="0.1" value="<?= e($editing['target_margin'] ?? '30') ?>" required></label>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah produk' ?></button><?php if ($editing): ?><a class="button button-quiet" href="?page=products">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Kategori produk</h3><p>Kelompokkan menu dan barang dagangan.</p></div></div>
        <form method="post" class="compact-form">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_category"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) ($category_edit['id'] ?? 0) ?>">
            <label class="grow">Nama kategori<input name="name" maxlength="100" value="<?= e($category_edit['name'] ?? '') ?>" required></label><button class="button button-primary" type="submit"><?= $category_edit ? 'Simpan' : 'Tambah' ?></button>
        </form>
        <?php if (!$categories): ?><div class="empty-compact">Belum ada kategori.</div><?php else: ?><ul class="category-list"><?php foreach ($categories as $category): ?><li><span><?= e($category['name']) ?></span><a class="text-link" href="?page=products&edit_category=<?= (int) $category['id'] ?>">Ubah</a></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar produk</h3><p>Rekomendasi harga adalah saran dan tidak mengubah harga jual.</p></div></div>
    <?php if (!$products): ?><div class="empty-state">Belum ada produk. Tambahkan katalog usaha Anda di atas.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Jenis</th><th>Stok</th><th>HPP/unit</th><th>Harga jual</th><th>Margin</th><th>Saran harga</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($products as $product):
        $cost = (float) $product['current_cost'];
        $price = (float) $product['selling_price'];
        $margin = $price > 0 ? (($price - $cost) / $price) * 100 : -100;
        $target = (float) $product['target_margin'];
        $recommendation = $target < 100 ? $cost / (1 - $target / 100) : 0;
    ?>
        <tr><td><strong><?= e($product['name']) ?></strong><small class="cell-sub"><?= e($product['category_name'] ?: 'Tanpa kategori') ?></small></td>
            <td><span class="badge <?= $product['type'] === 'culinary' ? 'badge-yellow' : 'badge-blue' ?>"><?= $product['type'] === 'culinary' ? 'Kuliner' : 'Retail' ?></span></td>
            <td><?= $product['type'] === 'retail' ? e($product['stock'] . ' ' . $product['unit']) : '<span class="muted">Resep</span>' ?></td>
            <td><?= rupiah($cost) ?></td><td><?= rupiah($price) ?></td>
            <td class="<?= $margin < (float) $product['min_margin'] ? 'margin-danger' : 'margin-good' ?>"><?= number_format($margin, 1, ',', '.') ?>%</td>
            <td><?= rupiah($recommendation) ?><small class="cell-sub">Target <?= e($target) ?>%</small></td>
            <td><span class="badge <?= $product['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $product['active'] ? 'Aktif' : 'Arsip' ?></span></td>
            <td class="actions-cell"><a class="text-link" href="?page=products&edit=<?= (int) $product['id'] ?>">Ubah</a>
                <form method="post" class="inline-form"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="active" value="<?= $product['active'] ? '0' : '1' ?>"><button class="text-button" type="submit"><?= $product['active'] ? 'Arsipkan' : 'Aktifkan' ?></button></form>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
