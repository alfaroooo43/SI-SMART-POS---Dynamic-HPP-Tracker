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
    <title><?= e($title ?? 'SI SMART UMKM') ?> · SMART UMKM</title>
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
            <a class="brand" href="?page=dashboard"><span class="brand-mark">S</span><span>SMART<span class="brand-light">UMKM</span><small><?= e(($business_profile['business_name'] ?? '') ?: 'PENGELOLAAN USAHA') ?></small></span></a>
            <div class="sidebar-label">MENU UTAMA</div>
            <nav class="nav-menu" aria-label="Navigasi utama">
                <?php
                $role = $user['role'] ?? '';
                $singleLinks = [
                    ['dashboard', '◫', 'Dashboard', ['owner', 'admin']],
                    ['reports', '▥', 'Laporan', ['owner', 'admin']],
                ];
                $menuGroups = [
                    ['Master Data', [
                        ['products', '▦', 'Produk', ['owner', 'admin']],
                        ['ingredients', '◉', 'Bahan baku', ['owner', 'admin']],
                        ['suppliers', '⇄', 'Supplier', ['owner', 'admin']],
                    ]],
                    ['Persediaan', [
                        ['inventory', '⇵', 'Stok & riwayat', ['owner', 'admin']],
                    ]],
                    ['Keuangan', [
                        ['recipes', '♧', 'HPP & resep', ['owner', 'admin']],
                        ['expenses', '−', 'Biaya operasional', ['owner', 'admin']],
                    ]],
                    ['Pengaturan', [
                        ['settings', '⚙', 'Profil usaha', ['owner']],
                        ['users', '♙', 'Pengguna & akses', ['owner']],
                    ]],
                ];
                ?>
                    <?php foreach ($singleLinks as [$slug, $icon, $label, $roles]): if (in_array($role, $roles, true)): ?>
                        <a class="nav-link <?= ($page ?? '') === $slug ? 'active' : '' ?>" href="?page=<?= e($slug) ?>">
                            <span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?>
                        </a>
                    <?php endif; endforeach; ?>
                    <?php foreach ($menuGroups as [$groupLabel, $items]):
                        $visibleItems = array_values(array_filter($items, static fn (array $item): bool => in_array($role, $item[3], true)));
                        if (!$visibleItems) {
                            continue;
                        }
                        $groupActive = in_array($page ?? '', array_column($visibleItems, 0), true);
                    ?>
                        <details class="nav-group" <?= $groupActive ? 'open' : '' ?>>
                            <summary class="nav-group-summary"><?= e($groupLabel) ?><span class="nav-chevron" aria-hidden="true"></span></summary>
                            <div class="nav-submenu">
                                <?php foreach ($visibleItems as [$slug, $icon, $label]): ?>
                                    <a class="nav-link <?= ($page ?? '') === $slug ? 'active' : '' ?>" href="?page=<?= e($slug) ?>">
                                        <span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="support-card"><span class="support-icon">✦</span><strong>Bisnis lebih terukur</strong><p>Pantau biaya dan keuntungan usaha dalam satu tempat.</p></div>
                <div class="profile-mini"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span><span><strong><?= e($user['name'] ?? '') ?></strong><small><?= e(ucfirst($user['role'] ?? '')) ?></small></span></div>
            </div>
        </aside>
        <main class="main-area">
            <header class="topbar">
                <div><span class="eyebrow">PENGELOLAAN PRODUK, STOK, HPP & KEUANGAN UMKM</span><h1><?= e($title ?? '') ?></h1></div>
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
                <footer class="footer">© <?= date('Y') ?> SMART UMKM <span>·</span> Pencatatan usaha yang lebih cerdas</footer>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
