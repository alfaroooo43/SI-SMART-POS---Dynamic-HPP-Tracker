<div class="section-intro"><div><h2>Stok dan histori persediaan</h2><p>Setiap mutasi dicatat untuk menjaga jejak audit dan rekonsiliasi stok.</p></div><a class="button button-outline" href="?page=purchases">Pembelian →</a></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3>Penyesuaian stok</h3><p>Gunakan untuk koreksi stok fisik atau kehilangan barang.</p></div><span class="badge badge-warning">Tercatat</span></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="adjust_inventory"><input type="hidden" name="return_page" value="inventory">
            <label class="span-2">Barang / bahan<select name="item_ref" required><option value="">Pilih stok</option><optgroup label="Bahan baku"><?php foreach ($ingredients as $ingredient): ?><option value="ingredient:<?= (int) $ingredient['id'] ?>"><?= e($ingredient['name']) ?> · stok <?= e($ingredient['stock']) ?> <?= e($ingredient['unit']) ?></option><?php endforeach; ?></optgroup><optgroup label="Produk retail"><?php foreach ($products as $product): ?><option value="product:<?= (int) $product['id'] ?>"><?= e($product['name']) ?> · stok <?= e($product['stock']) ?> <?= e($product['unit']) ?></option><?php endforeach; ?></optgroup></select></label>
            <label>Perubahan kuantitas<input type="number" name="quantity_change" step="0.001" placeholder="contoh: 5 atau -2" required><small>Positif menambah stok, negatif mengurangi stok.</small></label>
            <label>Alasan penyesuaian<input name="note" maxlength="255" placeholder="Contoh: hasil stok opname" required></label>
            <div class="span-2"><button class="button button-primary" type="submit">Simpan penyesuaian</button></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Nilai persediaan</h3><p>Stok tersedia × biaya rata-rata unit.</p></div></div>
        <?php $stockValue = 0.0; foreach ($products as $product) { $stockValue += (float) $product['stock'] * (float) $product['average_cost']; } foreach ($ingredients as $ingredient) { $stockValue += (float) $ingredient['stock'] * (float) $ingredient['average_cost']; } ?>
        <div class="inventory-value"><span>Total nilai stok saat ini</span><strong><?= rupiah($stockValue) ?></strong></div>
        <div class="callout callout-soft"><strong>Catatan</strong><p>Penyesuaian jumlah tidak mengubah biaya rata-rata unit. Pembelian terkonfirmasi memperbarui biaya rata-rata menggunakan metode rata-rata tertimbang bergerak.</p></div>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Mutasi stok terbaru</h3><p>50 perubahan stok terakhir, termasuk pembelian, penjualan, dan koreksi manual.</p></div></div>
    <?php if (!$movements): ?><div class="empty-state">Belum ada mutasi persediaan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th>Perubahan</th><th>Biaya/unit</th><th>Catatan / aktor</th></tr></thead><tbody>
    <?php foreach ($movements as $movement): ?><tr><td><?= e(format_date($movement['created_at'], 'd M Y, H:i')) ?></td><td class="strong"><?= e($movement['item_name']) ?></td><td><span class="badge <?= $movement['movement_type'] === 'purchase' ? 'badge-green' : ($movement['movement_type'] === 'sale' ? 'badge-blue' : 'badge-warning') ?>"><?= e(ucfirst($movement['movement_type'])) ?></span></td><td class="<?= (float) $movement['quantity_change'] < 0 ? 'margin-danger' : 'margin-good' ?>"><?= (float) $movement['quantity_change'] > 0 ? '+' : '' ?><?= e($movement['quantity_change']) ?></td><td><?= rupiah($movement['unit_cost']) ?></td><td><?= e($movement['note'] ?: ($movement['actor'] ?: $movement['movement_type'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Riwayat perubahan biaya</h3><p>Biaya rata-rata bahan dan HPP resep saat berubah.</p></div></div>
    <?php if (!$cost_history): ?><div class="empty-compact">Riwayat biaya akan muncul setelah pembelian dikonfirmasi atau resep diubah.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Barang / produk</th><th>Sumber perubahan</th><th>Biaya sebelumnya</th><th>Biaya terbaru</th></tr></thead><tbody><?php foreach ($cost_history as $history): ?><tr><td><?= e(format_date($history['changed_at'], 'd M Y, H:i')) ?></td><td class="strong"><?= e($history['item_name'] ?: 'Barang diarsipkan') ?></td><td><?= e(ucfirst($history['source_type'])) ?></td><td><?= rupiah($history['old_cost']) ?></td><td class="strong"><?= rupiah($history['new_cost']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
