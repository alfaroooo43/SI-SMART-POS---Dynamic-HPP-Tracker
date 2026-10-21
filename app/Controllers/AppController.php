<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\BusinessModel;
use DateTimeImmutable;
use PDOException;
use RuntimeException;
use Throwable;

final class AppController
{
    private BusinessModel $model;

    public function __construct()
    {
        $this->model = new BusinessModel();
    }

    public function dispatch(): void
    {
        try {
            Auth::refresh();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Csrf::verify();
                $this->handlePost((string) ($_POST['action'] ?? ''));
                return;
            }
            $this->handleGet((string) ($_GET['page'] ?? 'dashboard'));
        } catch (Throwable $exception) {
            $returnUrl = null;
            if (($_POST['action'] ?? '') === 'save_recipe' && Auth::check()) {
                $ingredientIds = is_array($_POST['ingredient_id'] ?? null) ? array_slice($_POST['ingredient_id'], 0, 12) : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? array_slice($_POST['quantity'], 0, 12) : [];
                $_SESSION['recipe_form'] = [
                    'product_id' => (int) ($_POST['product_id'] ?? 0),
                    'yield_quantity' => is_scalar($_POST['yield_quantity'] ?? null) ? (string) $_POST['yield_quantity'] : '',
                    'ingredient_id' => array_map(static fn ($id): string => is_scalar($id) ? (string) $id : '0', $ingredientIds),
                    'quantity' => array_map(static fn ($quantity): string => is_scalar($quantity) ? (string) $quantity : '', $quantities),
                ];
                $returnUrl = '?page=recipes&product_id=' . max(0, (int) ($_POST['product_id'] ?? 0));
            }
            if ($exception instanceof PDOException) {
                error_log('SMART UMKM database operation failed: ' . $exception->getMessage());
                flash('error', 'Data tidak dapat disimpan. Periksa kembali input dan koneksi database.');
            } elseif ($exception instanceof RuntimeException) {
                flash('error', $exception->getMessage());
            } else {
                error_log('SMART UMKM error: ' . $exception);
                flash('error', 'Terjadi kesalahan saat memproses permintaan. Silakan coba kembali.');
            }
            $page = ($_POST['action'] ?? '') === 'setup'
                ? 'setup'
                : (Auth::check() ? (string) ($_POST['return_page'] ?? 'dashboard') : 'login');
            redirect($returnUrl ?? '?page=' . rawurlencode($page));
        }
    }

    private function handleGet(string $page): void
    {
        if ($page === 'setup') {
            $this->setup();
            return;
        }
        if ($page === 'login') {
            if (Auth::check()) {
                redirect('?page=dashboard');
            }
            $this->render('login', [
                'title' => 'Masuk',
                'registrationOpen' => (int) $this->model->scalar('SELECT COUNT(*) FROM users') === 0,
            ]);
            return;
        }
        if (!Auth::check()) {
            redirect('?page=login');
        }

        $allowed = [
            'dashboard' => ['owner', 'admin'],
            'products' => ['owner', 'admin'],
            'ingredients' => ['owner', 'admin'],
            'inventory' => ['owner', 'admin'],
            'recipes' => ['owner', 'admin'],
            'suppliers' => ['owner', 'admin'],
            'expenses' => ['owner', 'admin'],
            'reports' => ['owner', 'admin'],
            'users' => ['owner'],
            'settings' => ['owner'],
        ];
        if (!isset($allowed[$page])) {
            $page = 'dashboard';
        }
        Auth::requireRole($allowed[$page]);

        $today = date('Y-m-d');
        $from = $this->validDate((string) ($_GET['from'] ?? $today), $today);
        $to = $this->validDate((string) ($_GET['to'] ?? $today), $today);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $data = ['title' => $this->title($page), 'page' => $page, 'from' => $from, 'to' => $to];
        $data['business_profile'] = $this->model->settings();

        switch ($page) {
            case 'dashboard':
                $data += $this->model->dashboard($from, $to);
                break;
            case 'products':
                $data['categories'] = $this->model->all('SELECT * FROM categories ORDER BY name');
                $data['product_search'] = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
                $categoryFilter = filter_var($_GET['category_id'] ?? '', FILTER_VALIDATE_INT);
                $data['product_category_filter'] = $categoryFilter && $categoryFilter > 0 ? $categoryFilter : null;
                $data['product_status_filter'] = in_array($_GET['status'] ?? '', ['active', 'archived'], true) ? (string) $_GET['status'] : '';
                $data['product_type_filter'] = in_array($_GET['type'] ?? '', ['retail', 'culinary'], true) ? (string) $_GET['type'] : '';
                $data['products'] = $this->model->products(
                    false,
                    $data['product_search'],
                    $data['product_category_filter'],
                    $data['product_status_filter'] ?: null,
                    $data['product_type_filter'] ?: null
                );
                $data['editing'] = isset($_GET['edit']) ? $this->model->one('SELECT * FROM products WHERE id = ?', [(int) $_GET['edit']]) : null;
                $data['category_edit'] = isset($_GET['edit_category']) ? $this->model->one('SELECT * FROM categories WHERE id = ?', [(int) $_GET['edit_category']]) : null;
                break;
            case 'ingredients':
                $data['ingredients'] = $this->model->all('SELECT * FROM ingredients ORDER BY name');
                $data['editing'] = isset($_GET['edit']) ? $this->model->one('SELECT * FROM ingredients WHERE id = ?', [(int) $_GET['edit']]) : null;
                break;
            case 'inventory':
                $data['products'] = $this->model->all("SELECT id, name, unit, stock, minimum_stock, average_cost FROM products WHERE active = 1 AND type = 'retail' ORDER BY name");
                $data['ingredients'] = $this->model->all('SELECT id, name, unit, stock, minimum_stock, average_cost FROM ingredients WHERE active = 1 ORDER BY name');
                $data['stock_items'] = $this->model->all(
                    "SELECT id, 'product' AS item_type, name, unit, stock, average_cost FROM products
                     WHERE active = 1 AND type = 'retail'
                     UNION ALL
                     SELECT id, 'ingredient' AS item_type, name, unit, stock, average_cost FROM ingredients
                     WHERE active = 1 ORDER BY name"
                );
                $data['movements'] = $this->model->all(
                    "SELECT m.id, m.item_type, m.movement_type, m.reference_type, m.quantity_change, m.unit_cost, m.note, m.created_at,
                        COALESCE(m.item_name_snapshot, p.name, i.name) AS item_name, u.name AS actor,
                        m.unit_snapshot
                     FROM inventory_movements m
                     LEFT JOIN products p ON p.id = COALESCE(m.product_id, IF(m.item_type = 'product', m.item_id, NULL))
                     LEFT JOIN ingredients i ON i.id = COALESCE(m.ingredient_id, IF(m.item_type = 'ingredient', m.item_id, NULL))
                     LEFT JOIN users u ON u.id = COALESCE(m.user_id, IF(m.reference_type = 'manual', m.reference_id, NULL))
                     WHERE m.item_type = 'product'
                     UNION ALL
                     SELECT m.id, m.item_type, m.movement_type, m.reference_type, m.quantity_change, m.unit_cost, m.note, m.created_at,
                        COALESCE(m.item_name_snapshot, p.name, i.name) AS item_name, u.name AS actor,
                        m.unit_snapshot
                     FROM inventory_movements m
                     LEFT JOIN products p ON p.id = COALESCE(m.product_id, IF(m.item_type = 'product', m.item_id, NULL))
                     LEFT JOIN ingredients i ON i.id = COALESCE(m.ingredient_id, IF(m.item_type = 'ingredient', m.item_id, NULL))
                     LEFT JOIN users u ON u.id = COALESCE(m.user_id, IF(m.reference_type = 'manual', m.reference_id, NULL))
                     WHERE m.item_type = 'ingredient'
                     ORDER BY created_at DESC LIMIT 50"
                );
                $data['movement_audits'] = $this->model->all(
                    'SELECT a.action, a.item_name_snapshot, a.unit_snapshot, a.quantity_change, a.note,
                            a.created_at, a.changed_at, u.name AS changed_by
                     FROM inventory_movement_audit a
                     LEFT JOIN users u ON u.id = a.changed_by
                     ORDER BY a.changed_at DESC, a.id DESC LIMIT 25'
                );
                $data['cost_history'] = $this->model->all(
                    "SELECT h.item_type, h.old_cost, h.new_cost, h.source_type, h.changed_at,
                        COALESCE(h.item_name_snapshot, p.name, i.name) AS item_name
                     FROM cost_history h
                     LEFT JOIN products p ON p.id = COALESCE(h.product_id, IF(h.item_type = 'product', h.item_id, NULL))
                     LEFT JOIN ingredients i ON i.id = COALESCE(h.ingredient_id, IF(h.item_type = 'ingredient', h.item_id, NULL))
                     ORDER BY h.changed_at DESC LIMIT 30"
                );
                break;
            case 'recipes':
                $data['products'] = $this->model->all("SELECT * FROM products WHERE active = 1 AND type = 'culinary' ORDER BY name");
                $data['ingredients'] = $this->model->all('SELECT * FROM ingredients WHERE active = 1 ORDER BY name');
                $data['selected_id'] = (int) ($_GET['product_id'] ?? 0);
                $data['recipe_form'] = null;
                if (isset($_SESSION['recipe_form'])
                    && (int) $_SESSION['recipe_form']['product_id'] === $data['selected_id']) {
                    $data['recipe_form'] = $_SESSION['recipe_form'];
                    unset($_SESSION['recipe_form']);
                }
                $data['recipe'] = $data['selected_id'] ? $this->model->one(
                    'SELECT r.*, ri.ingredient_id, ri.quantity FROM recipes r
                     LEFT JOIN recipe_items ri ON ri.recipe_id = r.id WHERE r.product_id = ?',
                    [$data['selected_id']]
                ) : null;
                if ($data['selected_id']) {
                    $data['recipe_items'] = $this->model->all(
                        'SELECT ingredient_id, quantity FROM recipe_items WHERE recipe_id = (SELECT id FROM recipes WHERE product_id = ?)',
                        [$data['selected_id']]
                    );
                } else {
                    $data['recipe_items'] = [];
                }
                $data['costs'] = $this->model->products(true);
                break;
            case 'suppliers':
                $data['suppliers'] = $this->model->all('SELECT * FROM suppliers ORDER BY name');
                $data['editing'] = isset($_GET['edit']) ? $this->model->one('SELECT * FROM suppliers WHERE id = ?', [(int) $_GET['edit']]) : null;
                break;
            case 'reports':
                $data += $this->model->report($from, $to);
                break;
            case 'expenses':
                $data['expense_search'] = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
                $data['expenses'] = $this->model->expenses($from, $to, $data['expense_search']);
                break;
            case 'users':
                $data['users'] = $this->model->all('SELECT id, name, email, role, active, created_at FROM users ORDER BY name');
                $data['editing'] = isset($_GET['edit']) ? $this->model->one('SELECT id, name, email, role, active FROM users WHERE id = ?', [(int) $_GET['edit']]) : null;
                break;
            case 'settings':
                $data['settings'] = $this->model->settings();
                break;
        }
        $this->render($page, $data);
    }

    private function handlePost(string $action): void
    {
        if ($action === 'setup') {
            $this->createInitialOwner();
            return;
        }
        if ($action === 'login') {
            if (Auth::attempt((string) input('email'), (string) input('password'))) {
                redirect('?page=dashboard');
            }
            flash('error', 'Email atau kata sandi salah, atau akun sedang dinonaktifkan.');
            redirect('?page=login');
        }
        if ($action === 'logout') {
            Auth::logout();
            flash('success', 'Anda telah keluar dari sistem.');
            redirect('?page=login');
        }

        if (!Auth::check()) {
            redirect('?page=login');
        }
        $page = (string) input('return_page', 'dashboard');
        $ownerOnly = ['save_user', 'toggle_user', 'save_settings'];
        $staffActions = [
            'save_category', 'delete_category', 'save_supplier', 'toggle_supplier', 'delete_supplier', 'save_ingredient', 'toggle_ingredient', 'delete_ingredient',
            'save_product', 'toggle_product', 'delete_product', 'save_recipe', 'delete_recipe_ingredient', 'adjust_inventory',
            'set_inventory_stock', 'update_inventory_movement', 'delete_inventory_movement',
            'create_expense', 'void_expense',
        ];
        if (in_array($action, $ownerOnly, true)) {
            Auth::requireRole(['owner']);
        } elseif (in_array($action, $staffActions, true)) {
            Auth::requireRole(['owner', 'admin']);
        } else {
            throw new RuntimeException('Tindakan tidak dikenal.');
        }

        $query = '';
        switch ($action) {
            case 'save_category':
                $name = $this->requiredText('name', 100);
                $this->model->saveCategory($this->optionalId('id'), $name);
                $page = 'products';
                break;
            case 'delete_category':
                $this->model->deleteCategory((int) input('id'));
                $page = 'products';
                break;
            case 'save_supplier':
                $this->model->saveSupplier($this->optionalId('id'), [
                    'name' => $this->requiredText('name', 150),
                    'phone' => $this->optionalText('phone', 40),
                    'address' => $this->optionalText('address', 255),
                ]);
                $page = 'suppliers';
                break;
            case 'toggle_supplier':
                $this->model->setSupplierActive((int) input('id'), $this->toggleValue('active'));
                $page = 'suppliers';
                break;
            case 'delete_supplier':
                $this->model->deleteSupplier((int) input('id'));
                $page = 'suppliers';
                break;
            case 'save_ingredient':
                $averageCost = input('average_cost');
                if (!is_string($averageCost) || trim($averageCost) === '') {
                    throw new RuntimeException('Harga beli per satuan dasar wajib diisi. Masukkan 0 hanya jika biaya memang belum tersedia.');
                }
                $this->model->saveIngredient($this->optionalId('id'), [
                    'name' => $this->requiredText('name', 150),
                    'unit' => $this->requiredText('unit', 30),
                    'average_cost' => $this->number('average_cost'),
                    'minimum_stock' => $this->quantity('minimum_stock'),
                ]);
                $page = 'ingredients';
                break;
            case 'toggle_ingredient':
                $this->model->setIngredientActive((int) input('id'), $this->toggleValue('active'));
                $page = 'ingredients';
                break;
            case 'delete_ingredient':
                $this->model->deleteIngredient((int) input('id'));
                $page = 'ingredients';
                break;
            case 'save_product':
                $type = (string) input('type');
                if (!in_array($type, ['culinary', 'retail'], true)) {
                    throw new RuntimeException('Jenis produk tidak valid.');
                }
                $this->model->saveProduct($this->optionalId('id'), [
                    'category_id' => $this->optionalId('category_id'),
                    'name' => $this->requiredText('name', 150),
                    'type' => $type,
                    'unit' => $this->requiredText('unit', 30),
                    'selling_price' => $this->number('selling_price'),
                    'minimum_stock' => $this->quantity('minimum_stock'),
                    'min_margin' => $this->boundedNumber('min_margin', 0, 99),
                    'target_margin' => $this->boundedNumber('target_margin', 0, 99),
                ]);
                $page = 'products';
                break;
            case 'toggle_product':
                $this->model->setProductActive((int) input('id'), $this->toggleValue('active'));
                $page = 'products';
                break;
            case 'delete_product':
                $this->model->deleteProduct((int) input('id'));
                $page = 'products';
                break;
            case 'save_recipe':
                $productId = filter_var(input('product_id'), FILTER_VALIDATE_INT);
                if ($productId === false || $productId <= 0) {
                    throw new RuntimeException('Pilih produk kuliner sebelum menyimpan resep.');
                }
                $ingredients = is_array($_POST['ingredient_id'] ?? null) ? $_POST['ingredient_id'] : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
                $items = [];
                foreach ($ingredients as $index => $id) {
                    $ingredientId = filter_var($id, FILTER_VALIDATE_INT);
                    $rawQuantity = $quantities[$index] ?? '';
                    $hasQuantity = is_scalar($rawQuantity) && trim((string) $rawQuantity) !== '';
                    if ($ingredientId === false || $ingredientId < 0) {
                        throw new RuntimeException('Pilihan bahan resep pada baris ' . ((int) $index + 1) . ' tidak valid.');
                    }
                    if ($ingredientId === 0) {
                        if ($hasQuantity) {
                            throw new RuntimeException('Pilih bahan baku pada baris ' . ((int) $index + 1) . ' atau kosongkan jumlahnya.');
                        }
                        continue;
                    }
                    $quantity = $this->recipeQuantityValue($rawQuantity, 'Jumlah bahan pada baris ' . ((int) $index + 1));
                    if (isset($items[$ingredientId])) {
                        throw new RuntimeException('Bahan yang sama dipilih lebih dari sekali. Gabungkan jumlahnya dalam satu baris.');
                    }
                    $items[$ingredientId] = $quantity;
                }
                $yield = $this->recipeQuantityValue(input('yield_quantity'), 'Hasil produksi');
                $this->model->saveRecipe($productId, $yield, $items);
                unset($_SESSION['recipe_form']);
                $page = 'recipes';
                $query = '&product_id=' . $productId;
                break;
            case 'delete_recipe_ingredient':
                $productId = filter_var(input('product_id'), FILTER_VALIDATE_INT);
                $ingredientId = filter_var(input('ingredient_id'), FILTER_VALIDATE_INT);
                if ($productId === false || $productId <= 0 || $ingredientId === false || $ingredientId <= 0) {
                    throw new RuntimeException('Produk atau bahan resep yang dipilih tidak valid.');
                }
                $this->model->deleteRecipeIngredient($productId, $ingredientId);
                $page = 'recipes';
                $query = '&product_id=' . $productId;
                break;
            case 'adjust_inventory':
                $itemRef = explode(':', (string) input('item_ref'), 2);
                $type = $itemRef[0] ?? '';
                $itemId = filter_var($itemRef[1] ?? '', FILTER_VALIDATE_INT);
                $delta = $this->quantity('quantity_change', true);
                if ($itemId === false || $itemId <= 0 || $delta == 0.0) {
                    throw new RuntimeException('Masukkan penyesuaian stok positif untuk menambah atau negatif untuk mengurangi.');
                }
                $this->model->adjustInventory(
                    $type,
                    $itemId,
                    $delta,
                    $this->requiredText('note', 255),
                    (int) Auth::user()['id']
                );
                $page = 'inventory';
                break;
            case 'set_inventory_stock':
                $itemRef = explode(':', (string) input('item_ref'), 2);
                $type = $itemRef[0] ?? '';
                $itemId = filter_var($itemRef[1] ?? '', FILTER_VALIDATE_INT);
                if ($itemId === false || $itemId <= 0) {
                    throw new RuntimeException('Pilih barang atau bahan baku yang valid.');
                }
                $this->model->setInventoryStock(
                    $type,
                    $itemId,
                    $this->quantity('stock'),
                    $this->requiredText('note', 255),
                    (int) Auth::user()['id']
                );
                $page = 'inventory';
                break;
            case 'update_inventory_movement':
                $movementId = filter_var(input('id'), FILTER_VALIDATE_INT);
                if ($movementId === false || $movementId <= 0) {
                    throw new RuntimeException('Mutasi stok yang dipilih tidak valid.');
                }
                $this->model->updateInventoryMovement(
                    $movementId,
                    $this->quantity('quantity_change', true),
                    $this->requiredText('note', 255),
                    $this->dateTimeLocal((string) input('created_at')),
                    (int) Auth::user()['id']
                );
                $page = 'inventory';
                break;
            case 'delete_inventory_movement':
                $movementId = filter_var(input('id'), FILTER_VALIDATE_INT);
                if ($movementId === false || $movementId <= 0) {
                    throw new RuntimeException('Mutasi stok yang dipilih tidak valid.');
                }
                $this->model->deleteInventoryMovement($movementId, (int) Auth::user()['id']);
                $page = 'inventory';
                break;
            case 'create_expense':
                $paymentMethod = (string) input('payment_method');
                if (!in_array($paymentMethod, ['cash', 'transfer', 'qris', 'debit', 'other'], true)) {
                    throw new RuntimeException('Metode pembayaran biaya operasional tidak valid.');
                }
                $expenseCategory = (string) input('category');
                if (!in_array($expenseCategory, ['Listrik & air', 'Sewa', 'Transportasi', 'Gaji', 'Perlengkapan', 'Internet & telepon', 'Perawatan', 'Biaya lainnya'], true)) {
                    throw new RuntimeException('Pilih jenis biaya operasional yang tersedia.');
                }
                $this->model->createExpense([
                    'expense_date' => $this->requiredDate((string) input('expense_date')),
                    'category' => $expenseCategory,
                    'description' => $this->requiredText('description', 255),
                    'amount' => $this->number('amount'),
                    'payment_method' => $paymentMethod,
                ], (int) Auth::user()['id']);
                $page = 'expenses';
                break;
            case 'void_expense':
                $this->model->voidExpense(
                    (int) input('id'),
                    $this->requiredText('void_note', 255),
                    (int) Auth::user()['id']
                );
                $page = 'expenses';
                break;
            case 'save_user':
                $role = (string) input('role');
                if ($role !== 'admin') {
                    throw new RuntimeException('Peran pengguna tidak valid.');
                }
                $this->model->saveUser($this->optionalId('id'), [
                    'name' => $this->requiredText('name', 120),
                    'email' => filter_var(input('email'), FILTER_VALIDATE_EMAIL) ?: throw new RuntimeException('Alamat email tidak valid.'),
                    'role' => $role,
                    'password' => (string) input('password'),
                ]);
                $page = 'users';
                break;
            case 'toggle_user':
                $this->model->setUserActive((int) input('id'), $this->toggleValue('active'), (int) Auth::user()['id']);
                $page = 'users';
                break;
            case 'save_settings':
                $this->model->saveSettings([
                    'business_name' => $this->optionalText('business_name', 120),
                    'business_phone' => $this->optionalText('business_phone', 120),
                    'business_address' => $this->optionalText('business_address', 255),
                ]);
                $page = 'settings';
                break;
        }
        flash('success', 'Perubahan berhasil disimpan.');
        redirect('?page=' . rawurlencode($page) . $query);
    }

    private function setup(): void
    {
        if ((int) $this->model->scalar('SELECT COUNT(*) FROM users') > 0) {
            $this->render('setup', ['title' => 'Pendaftaran awal selesai', 'setupClosed' => true]);
            return;
        }
        $this->render('setup', ['title' => 'Buat akun pemilik', 'setupClosed' => false]);
    }

    private function createInitialOwner(): void
    {
        $lock = (int) $this->model->scalar("SELECT GET_LOCK('si_smart_pos_initial_owner', 10)");
        if ($lock !== 1) {
            throw new RuntimeException('Setup sedang digunakan. Silakan coba kembali sebentar lagi.');
        }
        $alreadyExists = false;
        try {
            if ((int) $this->model->scalar('SELECT COUNT(*) FROM users') > 0) {
                $alreadyExists = true;
            } else {
                $name = $this->requiredText('name', 120);
                $email = filter_var(input('email'), FILTER_VALIDATE_EMAIL);
                $password = (string) input('password');
                if (!$email || strlen($password) < 10) {
                    throw new RuntimeException('Masukkan email yang valid dan kata sandi minimal 10 karakter.');
                }
                $statement = Database::connection()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "owner")');
                try {
                    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        throw new RuntimeException('Email tersebut sudah terdaftar. Gunakan email lain atau masuk ke akun yang sudah ada.', 0, $exception);
                    }
                    throw $exception;
                }
            }
        } finally {
            $this->model->scalar("SELECT RELEASE_LOCK('si_smart_pos_initial_owner')");
        }
        if ($alreadyExists) {
            flash('error', 'Pendaftaran akun pemilik sudah ditutup karena akun usaha telah dibuat. Masuk, lalu kelola akun dari menu Pengguna.');
            redirect('?page=login');
        }
        flash('success', 'Akun pemilik berhasil dibuat. Silakan masuk.');
        redirect('?page=login');
    }

    private function render(string $view, array $data): void
    {
        $user = Auth::user();
        $flashMessage = consume_flash();
        extract($data, EXTR_SKIP);
        require APP_ROOT . '/app/Views/layout.php';
    }

    private function title(string $page): string
    {
        return [
            'dashboard' => 'Ringkasan usaha',
            'products' => 'Produk & kategori',
            'ingredients' => 'Bahan baku',
            'inventory' => 'Persediaan & riwayat',
            'recipes' => 'Resep & HPP',
            'suppliers' => 'Supplier',
            'expenses' => 'Biaya operasional',
            'reports' => 'Laporan usaha',
            'users' => 'Pengguna & akses',
            'settings' => 'Profil usaha',
        ][$page] ?? 'SI SMART UMKM';
    }

    private function toggleValue(string $key): bool
    {
        $value = input($key);
        if (!in_array($value, ['0', '1', 0, 1], true)) {
            throw new RuntimeException('Status yang dipilih tidak valid.');
        }
        return (string) $value === '1';
    }

    private function validDate(string $date, string $fallback): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : $fallback;
    }

    private function requiredDate(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Tanggal yang dimasukkan tidak valid.');
        }
        return $date;
    }

    private function dateTimeLocal(string $dateTime): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateTime);
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $dateTime) {
            throw new RuntimeException('Tanggal dan waktu mutasi tidak valid.');
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    private function requiredText(string $key, int $maxLength): string
    {
        $value = trim((string) input($key));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Kolom ' . $key . ' wajib diisi dan maksimal ' . $maxLength . ' karakter.');
        }
        return $value;
    }

    private function optionalText(string $key, int $maxLength): string
    {
        $value = trim((string) input($key));
        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Kolom ' . $key . ' terlalu panjang.');
        }
        return $value;
    }

    private function optionalId(string $key): ?int
    {
        $raw = input($key);
        if ($raw === '' || $raw === null || $raw === '0' || $raw === 0) {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            throw new RuntimeException('ID yang dipilih tidak valid.');
        }
        return $value;
    }

    private function number(string $key): float
    {
        $raw = input($key);
        if (is_string($raw) && trim($raw) === '') {
            throw new RuntimeException('Kolom ' . $key . ' wajib diisi.');
        }
        $value = $this->parseNumber($raw);
        if (!is_finite($value) || $value < 0) {
            throw new RuntimeException('Nilai ' . $key . ' harus berupa angka nol atau lebih.');
        }
        return $value;
    }

    private function quantity(string $key, bool $allowNegative = false): float
    {
        return $this->quantityValue(input($key), $allowNegative);
    }

    private function quantityValue(mixed $raw, bool $allowNegative = false): float
    {
        if (!is_string($raw) || trim($raw) === ''
            || !preg_match('/^[+-]?(?:\d+(?:\.\d{1,3})?|\.\d{1,3})$/D', trim($raw))) {
            throw new RuntimeException('Jumlah stok wajib berupa angka tanpa pemisah ribuan, maksimal tiga angka desimal (gunakan titik untuk desimal).');
        }
        $value = (float) trim($raw);
        if (!is_finite($value) || (!$allowNegative && $value < 0)) {
            throw new RuntimeException('Jumlah stok tidak valid atau tidak boleh negatif.');
        }
        return $value;
    }

    private function recipeQuantityValue(mixed $raw, string $label): float
    {
        if (!is_scalar($raw) || trim((string) $raw) === ''
            || !preg_match('/^(?:\d+(?:\.\d{1,3})?|\.\d{1,3})$/D', trim((string) $raw))) {
            throw new RuntimeException($label . ' wajib berupa angka lebih dari nol, tanpa pemisah ribuan, maksimal tiga desimal (gunakan titik untuk desimal).');
        }
        $value = (float) trim((string) $raw);
        if (!is_finite($value) || $value <= 0) {
            throw new RuntimeException($label . ' harus lebih besar dari nol.');
        }
        return $value;
    }

    private function boundedNumber(string $key, float $min, float $max): float
    {
        $value = $this->number($key);
        if ($value < $min || $value > $max) {
            throw new RuntimeException($key . ' harus berada di antara ' . $min . ' dan ' . $max . '.');
        }
        return $value;
    }

    private function parseNumber(mixed $value): float
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return 0.0;
            }
        }
        return is_numeric($value) ? (float) $value : NAN;
    }
}
