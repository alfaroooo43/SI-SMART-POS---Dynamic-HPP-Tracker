<div class="section-intro"><div><h2>Kelola katalog produk</h2><p>Atur harga jual, jenis produk, dan ambang keuntungan.</p></div><span class="badge badge-green"><?= count($products) ?> produk ditemukan</span></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3><?= $editing ? 'Ubah produk' : 'Tambah produk' ?></h3><p>HPP dihitung dari biaya terbaru yang tercatat.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="return_page" value="products">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label class="span-2">Nama produk<input name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" required></label>
            <label>Kategori<select name="category_id"><option value="">Pilih kategori (opsional)</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Jenis produk
                <?php if ($editing): ?><input type="hidden" name="type" value="<?= e($editing['type']) ?>"><input value="<?= $editing['type'] === 'culinary' ? 'Kuliner · menggunakan resep' : 'Retail · stok barang' ?>" disabled>
                <?php else: ?><select name="type" required><option value="culinary">Kuliner · menggunakan resep</option><option value="retail">Retail · stok barang</option></select><?php endif; ?>
            </label>
            <label>Satuan jual<input name="unit" list="unit-options" maxlength="30" value="<?= e($editing['unit'] ?? 'pcs') ?>" required><small>Contoh: pcs, kg, liter, porsi, atau pack.</small></label>
            <datalist id="unit-options"><option value="pcs"><option value="kg"><option value="gr"><option value="liter"><option value="ml"><option value="porsi"><option value="box"><option value="botol"><option value="pack"></datalist>
            <label>Harga jual (Rp)<input type="number" name="selling_price" min="0" step="0.01" value="<?= e($editing['selling_price'] ?? '0') ?>" required></label>
            <label>Stok minimum<input type="number" name="minimum_stock" min="0" step="0.001" value="<?= e((float) ($editing['minimum_stock'] ?? 0)) ?>" required><small>Stok retail dikelola melalui menu Persediaan.</small></label>
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
        <?php if (!$categories): ?><div class="empty-compact">Belum ada kategori.</div><?php else: ?><ul class="category-list"><?php foreach ($categories as $category): ?><li><span><?= e($category['name']) ?></span><span class="actions-cell"><a class="text-link" href="?page=products&edit_category=<?= (int) $category['id'] ?>">Ubah</a><details class="confirm-action"><summary>Hapus</summary><form method="post" class="confirm-box"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><p>Hapus kategori ini? Kategori yang masih dipakai produk harus dilepas terlebih dahulu.</p><button class="button" type="submit">Ya, hapus kategori</button></form></details></span></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar produk</h3><p>Rekomendasi harga adalah saran dan tidak mengubah harga jual.</p></div></div>
    <form class="filter-bar" method="get"><input type="hidden" name="page" value="products"><label>Cari nama produk<input name="q" maxlength="100" value="<?= e($product_search) ?>" placeholder="Contoh: kopi"></label><label>Kategori<select name="category_id"><option value="">Semua kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $product_category_filter === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label><label>Jenis<select name="type"><option value="">Semua jenis</option><option value="retail" <?= $product_type_filter === 'retail' ? 'selected' : '' ?>>Retail</option><option value="culinary" <?= $product_type_filter === 'culinary' ? 'selected' : '' ?>>Kuliner</option></select></label><label>Status<select name="status"><option value="">Semua status</option><option value="active" <?= $product_status_filter === 'active' ? 'selected' : '' ?>>Aktif</option><option value="archived" <?= $product_status_filter === 'archived' ? 'selected' : '' ?>>Arsip</option></select></label><button class="button button-outline" type="submit">Cari</button><a class="button button-quiet" href="?page=products">Reset</a></form>
    <?php if (!$products): ?><div class="empty-state"><?= $product_search !== '' || $product_category_filter !== null || $product_status_filter !== '' || $product_type_filter !== '' ? 'Tidak ada produk yang cocok dengan pencarian atau filter.' : 'Belum ada produk. Tambahkan katalog usaha Anda di atas.' ?></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Jenis</th><th>Stok</th><th>HPP/unit</th><th>Harga jual</th><th>Margin</th><th>Saran harga</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($products as $product):
        $costAvailable = $product['current_cost'] !== null;
        $cost = $costAvailable ? (float) $product['current_cost'] : 0.0;
        $price = (float) $product['selling_price'];
        $margin = $costAvailable && $price > 0 ? (($price - $cost) / $price) * 100 : null;
        $target = (float) $product['target_margin'];
        $recommendation = $costAvailable && $target < 100 ? $cost / (1 - $target / 100) : null;
    ?>
        <tr><td><strong><?= e($product['name']) ?></strong><small class="cell-sub"><?= e($product['category_name'] ?: 'Tanpa kategori') ?></small></td>
            <td><span class="badge <?= $product['type'] === 'culinary' ? 'badge-yellow' : 'badge-blue' ?>"><?= $product['type'] === 'culinary' ? 'Kuliner' : 'Retail' ?></span></td>
            <td><?= $product['type'] === 'retail' ? e(format_quantity($product['stock']) . ' ' . $product['unit']) : '<span class="muted">Resep</span>' ?></td>
            <td><?= $costAvailable ? rupiah($cost) : '<span class="badge badge-warning">HPP belum tersedia</span>' ?><?php if (!$costAvailable && !empty($product['missing_cost_ingredients'])): ?><small class="cell-sub">Harga belum diisi: <?= e($product['missing_cost_ingredients']) ?></small><?php endif; ?></td><td><?= rupiah($price) ?></td>
            <td class="<?= $margin === null ? 'muted' : ($margin < (float) $product['min_margin'] ? 'margin-danger' : 'margin-good') ?>"><?= $margin === null ? '—' : number_format($margin, 1, ',', '.') . '%' ?></td>
            <td><?= $recommendation === null ? '—' : rupiah($recommendation) ?><small class="cell-sub">Target <?= e($target) ?>%</small></td>
            <td><span class="badge <?= $product['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $product['active'] ? 'Aktif' : 'Arsip' ?></span></td>
            <td class="actions-cell"><a class="text-link" href="?page=products&edit=<?= (int) $product['id'] ?>">Ubah</a>
                <?php if ($product['active']): ?><details class="confirm-action"><summary>Arsipkan</summary><form method="post" class="confirm-box"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="active" value="0"><p>Produk retail harus memiliki stok nol. Arsip tidak menghapus histori transaksi.</p><button class="button" type="submit">Ya, arsipkan</button></form></details>
                <?php else: ?><form method="post" class="inline-form"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="active" value="1"><button class="text-button" type="submit">Aktifkan</button></form><?php endif; ?>
                <details class="confirm-action"><summary>Hapus permanen</summary><form method="post" class="confirm-box"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="return_page" value="products"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><p>Hapus permanen akan menghapus resep terkait jika aman. Produk dengan stok, riwayat pembelian, penjualan, mutasi, atau HPP tetap dilindungi dari penghapusan.</p><button class="button" type="submit">Ya, hapus produk</button></form></details>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
