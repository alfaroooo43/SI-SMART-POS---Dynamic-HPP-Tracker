<?php if (!empty($selected_sale)): ?>
<div class="section-intro"><div><h2>Nota <?= e($selected_sale['receipt_number']) ?></h2><p><?= e(format_date($selected_sale['sold_at'], 'd F Y, H:i')) ?> · Kasir <?= e($selected_sale['cashier']) ?></p></div><a class="button button-outline" href="?page=sales">← Kembali ke transaksi</a></div>
<div class="receipt-layout">
    <section class="panel"><div class="panel-heading"><div><h3>Rincian produk</h3><p>Harga dan HPP disimpan pada saat transaksi.</p></div><span class="badge badge-green">Lunas</span></div>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Jumlah</th><th>Harga/unit</th><th>HPP/unit</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($sale_items as $item): ?><tr><td class="strong"><?= e($item['product_name']) ?></td><td><?= e($item['quantity']) ?></td><td><?= rupiah($item['unit_price']) ?></td><td><?= rupiah($item['unit_cost']) ?></td><td><?= rupiah($item['line_total']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <aside class="panel"><div class="panel-heading"><div><h3>Pembayaran</h3><p><?= e(strtoupper($selected_sale['payment_method'])) ?></p></div></div>
        <div class="totals-box"><div><span>Subtotal</span><strong><?= rupiah($selected_sale['subtotal']) ?></strong></div><div><span>Diskon</span><strong>− <?= rupiah($selected_sale['discount']) ?></strong></div><div class="total-grand"><span>Total</span><strong><?= rupiah($selected_sale['total']) ?></strong></div><div><span>HPP penjualan</span><strong><?= rupiah($selected_sale['cost_total']) ?></strong></div><div><span>Laba kotor</span><strong class="<?= (float) $selected_sale['total'] - (float) $selected_sale['cost_total'] < 0 ? 'margin-danger' : 'margin-good' ?>"><?= rupiah((float) $selected_sale['total'] - (float) $selected_sale['cost_total']) ?></strong></div><div><span>Dibayar</span><strong><?= rupiah($selected_sale['amount_paid']) ?></strong></div><div><span>Kembalian</span><strong><?= rupiah($selected_sale['change_amount']) ?></strong></div></div>
    </aside>
</div>
<?php else: ?>
<div class="section-intro"><div><h2>Riwayat transaksi</h2><p>Catatan penjualan yang telah tersimpan.</p></div><a class="button button-yellow" href="?page=pos">＋ Transaksi baru</a></div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Daftar penjualan</h3><p><?= $user['role'] === 'cashier' ? 'Transaksi yang Anda proses.' : '100 transaksi terbaru.' ?></p></div></div>
    <?php if (!$sales): ?><div class="empty-state">Belum ada transaksi penjualan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>No. transaksi</th><th>Tanggal & waktu</th><th>Kasir</th><th>Omzet</th><th>HPP</th><th>Laba kotor</th><th>Pembayaran</th><th></th></tr></thead><tbody>
    <?php foreach ($sales as $sale): $profit = (float) $sale['total'] - (float) $sale['cost_total']; ?><tr><td class="strong"><?= e($sale['receipt_number']) ?></td><td><?= e(format_date($sale['sold_at'], 'd M Y, H:i')) ?></td><td><?= e($sale['cashier']) ?></td><td><?= rupiah($sale['total']) ?></td><td><?= rupiah($sale['cost_total']) ?></td><td class="<?= $profit < 0 ? 'margin-danger' : 'margin-good' ?>"><?= rupiah($profit) ?></td><td><span class="badge badge-gray"><?= e(strtoupper($sale['payment_method'])) ?></span></td><td><a class="text-link" href="?page=sales&id=<?= (int) $sale['id'] ?>">Nota →</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
