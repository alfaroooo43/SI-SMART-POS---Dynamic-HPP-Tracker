<div class="section-intro"><div><h2>Profil usaha</h2><p>Simpan identitas usaha yang ditampilkan pada navigasi aplikasi. Perubahan tidak mengubah transaksi lama.</p></div><span class="badge badge-yellow">Khusus pemilik</span></div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3>Informasi usaha</h3><p>Isi data yang ingin ditampilkan dan simpan perubahan.</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="return_page" value="settings">
            <label class="span-2">Nama usaha<input name="business_name" maxlength="120" value="<?= e($settings['business_name'] ?? '') ?>" placeholder="Contoh: Warung Maju"></label>
            <label>Nomor telepon<input name="business_phone" maxlength="120" value="<?= e($settings['business_phone'] ?? '') ?>" placeholder="Opsional"></label>
            <label>Alamat usaha<input name="business_address" maxlength="255" value="<?= e($settings['business_address'] ?? '') ?>" placeholder="Opsional"></label>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit">Simpan profil usaha</button></div>
        </form>
    </section>
    <aside class="panel"><div class="panel-heading"><div><h3>Petunjuk</h3><p>Pengaturan ini hanya untuk identitas usaha.</p></div><span class="stat-icon green">i</span></div>
        <div class="callout"><strong>Riwayat transaksi tetap aman</strong><p>Nama produk, satuan, harga, dan HPP transaksi disimpan sebagai snapshot saat transaksi dibuat.</p></div>
        <div class="callout callout-soft"><strong>Akses</strong><p>Hanya pemilik yang dapat membuka dan mengubah profil usaha. Pengguna lain tetap mengikuti hak akses masing-masing.</p></div>
    </aside>
</div>
