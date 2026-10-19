<section class="welcome-banner">
    <div><span class="eyebrow">RINGKASAN <?= e(strtoupper($from === $to ? 'HARI INI' : format_date($from) . ' - ' . format_date($to))) ?></span>
        <h2>Halo, <?= e($user['name']) ?> 👋</h2><p>Berikut gambaran performa usaha pada periode yang dipilih.</p></div>
    <a class="button button-yellow" href="?page=pos">＋ Transaksi baru</a>
</section>
<form class="filter-bar" method="get"><input type="hidden" name="page" value="dashboard"><label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>"></label><label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>"></label><button class="button button-outline" type="submit">Terapkan</button></form>
<section class="stats-grid">
    <article class="stat-card"><span class="stat-icon green">↗</span><span class="stat-label">Omzet</span><strong><?= rupiah($summary['revenue']) ?></strong><small>Penjualan setelah diskon</small></article>
    <article class="stat-card"><span class="stat-icon yellow">◈</span><span class="stat-label">Laba kotor</span><strong><?= rupiah($summary['gross_profit']) ?></strong><small>Omzet dikurangi HPP</small></article>
    <article class="stat-card"><span class="stat-icon blue">▤</span><span class="stat-label">Transaksi</span><strong><?= number_format((int) $summary['transactions'], 0, ',', '.') ?></strong><small>Transaksi terkonfirmasi</small></article>
    <article class="stat-card"><span class="stat-icon red">◉</span><span class="stat-label">Stok perlu dicek</span><strong><?= number_format($low_stock, 0, ',', '.') ?></strong><small>Produk atau bahan di bawah batas</small></article>
</section>
<div class="dashboard-grid">
    <section class="panel"><div class="panel-heading"><div><h3>Penjualan terbaru</h3><p>Aktivitas kasir terakhir</p></div><a class="text-link" href="?page=sales">Semua transaksi →</a></div>
        <?php if (!$recent_sales): ?><div class="empty-state">Belum ada transaksi pada sistem.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Nomor nota</th><th>Kasir</th><th>Waktu</th><th>Total</th><th></th></tr></thead><tbody>
            <?php foreach ($recent_sales as $sale): ?><tr><td class="strong"><?= e($sale['receipt_number']) ?></td><td><?= e($sale['cashier']) ?></td><td><?= e(format_date($sale['sold_at'], 'd M, H:i')) ?></td><td class="strong"><?= rupiah($sale['total']) ?></td><td><a class="text-link" href="?page=sales&id=<?= (int) $sale['id'] ?>">Lihat</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <div class="stack-panels">
        <section class="panel"><div class="panel-heading"><div><h3>Perhatian stok</h3><p><?= (int) $low_stock ?> item menyentuh batas minimum</p></div><span class="badge badge-warning">Stok</span></div>
            <?php if (!$low_items): ?><div class="empty-compact">Semua stok berada di atas batas minimum.</div><?php else: ?><ul class="simple-list"><?php foreach ($low_items as $item): ?><li><span><strong><?= e($item['name']) ?></strong><small><?= e($item['item_kind']) ?></small></span><span class="stock-warning"><?= e($item['stock']) ?> <?= e($item['unit']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
        <section class="panel"><div class="panel-heading"><div><h3>Pantau margin</h3><p>Produk di bawah batas minimum</p></div><span class="badge badge-danger"><?= count($alerts) ?> produk</span></div>
            <?php if (!$alerts): ?><div class="empty-compact">Belum ada produk di bawah target margin.</div><?php else: ?><ul class="simple-list"><?php foreach ($alerts as $alert): ?><li><span><strong><?= e($alert['name']) ?></strong><small>HPP <?= rupiah($alert['current_cost']) ?></small></span><span class="margin-danger"><?= number_format((float) $alert['margin'], 1, ',', '.') ?>%</span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    </div>
</div>
