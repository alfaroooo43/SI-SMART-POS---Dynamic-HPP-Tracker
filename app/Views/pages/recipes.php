<div class="section-intro"><div><h2>Resep dan kalkulasi HPP</h2><p>Biaya resep akan mengikuti biaya bahan baku terbaru secara otomatis.</p></div><span class="badge badge-green">HPP dinamis</span></div>
<section class="panel recipe-selector"><form method="get" class="compact-form"><input type="hidden" name="page" value="recipes"><label class="grow">Pilih produk kuliner<select name="product_id" required><option value="">Pilih produk</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= $selected_id === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option><?php endforeach; ?></select></label><button class="button button-outline" type="submit">Buka resep</button></form></section>
<?php if (!$products): ?><div class="notice notice-warning">Buat dahulu produk dengan jenis Kuliner pada halaman <a href="?page=products">Produk</a>.</div><?php endif; ?>
<?php if ($selected_id): ?>
<?php $chosenProduct = null; foreach ($products as $product) { if ((int) $product['id'] === $selected_id) { $chosenProduct = $product; break; } } ?>
<?php if ($chosenProduct): ?>
<?php
$existingQuantities = [];
foreach ($recipe_items as $item) { $existingQuantities[(int) $item['ingredient_id']] = $item['quantity']; }
$current = null;
foreach ($costs as $productCost) { if ((int) $productCost['id'] === $selected_id) { $current = $productCost; break; } }
?>
<div class="recipe-layout">
    <section class="panel"><div class="panel-heading"><div><h3>Komposisi <?= e($chosenProduct['name']) ?></h3><p>Masukkan kuantitas bahan untuk satu kali produksi.</p></div></div>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_recipe"><input type="hidden" name="return_page" value="recipes"><input type="hidden" name="product_id" value="<?= $selected_id ?>">
            <label>Hasil produksi (<?= e($chosenProduct['unit']) ?>)<input type="number" name="yield_quantity" min="0.001" step="0.001" value="<?= e($recipe['yield_quantity'] ?? '1') ?>" required></label>
            <div class="recipe-rows"><div class="recipe-row recipe-header"><span>Bahan baku</span><span>Jumlah per batch</span></div>
            <?php for ($i = 0; $i < 8; $i++): $selectedIngredient = array_keys($existingQuantities)[$i] ?? 0; ?>
            <div class="recipe-row"><select name="ingredient_id[]" aria-label="Bahan baku <?= $i + 1 ?>"><option value="0">Pilih bahan</option><?php foreach ($ingredients as $ingredient): ?><option value="<?= (int) $ingredient['id'] ?>" <?= (int) $selectedIngredient === (int) $ingredient['id'] ? 'selected' : '' ?>><?= e($ingredient['name']) ?> · <?= rupiah($ingredient['average_cost']) ?>/<?= e($ingredient['unit']) ?></option><?php endforeach; ?></select><input type="number" name="quantity[]" min="0" step="0.001" value="<?= e($existingQuantities[$selectedIngredient] ?? '') ?>" aria-label="Jumlah bahan <?= $i + 1 ?>" placeholder="0"></div>
            <?php endfor; ?></div>
            <button class="button button-primary" type="submit">Simpan resep</button>
        </form>
    </section>
    <aside class="panel hpp-card"><span class="eyebrow">BIAYA BERDASARKAN STOK SAAT INI</span><h3>HPP per unit</h3><strong><?= rupiah($current['current_cost'] ?? 0) ?></strong><p>Harga jual saat ini: <?= rupiah($chosenProduct['selling_price']) ?></p>
        <?php $costValue = (float) ($current['current_cost'] ?? 0); $sell = (float) $chosenProduct['selling_price']; $marginValue = $sell > 0 ? ($sell - $costValue) / $sell * 100 : -100; $recommended = (float) $chosenProduct['target_margin'] < 100 ? $costValue / (1 - (float) $chosenProduct['target_margin'] / 100) : 0; ?>
        <div class="hpp-detail"><span>Margin saat ini</span><strong class="<?= $marginValue < (float) $chosenProduct['min_margin'] ? 'margin-danger' : 'margin-good' ?>"><?= number_format($marginValue, 1, ',', '.') ?>%</strong></div>
        <div class="hpp-detail"><span>Saran harga (target <?= e($chosenProduct['target_margin']) ?>%)</span><strong><?= rupiah($recommended) ?></strong></div>
        <div class="callout callout-soft">Saran harga tidak mengubah harga produk. Perubahan harga harus disetujui pemilik melalui pengelolaan produk.</div>
    </aside>
</div>
<?php else: ?><div class="notice notice-error">Produk tersebut tidak ditemukan atau bukan produk kuliner aktif.</div><?php endif; ?>
<?php endif; ?>
<section class="panel table-panel"><div class="panel-heading"><div><h3>Ringkasan HPP produk</h3><p>Nilai dihitung dari resep dan biaya rata-rata bahan baku aktif.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Produk</th><th>HPP / unit</th><th>Harga jual</th><th>Margin</th><th>Saran harga</th><th>Resep</th></tr></thead><tbody>
<?php foreach ($costs as $product): if ($product['type'] !== 'culinary') { continue; } $cost = (float) $product['current_cost']; $price = (float) $product['selling_price']; $margin = $price > 0 ? ($price - $cost) / $price * 100 : -100; ?>
<tr><td class="strong"><?= e($product['name']) ?></td><td><?= rupiah($cost) ?></td><td><?= rupiah($price) ?></td><td class="<?= $margin < (float) $product['min_margin'] ? 'margin-danger' : 'margin-good' ?>"><?= number_format($margin, 1, ',', '.') ?>%</td><td><?= rupiah($cost / (1 - (float) $product['target_margin'] / 100)) ?></td><td><a class="text-link" href="?page=recipes&product_id=<?= (int) $product['id'] ?>">Atur resep →</a></td></tr>
<?php endforeach; ?></tbody></table></div>
</section>
