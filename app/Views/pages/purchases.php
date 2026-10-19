<div class="section-intro"><div><h2>Pembelian bahan & barang</h2><p>Konfirmasi pembelian untuk memasukkan stok dan menghitung rata-rata biaya terbaru.</p></div><span class="badge badge-yellow">Weighted average</span></div>
<?php if (!$suppliers): ?><div class="notice notice-warning">Tambahkan supplier terlebih dahulu di halaman <a href="?page=suppliers">Supplier</a> sebelum mencatat pembelian.</div><?php endif; ?>
<div class="two-column purchase-layout">
    <section class="panel"><div class="panel-heading"><div><h3>Catat pembelian baru</h3><p>Hingga enam baris barang per nota.</p></div></div>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="create_purchase"><input type="hidden" name="return_page" value="purchases">
            <div class="form-grid">
                <label>Supplier<select name="supplier_id" required><option value="">Pilih supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>"><?= e($supplier['name']) ?></option><?php endforeach; ?></select></label>
                <label>Tanggal pembelian<input type="date" name="purchased_at" value="<?= e(date('Y-m-d')) ?>" required></label>
                <label class="span-2">Nomor nota / referensi<input name="invoice_number" maxlength="100" placeholder="Opsional"></label>
            </div>
            <div class="table-wrap"><table class="entry-table"><thead><tr><th>Barang / bahan</th><th>Kuantitas</th><th>Harga / unit</th></tr></thead><tbody>
                <?php for ($i = 0; $i < 6; $i++): ?><tr><td><select name="item_ref[]" aria-label="Barang pembelian <?= $i + 1 ?>"><option value="">Pilih barang</option><optgroup label="Bahan baku"><?php foreach ($ingredients as $ingredient): ?><option value="ingredient:<?= (int) $ingredient['id'] ?>"><?= e($ingredient['name']) ?> (<?= e($ingredient['unit']) ?>)</option><?php endforeach; ?></optgroup><optgroup label="Produk retail"><?php foreach ($retail_products as $product): ?><option value="product:<?= (int) $product['id'] ?>"><?= e($product['name']) ?> (<?= e($product['unit']) ?>)</option><?php endforeach; ?></optgroup></select></td><td><input type="number" name="quantity[]" min="0" step="0.001" placeholder="0" aria-label="Kuantitas barang <?= $i + 1 ?>"></td><td><input type="number" name="unit_cost[]" min="0" step="0.01" placeholder="Rp" aria-label="Harga per unit barang <?= $i + 1 ?>"></td></tr><?php endfor; ?>
            </tbody></table></div>
            <div class="form-actions"><button class="button button-primary" type="submit" <?= !$suppliers ? 'disabled' : '' ?>>Simpan sebagai draft</button><span class="muted">Stok belum berubah sebelum dikonfirmasi.</span></div>
        </form>
    </section>
    <aside class="panel"><div class="panel-heading"><div><h3>Metode biaya</h3><p>Rata-rata tertimbang bergerak</p></div><span class="stat-icon green">↗</span></div>
        <div class="callout"><strong>Biaya rata-rata unit baru</strong><p>(Nilai stok lama + nilai pembelian) ÷ (stok lama + jumlah pembelian).</p></div>
        <div class="callout callout-soft"><strong>Pembatalan yang aman</strong><p>Draft dapat dibatalkan. Pembelian terkonfirmasi tidak dapat dibatalkan supaya mutasi persediaan dan HPP historis tidak berubah diam-diam.</p></div>
    </aside>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Riwayat pembelian</h3><p>50 pembelian terbaru.</p></div></div>
    <?php if (!$purchases): ?><div class="empty-state">Belum ada pembelian tercatat.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Supplier</th><th>No. nota</th><th>Dicatat oleh</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($purchases as $purchase): ?><tr><td><?= e(format_date($purchase['purchased_at'])) ?></td><td class="strong"><?= e($purchase['supplier']) ?></td><td><?= e($purchase['invoice_number'] ?: '—') ?></td><td><?= e($purchase['created_by']) ?></td><td><?= rupiah($purchase['total']) ?></td><td><span class="badge <?= $purchase['status'] === 'confirmed' ? 'badge-green' : ($purchase['status'] === 'cancelled' ? 'badge-gray' : 'badge-warning') ?>"><?= e(ucfirst($purchase['status'])) ?></span></td>
    <td><?php if ($purchase['status'] === 'draft'): ?><div class="actions-cell"><form method="post"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="confirm_purchase"><input type="hidden" name="return_page" value="purchases"><input type="hidden" name="id" value="<?= (int) $purchase['id'] ?>"><button class="text-button text-positive" type="submit">Konfirmasi</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="cancel_purchase"><input type="hidden" name="return_page" value="purchases"><input type="hidden" name="id" value="<?= (int) $purchase['id'] ?>"><button class="text-button text-danger" type="submit">Batalkan</button></form></div><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
