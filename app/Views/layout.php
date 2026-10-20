<?php
declare(strict_types=1);

$assetPrefix = 'public/assets';
$authPages = ['login', 'setup'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#166534">
    <title><?= e($title ?? 'SI SMART POS') ?> · SMART POS</title>
    <link rel="stylesheet" href="<?= $assetPrefix ?>/app.css">
</head>
<body class="<?= in_array($view, $authPages, true) ? 'auth-body' : '' ?>">
<?php if (in_array($view, $authPages, true)): ?>
    <main class="auth-shell">
        <?php if ($flashMessage): ?>
            <div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
        <?php endif; ?>
        <?php require APP_ROOT . '/app/Views/' . $view . '.php'; ?>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="?page=dashboard"><span class="brand-mark">S</span><span>SMART<span class="brand-light">POS</span><small>UMKM MANAGEMENT</small></span></a>
            <div class="sidebar-label">MENU UTAMA</div>
            <nav class="nav-menu" aria-label="Navigasi utama">
                <?php
                $menu = [
                    ['dashboard', '◫', 'Dashboard', ['owner', 'admin', 'cashier']],
                    ['pos', '＋', 'Kasir / POS', ['owner', 'admin', 'cashier']],
                    ['sales', '▤', 'Transaksi', ['owner', 'admin', 'cashier']],
                    ['products', '▦', 'Produk', ['owner', 'admin']],
                    ['ingredients', '◉', 'Bahan baku', ['owner', 'admin']],
                    ['inventory', '⇵', 'Persediaan', ['owner', 'admin']],
                    ['recipes', '♧', 'Resep & HPP', ['owner', 'admin']],
                    ['suppliers', '⇄', 'Supplier', ['owner', 'admin']],
                    ['purchases', '▣', 'Pembelian & stok', ['owner', 'admin']],
                    ['expenses', '−', 'Biaya operasional', ['owner', 'admin']],
                    ['reports', '▥', 'Laporan', ['owner', 'admin']],
                    ['users', '♙', 'Pengguna', ['owner']],
                ];
                foreach ($menu as [$slug, $icon, $label, $roles]):
                    if (!in_array($user['role'] ?? '', $roles, true)) {
                        continue;
                    }
                ?>
                    <a class="nav-link <?= ($page ?? '') === $slug ? 'active' : '' ?>" href="?page=<?= e($slug) ?>">
                        <span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="support-card"><span class="support-icon">✦</span><strong>Bisnis lebih terukur</strong><p>Pantau biaya dan keuntungan usaha dalam satu tempat.</p></div>
                <div class="profile-mini"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span><span><strong><?= e($user['name'] ?? '') ?></strong><small><?= e(ucfirst($user['role'] ?? '')) ?></small></span></div>
            </div>
        </aside>
        <main class="main-area">
            <header class="topbar">
                <div><span class="eyebrow">SI SMART POS & DYNAMIC HPP TRACKER</span><h1><?= e($title ?? '') ?></h1></div>
                <div class="topbar-actions"><span class="today"><?= e(format_date(date('Y-m-d'), 'l, d M Y')) ?></span>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="button button-outline button-small" type="submit">Keluar</button>
                    </form>
                </div>
            </header>
            <div class="content">
                <?php if ($flashMessage): ?>
                    <div class="notice notice-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
                <?php endif; ?>
                <?php require APP_ROOT . '/app/Views/pages/' . $view . '.php'; ?>
                <footer class="footer">© <?= date('Y') ?> SMART POS <span>·</span> Pencatatan usaha yang lebih cerdas</footer>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
