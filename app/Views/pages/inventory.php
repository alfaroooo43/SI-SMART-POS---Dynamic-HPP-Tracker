<div class="section-intro"><div><h2>Stok dan histori persediaan</h2><p>Pantau jumlah barang dan catat perubahan stok dengan alasan yang jelas.</p></div></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3>Penyesuaian stok</h3><p>Gunakan untuk koreksi stok fisik atau kehilangan barang.</p></div><span class="badge badge-warning">Tercatat</span></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="adjust_inventory"><input type="hidden" name="return_page" value="inventory">
            <label class="span-2">Barang / bahan<select name="item_ref" required><option value="">Pilih stok</option><optgroup label="Bahan baku"><?php foreach ($ingredients as $ingredient): ?><option value="ingredient:<?= (int) $ingredient['id'] ?>"><?= e($ingredient['name']) ?> · stok <?= e(format_quantity($ingredient['stock'])) ?> <?= e($ingredient['unit']) ?></option><?php endforeach; ?></optgroup><optgroup label="Produk retail"><?php foreach ($products as $product): ?><option value="product:<?= (int) $product['id'] ?>"><?= e($product['name']) ?> · stok <?= e(format_quantity($product['stock'])) ?> <?= e($product['unit']) ?></option><?php endforeach; ?></optgroup></select></label>
            <label>Perubahan kuantitas<input type="number" name="quantity_change" step="0.001" placeholder="contoh: 5 atau -2" required><small>Positif menambah stok, negatif mengurangi stok. Hindari pemisah ribuan; gunakan titik untuk desimal.</small></label>
            <label>Alasan penyesuaian<input name="note" maxlength="255" placeholder="Contoh: hasil stok opname" required></label>
            <div class="span-2"><button class="button button-primary" type="submit">Simpan penyesuaian</button></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Nilai persediaan</h3><p>Stok tersedia × biaya rata-rata unit.</p></div></div>
        <?php $stockValue = 0.0; $itemsMissingCost = 0; foreach ($products as $product) { if ((float) $product['average_cost'] > 0) { $stockValue += (float) $product['stock'] * (float) $product['average_cost']; } else { $itemsMissingCost++; } } foreach ($ingredients as $ingredient) { if ((float) $ingredient['average_cost'] > 0) { $stockValue += (float) $ingredient['stock'] * (float) $ingredient['average_cost']; } else { $itemsMissingCost++; } } ?>
        <div class="inventory-value"><span>Total nilai stok saat ini</span><strong><?= rupiah($stockValue) ?></strong></div>
        <div class="callout callout-soft"><strong>Catatan</strong><p>Penyesuaian jumlah tidak mengubah biaya rata-rata unit. HPP dihitung dari biaya yang tersimpan pada data barang dan resep.<?php if ($itemsMissingCost > 0): ?> <?= $itemsMissingCost ?> barang/bahan belum memiliki biaya, sehingga belum masuk ke total nilai persediaan.<?php endif; ?></p></div>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Saldo stok</h3><p>Edit jumlah menetapkan saldo baru dan mencatat selisih sebagai koreksi. Kosongkan stok tidak menghapus barang atau histori.</p></div></div>
    <?php if (!$stock_items): ?><div class="empty-state">Belum ada barang aktif yang dikelola stoknya.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Barang</th><th>Jenis</th><th>Stok saat ini</th><th>Biaya/unit</th><th>Aksi stok</th></tr></thead><tbody>
    <?php foreach ($stock_items as $item): $itemRef = $item['item_type'] . ':' . (int) $item['id']; ?><tr>
        <td class="strong"><?= e($item['name']) ?></td><td><?= $item['item_type'] === 'product' ? 'Produk retail' : 'Bahan baku' ?></td>
        <td><?= e(format_quantity($item['stock'])) ?> <?= e($item['unit']) ?></td><td><?= (float) $item['average_cost'] > 0 ? rupiah($item['average_cost']) : '<span class="badge badge-warning">Biaya belum diisi</span>' ?></td>
        <td><div class="actions-cell"><details class="confirm-action"><summary>Ubah stok</summary><form method="post" class="confirm-box">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="set_inventory_stock"><input type="hidden" name="return_page" value="inventory"><input type="hidden" name="item_ref" value="<?= e($itemRef) ?>">
            <label>Jumlah stok baru<input type="number" name="stock" min="0" step="0.001" value="<?= e((float) $item['stock']) ?>" required><small>Gunakan angka biasa, tanpa pemisah ribuan. Desimal memakai titik.</small></label>
            <label>Alasan<input name="note" maxlength="255" required placeholder="Contoh: hasil stok opname"></label><button class="button" type="submit">Simpan stok</button>
        </form></details>
        <?php if ((float) $item['stock'] > 0): ?><details class="confirm-action"><summary>Kosongkan stok</summary><form method="post" class="confirm-box">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="set_inventory_stock"><input type="hidden" name="return_page" value="inventory"><input type="hidden" name="item_ref" value="<?= e($itemRef) ?>"><input type="hidden" name="stock" value="0">
            <p>Saldo akan dibuat nol melalui catatan koreksi; barang dan histori tidak dihapus.</p><label>Alasan<input name="note" maxlength="255" required placeholder="Contoh: stok rusak atau hasil opname"></label><button class="button" type="submit">Ya, kosongkan stok</button>
        </form></details><?php endif; ?></div></td>
    </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Perubahan stok terbaru</h3><p>50 perubahan terakhir, termasuk stok masuk, stok keluar, dan koreksi.</p></div></div>
    <?php if (!$movements): ?><div class="empty-state">Belum ada mutasi persediaan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th>Perubahan</th><th>Biaya/unit</th><th>Catatan / aktor</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($movements as $movement):
        $movementLabel = $movement['movement_type'] === 'adjustment' ? 'Koreksi' : ((float) $movement['quantity_change'] < 0 ? 'Stok keluar' : 'Stok masuk');
        $canEditMovement = $movement['movement_type'] === 'adjustment'
            && $movement['reference_type'] === 'manual'
            && !str_starts_with((string) $movement['note'], 'Pembatalan ');
        $movementDateTime = date('Y-m-d\TH:i', strtotime($movement['created_at']));
    ?><tr>
        <td><?= e(format_date($movement['created_at'], 'd M Y, H:i')) ?></td><td class="strong"><?= e($movement['item_name']) ?></td>
        <td><span class="badge <?= $movementLabel === 'Stok masuk' ? 'badge-green' : ($movementLabel === 'Stok keluar' ? 'badge-blue' : 'badge-warning') ?>"><?= e($movementLabel) ?></span></td>
        <td class="<?= (float) $movement['quantity_change'] < 0 ? 'margin-danger' : 'margin-good' ?>"><?= (float) $movement['quantity_change'] > 0 ? '+' : '' ?><?= e(format_quantity($movement['quantity_change'])) ?> <?= e($movement['unit_snapshot']) ?></td>
        <td><?= rupiah($movement['unit_cost']) ?></td><td><?= e($movement['note'] ?: ($movement['actor'] ?: $movementLabel)) ?></td>
        <td><?php if ($canEditMovement): ?><div class="actions-cell"><details class="confirm-action"><summary>Edit</summary><form method="post" class="confirm-box">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="update_inventory_movement"><input type="hidden" name="return_page" value="inventory"><input type="hidden" name="id" value="<?= (int) $movement['id'] ?>">
            <label>Tanggal dan waktu<input type="datetime-local" name="created_at" value="<?= e($movementDateTime) ?>" required></label>
            <label>Perubahan stok<input type="number" name="quantity_change" step="0.001" value="<?= e((float) $movement['quantity_change']) ?>" required><small>Positif menambah, negatif mengurangi. Maksimal tiga desimal.</small></label>
            <label>Keterangan<input name="note" maxlength="255" value="<?= e($movement['note']) ?>" required></label><button class="button" type="submit">Simpan koreksi</button>
        </form></details><details class="confirm-action"><summary>Hapus</summary><form method="post" class="confirm-box">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="delete_inventory_movement"><input type="hidden" name="return_page" value="inventory"><input type="hidden" name="id" value="<?= (int) $movement['id'] ?>">
            <p>Saldo stok akan dikoreksi sekaligus. Salinan audit mutasi tetap disimpan.</p><button class="button" type="submit">Ya, hapus koreksi</button>
        </form></details></div><?php else: ?><span class="muted">Histori terkunci</span><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Audit koreksi stok</h3><p>Perubahan dan penghapusan koreksi manual menyimpan nilai sebelumnya, waktu, serta pengguna yang melakukan aksi.</p></div></div>
    <?php if (!$movement_audits): ?><div class="empty-compact">Belum ada koreksi atau penghapusan mutasi manual.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu audit</th><th>Barang</th><th>Aksi</th><th>Nilai sebelumnya</th><th>Keterangan lama</th><th>Diubah oleh</th></tr></thead><tbody>
    <?php foreach ($movement_audits as $audit): ?><tr>
        <td><?= e(format_date($audit['changed_at'], 'd M Y, H:i')) ?></td><td class="strong"><?= e($audit['item_name_snapshot']) ?></td>
        <td><?= $audit['action'] === 'updated' ? 'Diedit' : 'Dihapus' ?></td>
        <td><?= e(format_quantity($audit['quantity_change'])) ?> <?= e($audit['unit_snapshot']) ?></td>
        <td><?= e($audit['note'] ?: '—') ?></td><td><?= e($audit['changed_by'] ?: 'Pengguna tidak aktif') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Riwayat perubahan biaya</h3><p>Biaya rata-rata bahan dan HPP resep saat berubah.</p></div></div>
    <?php if (!$cost_history): ?><div class="empty-compact">Riwayat biaya akan muncul setelah biaya rata-rata atau resep berubah.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Barang / produk</th><th>Sumber perubahan</th><th>Biaya sebelumnya</th><th>Biaya terbaru</th></tr></thead><tbody><?php foreach ($cost_history as $history): ?><tr><td><?= e(format_date($history['changed_at'], 'd M Y, H:i')) ?></td><td class="strong"><?= e($history['item_name'] ?: 'Barang diarsipkan') ?></td><td><?= $history['source_type'] === 'recipe' ? 'Perubahan resep' : 'Perubahan biaya barang' ?></td><td><?= rupiah($history['old_cost']) ?></td><td class="strong"><?= rupiah($history['new_cost']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
