<div class="section-intro"><div><h2>Transaksi penjualan</h2><p>Pilih produk dan jumlah, lalu tinjau total sebelum transaksi disimpan.</p></div><a class="button button-outline" href="?page=sales">Riwayat transaksi</a></div>
<?php if (!$products): ?><div class="notice notice-warning">Belum ada produk aktif untuk dijual. Minta admin menambahkan produk, resep, dan stok yang diperlukan.</div><?php endif; ?>
<?php if ($preview): ?>
<div class="pos-grid">
    <section class="panel"><div class="panel-heading"><div><h3>Ringkasan keranjang</h3><p>Biaya dan harga diambil ulang saat transaksi disimpan.</p></div><span class="badge badge-yellow">Tinjau</span></div>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Jumlah</th><th>Harga</th><th>HPP/unit</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($preview['data']['lines'] as $line): ?><tr><td class="strong"><?= e($line['product']['name']) ?></td><td><?= e($line['quantity']) ?> <?= e($line['product']['unit']) ?></td><td><?= rupiah($line['product']['selling_price']) ?></td><td><?= rupiah($line['unit_cost']) ?></td><td><?= rupiah($line['line_total']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <div class="totals-box"><div><span>Subtotal</span><strong><?= rupiah($preview['data']['subtotal']) ?></strong></div><div><span>Diskon</span><strong>− <?= rupiah($preview['data']['discount']) ?></strong></div><div><span>HPP penjualan</span><strong><?= rupiah($preview['data']['cost_total']) ?></strong></div><div class="total-grand"><span>Total pembayaran</span><strong><?= rupiah($preview['data']['total']) ?></strong></div><div><span>Perkiraan laba kotor</span><strong class="<?= $preview['data']['total'] - $preview['data']['cost_total'] < 0 ? 'margin-danger' : 'margin-good' ?>"><?= rupiah($preview['data']['total'] - $preview['data']['cost_total']) ?></strong></div></div>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Selesaikan pembayaran</h3><p>Transaksi akan mengurangi stok secara atomik.</p></div></div>
        <form method="post" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="create_sale"><input type="hidden" name="return_page" value="pos"><input type="hidden" name="checkout_token" value="<?= e($preview['checkout_token']) ?>">
            <?php foreach ($preview['quantities'] as $id => $quantity): ?><input type="hidden" name="product_id[]" value="<?= (int) $id ?>"><input type="hidden" name="quantity[]" value="<?= e($quantity) ?>"><?php endforeach; ?>
            <input type="hidden" name="discount" value="<?= e($preview['discount']) ?>">
            <label>Metode pembayaran<select name="payment_method" required><?php foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'debit' => 'Kartu debit'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $preview['payment_method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Uang diterima (Rp)<input type="number" name="amount_paid" min="<?= e($preview['data']['total']) ?>" step="0.01" value="<?= e($preview['data']['total']) ?>" required><small>Untuk tunai, masukkan nominal uang pelanggan. Kembalian dihitung otomatis.</small></label>
            <button class="button button-primary button-wide" type="submit">Konfirmasi & simpan transaksi</button>
        </form>
        <form method="post" class="form-bottom"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="clear_sale_preview"><input type="hidden" name="return_page" value="pos"><button class="button button-quiet" type="submit">Kembali, ubah keranjang</button></form>
    </section>
</div>
<?php else: ?>
<section class="panel pos-entry"><div class="panel-heading"><div><h3>Masukkan produk</h3><p>Gunakan baris berikut untuk memilih beberapa item. Harga ditampilkan pada pilihan.</p></div><span class="stat-icon green">＋</span></div>
    <form method="post" class="form-stack">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="preview_sale"><input type="hidden" name="return_page" value="pos">
        <div class="table-wrap"><table class="entry-table"><thead><tr><th>Produk</th><th>Kuantitas</th></tr></thead><tbody>
        <?php for ($i = 0; $i < 8; $i++): ?><tr><td><select name="product_id[]" aria-label="Produk baris <?= $i + 1 ?>"><option value="">Pilih produk</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> · <?= rupiah($product['selling_price']) ?><?= $product['type'] === 'retail' ? ' · stok ' . e($product['stock']) : '' ?></option><?php endforeach; ?></select></td><td><input type="number" name="quantity[]" min="0" step="0.001" placeholder="0" aria-label="Kuantitas baris <?= $i + 1 ?>"></td></tr><?php endfor; ?>
        </tbody></table></div>
        <div class="form-grid pos-bottom"><label>Diskon transaksi (Rp)<input type="number" name="discount" min="0" step="0.01" value="0" required><small>Diskon tidak boleh melebihi subtotal.</small></label><label>Metode pembayaran<select name="payment_method"><?php foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'debit' => 'Kartu debit'] as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><div class="align-end"><button class="button button-yellow button-wide" type="submit" <?= !$products ? 'disabled' : '' ?>>Tinjau total transaksi →</button></div></div>
    </form>
</section>
<?php endif; ?>
