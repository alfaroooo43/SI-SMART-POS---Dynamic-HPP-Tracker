<section class="welcome-banner">
    <div><span class="eyebrow">RINGKASAN <?= e(strtoupper($from === $to ? 'HARI INI' : format_date($from) . ' - ' . format_date($to))) ?></span>
        <h2>Halo, <?= e($user['name']) ?> 👋</h2><p>Ringkasan produk, stok, HPP, dan biaya usaha.</p></div>
    <a class="button button-yellow" href="?page=products">Kelola produk</a>
</section>
<form class="filter-bar" method="get"><input type="hidden" name="page" value="dashboard"><label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>"></label><label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>"></label><button class="button button-outline" type="submit">Terapkan</button></form>
<section class="stats-grid">
    <article class="stat-card"><span class="stat-icon blue">▦</span><span class="stat-label">Produk aktif</span><strong><?= number_format($product_count, 0, ',', '.') ?></strong><small>Produk yang tersedia di katalog</small></article>
    <article class="stat-card"><span class="stat-icon red">−</span><span class="stat-label">Biaya operasional</span><strong><?= rupiah($summary['expenses']) ?></strong><small>Biaya tercatat pada periode</small></article>
    <article class="stat-card"><span class="stat-icon blue">▤</span><span class="stat-label">Jumlah catatan biaya</span><strong><?= number_format((int) $summary['expense_count'], 0, ',', '.') ?></strong><small>Biaya aktif pada periode</small></article>
    <article class="stat-card"><span class="stat-icon red">◉</span><span class="stat-label">Stok perlu dicek</span><strong><?= number_format($low_stock, 0, ',', '.') ?></strong><small>Produk atau bahan di bawah batas</small></article>
    <article class="stat-card"><span class="stat-icon yellow">↗</span><span class="stat-label">Margin perlu ditinjau</span><strong><?= number_format(count($alerts), 0, ',', '.') ?></strong><small>Produk di bawah margin minimum</small></article>
</section>
<div class="dashboard-grid">
    <section class="panel"><div class="panel-heading"><div><h3>Perubahan stok terbaru</h3><p>Catatan jumlah stok yang masuk, keluar, atau dikoreksi.</p></div><a class="text-link" href="?page=inventory">Lihat stok →</a></div>
        <?php if (!$recent_movements): ?><div class="empty-state">Belum ada perubahan stok.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Barang</th><th>Perubahan</th><th>Catatan</th></tr></thead><tbody>
            <?php foreach ($recent_movements as $movement): ?><tr><td><?= e(format_date($movement['created_at'], 'd M, H:i')) ?></td><td class="strong"><?= e($movement['item_name']) ?></td><td class="<?= (float) $movement['quantity_change'] < 0 ? 'margin-danger' : 'margin-good' ?>"><?= (float) $movement['quantity_change'] > 0 ? '+' : '' ?><?= e(format_quantity($movement['quantity_change'])) ?> <?= e($movement['unit_snapshot']) ?></td><td><?= e($movement['note'] ?: 'Perubahan stok tercatat') ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <div class="stack-panels">
        <section class="panel"><div class="panel-heading"><div><h3>Perhatian stok</h3><p><?= (int) $low_stock ?> item menyentuh batas minimum</p></div><span class="badge badge-warning">Stok</span></div>
            <?php if (!$low_items): ?><div class="empty-compact">Semua stok berada di atas batas minimum.</div><?php else: ?><ul class="simple-list"><?php foreach ($low_items as $item): ?><li><span><strong><?= e($item['name']) ?></strong><small><?= e($item['item_kind']) ?></small></span><span class="stock-warning"><?= e(format_quantity($item['stock'])) ?> <?= e($item['unit']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
        <section class="panel"><div class="panel-heading"><div><h3>Pantau margin</h3><p>Produk di bawah batas minimum</p></div><span class="badge badge-danger"><?= count($alerts) ?> produk</span></div>
            <?php if (!$alerts): ?><div class="empty-compact">Belum ada produk di bawah target margin.</div><?php else: ?><ul class="simple-list"><?php foreach ($alerts as $alert): ?><li><span><strong><?= e($alert['name']) ?></strong><small>HPP <?= rupiah($alert['current_cost']) ?></small></span><span class="margin-danger"><?= number_format((float) $alert['margin'], 1, ',', '.') ?>%</span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
        <section class="panel"><div class="panel-heading"><div><h3>Biaya terbaru</h3><p>Pengeluaran usaha yang dicatat terakhir.</p></div><a class="text-link" href="?page=expenses">Lihat biaya →</a></div>
            <?php if (!$recent_expenses): ?><div class="empty-compact">Belum ada biaya operasional.</div><?php else: ?><ul class="simple-list"><?php foreach ($recent_expenses as $expense): ?><li><span><strong><?= e($expense['category']) ?></strong><small><?= e($expense['description']) ?> · <?= e(format_date($expense['expense_date'])) ?></small></span><span><?= rupiah($expense['amount']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    </div>
</div>
