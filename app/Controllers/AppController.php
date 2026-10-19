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
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Csrf::verify();
                $this->handlePost((string) ($_POST['action'] ?? ''));
                return;
            }
            $this->handleGet((string) ($_GET['page'] ?? 'dashboard'));
        } catch (Throwable $exception) {
            if ($exception instanceof PDOException) {
                error_log('SMART POS database operation failed: ' . $exception->getMessage());
                flash('error', 'Data tidak dapat disimpan. Periksa kembali input dan koneksi database.');
            } elseif ($exception instanceof RuntimeException) {
                flash('error', $exception->getMessage());
            } else {
                error_log('SMART POS error: ' . $exception);
                flash('error', 'Terjadi kesalahan saat memproses permintaan. Silakan coba kembali.');
            }
            $page = Auth::check() ? (string) ($_POST['return_page'] ?? 'dashboard') : 'login';
            redirect('?page=' . rawurlencode($page));
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
            $this->render('login', ['title' => 'Masuk']);
            return;
        }
        if (!Auth::check()) {
            redirect('?page=login');
        }

        $allowed = [
            'dashboard' => ['owner', 'admin', 'cashier'],
            'pos' => ['owner', 'admin', 'cashier'],
            'sales' => ['owner', 'admin', 'cashier'],
            'products' => ['owner', 'admin'],
            'ingredients' => ['owner', 'admin'],
            'inventory' => ['owner', 'admin'],
            'recipes' => ['owner', 'admin'],
            'suppliers' => ['owner', 'admin'],
            'purchases' => ['owner', 'admin'],
            'reports' => ['owner', 'admin'],
            'users' => ['owner'],
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

        switch ($page) {
            case 'dashboard':
                $data += $this->model->dashboard($from, $to);
                break;
            case 'products':
                $data['products'] = $this->model->products();
                $data['categories'] = $this->model->all('SELECT * FROM categories ORDER BY name');
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
                $data['movements'] = $this->model->all(
                    "SELECT m.id, m.item_type, m.movement_type, m.quantity_change, m.unit_cost, m.note, m.created_at,
                        p.name AS item_name, u.name AS actor
                     FROM inventory_movements m JOIN products p ON m.item_type = 'product' AND p.id = m.item_id
                     LEFT JOIN users u ON m.reference_type = 'manual' AND u.id = m.reference_id
                     UNION ALL
                     SELECT m.id, m.item_type, m.movement_type, m.quantity_change, m.unit_cost, m.note, m.created_at,
                        i.name AS item_name, u.name AS actor
                     FROM inventory_movements m JOIN ingredients i ON m.item_type = 'ingredient' AND i.id = m.item_id
                     LEFT JOIN users u ON m.reference_type = 'manual' AND u.id = m.reference_id
                     ORDER BY created_at DESC LIMIT 50"
                );
                $data['cost_history'] = $this->model->all(
                    "SELECT h.item_type, h.old_cost, h.new_cost, h.source_type, h.changed_at,
                        CASE WHEN h.item_type = 'product' THEN p.name ELSE i.name END AS item_name
                     FROM cost_history h LEFT JOIN products p ON h.item_type = 'product' AND p.id = h.item_id
                     LEFT JOIN ingredients i ON h.item_type = 'ingredient' AND i.id = h.item_id
                     ORDER BY h.changed_at DESC LIMIT 30"
                );
                break;
            case 'recipes':
                $data['products'] = $this->model->all("SELECT * FROM products WHERE active = 1 AND type = 'culinary' ORDER BY name");
                $data['ingredients'] = $this->model->all('SELECT * FROM ingredients WHERE active = 1 ORDER BY name');
                $data['selected_id'] = (int) ($_GET['product_id'] ?? 0);
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
            case 'purchases':
                $data['suppliers'] = $this->model->all('SELECT * FROM suppliers WHERE active = 1 ORDER BY name');
                $data['ingredients'] = $this->model->all('SELECT * FROM ingredients WHERE active = 1 ORDER BY name');
                $data['retail_products'] = $this->model->all("SELECT * FROM products WHERE active = 1 AND type = 'retail' ORDER BY name");
                $data['purchases'] = $this->model->all(
                    'SELECT p.*, s.name AS supplier, u.name AS created_by FROM purchases p
                     JOIN suppliers s ON s.id = p.supplier_id JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 50'
                );
                break;
            case 'pos':
                $data['products'] = $this->model->products(true);
                $data['preview'] = $_SESSION['pos_preview'] ?? null;
                break;
            case 'sales':
                $user = Auth::user();
                $condition = $user['role'] === 'cashier' ? 'WHERE s.user_id = ?' : '';
                $params = $user['role'] === 'cashier' ? [$user['id']] : [];
                $data['sales'] = $this->model->all(
                    "SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id $condition ORDER BY s.sold_at DESC LIMIT 100",
                    $params
                );
                if (isset($_GET['id'])) {
                    $sale = $this->model->one("SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id WHERE s.id = ?" . ($user['role'] === 'cashier' ? ' AND s.user_id = ?' : ''), $user['role'] === 'cashier' ? [(int) $_GET['id'], $user['id']] : [(int) $_GET['id']]);
                    $data['selected_sale'] = $sale;
                    $data['sale_items'] = $sale ? $this->model->all('SELECT * FROM sale_items WHERE sale_id = ?', [$sale['id']]) : [];
                }
                break;
            case 'reports':
                $data += $this->model->report($from, $to);
                break;
            case 'users':
                $data['users'] = $this->model->all('SELECT id, name, email, role, active, created_at FROM users ORDER BY name');
                $data['editing'] = isset($_GET['edit']) ? $this->model->one('SELECT id, name, email, role, active FROM users WHERE id = ?', [(int) $_GET['edit']]) : null;
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
        $ownerOnly = ['save_user', 'toggle_user'];
        $staffActions = [
            'save_category', 'save_supplier', 'save_ingredient', 'save_product', 'toggle_product',
            'save_recipe', 'create_purchase', 'confirm_purchase', 'cancel_purchase', 'adjust_inventory',
        ];
        if (in_array($action, $ownerOnly, true)) {
            Auth::requireRole(['owner']);
        } elseif (in_array($action, $staffActions, true)) {
            Auth::requireRole(['owner', 'admin']);
        } elseif (in_array($action, ['create_sale', 'preview_sale', 'clear_sale_preview'], true)) {
            Auth::requireRole(['owner', 'admin', 'cashier']);
        } else {
            throw new RuntimeException('Tindakan tidak dikenal.');
        }

        switch ($action) {
            case 'save_category':
                $name = $this->requiredText('name', 100);
                $this->model->saveCategory($this->optionalId('id'), $name);
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
            case 'save_ingredient':
                $this->model->saveIngredient($this->optionalId('id'), [
                    'name' => $this->requiredText('name', 150),
                    'unit' => $this->requiredText('unit', 30),
                    'minimum_stock' => $this->number('minimum_stock'),
                ]);
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
                    'minimum_stock' => $this->number('minimum_stock'),
                    'min_margin' => $this->boundedNumber('min_margin', 0, 99),
                    'target_margin' => $this->boundedNumber('target_margin', 0, 99),
                ]);
                $page = 'products';
                break;
            case 'toggle_product':
                $this->model->setProductActive((int) input('id'), input('active') === '1');
                $page = 'products';
                break;
            case 'save_recipe':
                $productId = (int) input('product_id');
                $ingredients = is_array($_POST['ingredient_id'] ?? null) ? $_POST['ingredient_id'] : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
                $items = [];
                foreach ($ingredients as $index => $id) {
                    $quantity = $this->parseNumber($quantities[$index] ?? '');
                    if (!is_finite($quantity)) {
                        throw new RuntimeException('Jumlah bahan resep harus berupa angka yang valid.');
                    }
                    if ((int) $id > 0 && $quantity > 0) {
                        $items[(int) $id] = $quantity;
                    } elseif ((int) $id > 0) {
                        throw new RuntimeException('Setiap bahan resep yang dipilih harus memiliki jumlah lebih dari nol.');
                    }
                }
                $this->model->saveRecipe($productId, $this->number('yield_quantity'), $items);
                $page = 'recipes';
                break;
            case 'create_purchase':
                $refs = is_array($_POST['item_ref'] ?? null) ? $_POST['item_ref'] : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
                $costs = is_array($_POST['unit_cost'] ?? null) ? $_POST['unit_cost'] : [];
                $lines = [];
                foreach ($refs as $index => $ref) {
                    if ($ref === '') {
                        continue;
                    }
                    $parts = explode(':', (string) $ref, 2);
                    if (trim((string) ($quantities[$index] ?? '')) === '' || trim((string) ($costs[$index] ?? '')) === '') {
                        throw new RuntimeException('Lengkapi kuantitas dan biaya untuk setiap barang pembelian yang dipilih.');
                    }
                    $quantity = $this->parseNumber($quantities[$index] ?? '');
                    $unitCost = $this->parseNumber($costs[$index] ?? '');
                    if (!is_finite($quantity) || !is_finite($unitCost) || $quantity <= 0 || $unitCost < 0) {
                        throw new RuntimeException('Setiap baris pembelian terpilih harus memiliki kuantitas lebih dari nol dan biaya yang valid.');
                    }
                    $lines[] = [
                        'item_type' => $parts[0] ?? '',
                        'item_id' => (int) ($parts[1] ?? 0),
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                    ];
                }
                $id = $this->model->createPurchase([
                    'supplier_id' => (int) input('supplier_id'),
                    'invoice_number' => $this->optionalText('invoice_number', 100),
                    'purchased_at' => $this->requiredDate((string) input('purchased_at')),
                ], $lines, (int) Auth::user()['id']);
                flash('success', "Pembelian #$id tersimpan sebagai draft. Konfirmasikan untuk memperbarui stok.");
                $page = 'purchases';
                break;
            case 'confirm_purchase':
                $this->model->confirmPurchase((int) input('id'));
                flash('success', 'Pembelian dikonfirmasi dan stok serta biaya rata-rata telah diperbarui.');
                $page = 'purchases';
                break;
            case 'cancel_purchase':
                $this->model->cancelPurchase((int) input('id'));
                $page = 'purchases';
                break;
            case 'adjust_inventory':
                $itemRef = explode(':', (string) input('item_ref'), 2);
                $type = $itemRef[0] ?? '';
                $itemId = (int) ($itemRef[1] ?? 0);
                $delta = $this->parseNumber(input('quantity_change'));
                if (!is_finite($delta) || $delta == 0) {
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
            case 'create_sale':
                $ids = is_array($_POST['product_id'] ?? null) ? $_POST['product_id'] : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
                $cart = [];
                foreach ($ids as $index => $id) {
                    $id = (int) $id;
                    $quantity = $this->parseNumber($quantities[$index] ?? '');
                    if (!is_finite($quantity)) {
                        throw new RuntimeException('Kuantitas transaksi harus berupa angka yang valid.');
                    }
                    if ($quantity < 0) {
                        throw new RuntimeException('Kuantitas produk tidak boleh negatif.');
                    }
                    if ($id > 0 && $quantity > 0) {
                        $cart[$id] = ($cart[$id] ?? 0) + $quantity;
                    } elseif ($id > 0) {
                        throw new RuntimeException('Kuantitas setiap produk yang dipilih harus lebih dari nol.');
                    } elseif ($quantity > 0) {
                        throw new RuntimeException('Pilih produk untuk setiap kuantitas yang dimasukkan.');
                    }
                }
                $saleId = $this->model->createSale(
                    $cart,
                    $this->number('discount'),
                    $this->number('amount_paid'),
                    (string) input('payment_method'),
                    (int) Auth::user()['id']
                );
                unset($_SESSION['pos_preview']);
                flash('success', 'Transaksi berhasil disimpan dengan nomor ' . $this->model->scalar('SELECT receipt_number FROM sales WHERE id = ?', [$saleId]) . '.');
                redirect('?page=sales&id=' . $saleId);
            case 'preview_sale':
                $ids = is_array($_POST['product_id'] ?? null) ? $_POST['product_id'] : [];
                $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
                $cart = [];
                foreach ($ids as $index => $id) {
                    $id = (int) $id;
                    $quantity = $this->parseNumber($quantities[$index] ?? '');
                    if (!is_finite($quantity)) {
                        throw new RuntimeException('Kuantitas transaksi harus berupa angka yang valid.');
                    }
                    if ($quantity < 0) {
                        throw new RuntimeException('Kuantitas produk tidak boleh negatif.');
                    }
                    if ($id > 0 && $quantity > 0) {
                        $cart[$id] = ($cart[$id] ?? 0) + $quantity;
                    } elseif ($id > 0) {
                        throw new RuntimeException('Kuantitas setiap produk yang dipilih harus lebih dari nol.');
                    } elseif ($quantity > 0) {
                        throw new RuntimeException('Pilih produk untuk setiap kuantitas yang dimasukkan.');
                    }
                }
                $discount = $this->number('discount');
                $preview = $this->model->previewSale($cart, $discount);
                $_SESSION['pos_preview'] = [
                    'quantities' => $cart,
                    'discount' => $discount,
                    'payment_method' => in_array(input('payment_method'), ['cash', 'transfer', 'qris', 'debit'], true) ? input('payment_method') : 'cash',
                    'data' => $preview,
                ];
                flash('success', 'Periksa ringkasan transaksi sebelum menyimpan pembayaran.');
                redirect('?page=pos');
            case 'clear_sale_preview':
                unset($_SESSION['pos_preview']);
                redirect('?page=pos');
            case 'save_user':
                $role = (string) input('role');
                if (!in_array($role, ['admin', 'cashier'], true)) {
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
                $this->model->setUserActive((int) input('id'), input('active') === '1', (int) Auth::user()['id']);
                $page = 'users';
                break;
        }
        flash('success', 'Perubahan berhasil disimpan.');
        redirect('?page=' . rawurlencode($page));
    }

    private function setup(): void
    {
        if ((int) $this->model->scalar('SELECT COUNT(*) FROM users') > 0) {
            redirect('?page=login');
        }
        $this->render('setup', ['title' => 'Buat akun pemilik']);
    }

    private function createInitialOwner(): void
    {
        if ((int) $this->model->scalar('SELECT COUNT(*) FROM users') > 0) {
            redirect('?page=login');
        }
        $name = $this->requiredText('name', 120);
        $email = filter_var(input('email'), FILTER_VALIDATE_EMAIL);
        $password = (string) input('password');
        if (!$email || strlen($password) < 10) {
            throw new RuntimeException('Masukkan email yang valid dan kata sandi minimal 10 karakter.');
        }
        $statement = Database::connection()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "owner")');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
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
            'purchases' => 'Pembelian & stok',
            'pos' => 'Kasir / POS',
            'sales' => 'Transaksi penjualan',
            'reports' => 'Laporan usaha',
            'users' => 'Pengguna & akses',
        ][$page] ?? 'SI SMART POS';
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
        $value = (int) input($key);
        return $value > 0 ? $value : null;
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
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float) $value : NAN;
    }
}
