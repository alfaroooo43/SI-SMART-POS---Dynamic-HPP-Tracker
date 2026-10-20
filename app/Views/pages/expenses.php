<div class="section-intro"><div><h2>Biaya operasional usaha</h2><p>Catat biaya di luar HPP, seperti listrik, sewa, transportasi, dan perlengkapan.</p></div><span class="badge badge-green"><?= count(array_filter($expenses, static fn (array $expense): bool => $expense['status'] === 'recorded')) ?> biaya aktif</span></div>
<form class="filter-bar" method="get"><input type="hidden" name="page" value="expenses"><label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>" required></label><label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>" required></label><button class="button button-outline" type="submit">Tampilkan</button></form>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3>Catat biaya baru</h3><p>Biaya tercatat mengurangi laba bersih pada laporan.</p></div><span class="stat-icon yellow">−</span></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="create_expense"><input type="hidden" name="return_page" value="expenses">
            <label>Tanggal<input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label>Kategori<input name="category" maxlength="100" placeholder="Contoh: Listrik" required></label>
            <label class="span-2">Keterangan<input name="description" maxlength="255" placeholder="Contoh: Tagihan listrik bulan ini" required></label>
            <label>Jumlah (Rp)<input type="number" name="amount" min="0.01" step="0.01" required></label>
            <label>Metode pembayaran<select name="payment_method" required><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="debit">Kartu debit</option><option value="other">Lainnya</option></select></label>
            <div class="span-2"><button class="button button-primary" type="submit">Simpan biaya</button></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Perhitungan laba</h3><p>Biaya operasional dicatat terpisah dari HPP persediaan.</p></div></div>
        <div class="callout"><strong>Laba bersih periode</strong><p>Laba kotor penjualan − biaya operasional berstatus aktif pada periode yang dipilih. Biaya yang dibatalkan tidak memengaruhi laporan, tetapi tetap terlihat di histori.</p></div>
        <div class="callout callout-soft"><strong>Menjaga histori</strong><p>Biaya yang salah tidak dihapus. Batalkan dengan catatan alasan, lalu buat catatan pengganti yang benar.</p></div>
    </section>
</div>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Riwayat biaya operasional</h3><p><?= e(format_date($from)) ?> – <?= e(format_date($to)) ?></p></div></div>
    <?php if (!$expenses): ?><div class="empty-state">Belum ada biaya pada periode ini.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Metode</th><th>Dicatat oleh</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($expenses as $expense): ?><tr><td><?= e(format_date($expense['expense_date'])) ?></td><td><?= e($expense['category']) ?></td><td class="strong"><?= e($expense['description']) ?><?php if ($expense['status'] === 'voided'): ?><small class="cell-sub">Alasan batal: <?= e($expense['void_note']) ?></small><?php endif; ?></td><td><span class="badge badge-gray"><?= e(strtoupper($expense['payment_method'])) ?></span></td><td><?= e($expense['created_by']) ?></td><td><?= rupiah($expense['amount']) ?></td><td><span class="badge <?= $expense['status'] === 'recorded' ? 'badge-green' : 'badge-gray' ?>"><?= $expense['status'] === 'recorded' ? 'Aktif' : 'Dibatalkan' ?></span></td>
        <td><?php if ($expense['status'] === 'recorded'): ?><form method="post" class="expense-void-form"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="void_expense"><input type="hidden" name="return_page" value="expenses"><input type="hidden" name="id" value="<?= (int) $expense['id'] ?>"><label class="visually-hidden" for="void-note-<?= (int) $expense['id'] ?>">Alasan pembatalan</label><input id="void-note-<?= (int) $expense['id'] ?>" name="void_note" maxlength="255" placeholder="Alasan pembatalan" required><button class="text-button text-danger" type="submit">Batalkan</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
