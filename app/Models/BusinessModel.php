<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class BusinessModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    public function all(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function products(
        bool $activeOnly = false,
        string $search = '',
        ?int $categoryId = null,
        ?string $status = null,
        ?string $type = null
    ): array
    {
        $conditions = [];
        $params = [];
        if ($activeOnly) {
            $conditions[] = 'p.active = 1';
        }
        if ($search !== '') {
            $conditions[] = 'p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($status === 'active' || $status === 'archived') {
            $conditions[] = 'p.active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        if ($type === 'retail' || $type === 'culinary') {
            $conditions[] = 'p.type = ?';
            $params[] = $type;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return $this->all(
            "SELECT p.*, c.name AS category_name,
                CASE WHEN p.type = 'retail' THEN NULLIF(p.average_cost, 0)
                     WHEN EXISTS (
                         SELECT 1 FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                         JOIN ingredients i ON i.id = ri.ingredient_id
                         WHERE r.product_id = p.id AND i.average_cost <= 0
                     ) THEN NULL
                     ELSE (SELECT SUM(ri.quantity * i.average_cost) / NULLIF(r.yield_quantity, 0)
                         FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                         JOIN ingredients i ON i.id = ri.ingredient_id WHERE r.product_id = p.id)
                END AS current_cost,
                (SELECT GROUP_CONCAT(DISTINCT i.name ORDER BY i.name SEPARATOR ', ')
                 FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                 JOIN ingredients i ON i.id = ri.ingredient_id
                 WHERE r.product_id = p.id AND i.average_cost <= 0) AS missing_cost_ingredients,
                (SELECT COUNT(*) FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                 WHERE r.product_id = p.id) AS recipe_item_count,
                (SELECT r.yield_quantity FROM recipes r WHERE r.product_id = p.id) AS recipe_yield_quantity
             FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY p.name",
            $params
        );
    }

    public function productCost(int $productId, bool $lock = false): array
    {
        $product = $this->one('SELECT * FROM products WHERE id = ? AND active = 1' . ($lock ? ' FOR UPDATE' : ''), [$productId]);
        if (!$product) {
            throw new RuntimeException('Produk tidak ditemukan atau sudah dinonaktifkan.');
        }

        if ($product['type'] === 'retail') {
            if ((float) $product['average_cost'] <= 0) {
                throw new RuntimeException('HPP produk ' . $product['name'] . ' belum tersedia karena biaya per unit belum diisi. Masukkan biaya yang valid pada data produk.');
            }
            return ['product' => $product, 'unit_cost' => (float) $product['average_cost'], 'ingredients' => []];
        }

        $recipe = $this->one('SELECT * FROM recipes WHERE product_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$productId]);
        if (!$recipe || (float) $recipe['yield_quantity'] <= 0) {
            throw new RuntimeException('Resep produk belum diatur dengan hasil produksi yang valid.');
        }
        $ingredients = $this->all(
            'SELECT ri.ingredient_id, ri.quantity, i.name, i.unit, i.stock, i.average_cost
             FROM recipe_items ri JOIN ingredients i ON i.id = ri.ingredient_id
             WHERE ri.recipe_id = ? ORDER BY i.id' . ($lock ? ' FOR UPDATE' : ''),
            [$recipe['id']]
        );
        if (!$ingredients) {
            throw new RuntimeException('Resep produk belum memiliki bahan baku.');
        }
        $missingCostIngredients = array_values(array_map(
            static fn (array $item): string => (float) $item['average_cost'] <= 0 ? (string) $item['name'] : '',
            $ingredients
        ));
        $missingCostIngredients = array_values(array_filter($missingCostIngredients, static fn (string $name): bool => $name !== ''));
        if ($missingCostIngredients) {
            throw new RuntimeException(
                'Perhitungan HPP belum dapat dilakukan karena bahan ' . implode(', ', $missingCostIngredients)
                . ' belum memiliki biaya per satuan. Silakan masukkan harga bahan baku atau catat stok masuk beserta harga belinya.'
            );
        }
        $recipeCost = array_sum(array_map(
            static fn (array $item): float => (float) $item['quantity'] * (float) $item['average_cost'],
            $ingredients
        ));
        return [
            'product' => $product,
            'recipe_cost' => $recipeCost,
            'unit_cost' => $recipeCost / (float) $recipe['yield_quantity'],
            'ingredients' => $ingredients,
            'yield' => (float) $recipe['yield_quantity'],
        ];
    }

    private function recipeCostIfAvailable(int $productId): ?float
    {
        $recipe = $this->one(
            'SELECT r.yield_quantity, COUNT(ri.ingredient_id) AS ingredient_count,
                    SUM(CASE WHEN i.average_cost <= 0 THEN 1 ELSE 0 END) AS missing_cost_count,
                    SUM(ri.quantity * i.average_cost) AS total_cost
             FROM recipes r
             LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
             LEFT JOIN ingredients i ON i.id = ri.ingredient_id
             WHERE r.product_id = ?
             GROUP BY r.id, r.yield_quantity',
            [$productId]
        );
        if (!$recipe || (float) $recipe['yield_quantity'] <= 0
            || (int) $recipe['ingredient_count'] === 0 || (int) $recipe['missing_cost_count'] > 0) {
            return null;
        }
        return (float) $recipe['total_cost'] / (float) $recipe['yield_quantity'];
    }

    public function saveCategory(?int $id, string $name): void
    {
        $this->assertUniqueValue('categories', 'name', $name, $id);
        if ($id) {
            $statement = $this->db->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $statement->execute([$name, $id]);
            if ($statement->rowCount() === 0 && !$this->one('SELECT id FROM categories WHERE id = ?', [$id])) {
                throw new RuntimeException('Kategori yang akan diubah tidak ditemukan.');
            }
            return;
        }
        $this->db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    }

    public function deleteCategory(int $id): void
    {
        if ($id <= 0 || !$this->one('SELECT id FROM categories WHERE id = ?', [$id])) {
            throw new RuntimeException('Kategori tidak ditemukan.');
        }
        if ((int) $this->scalar('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]) > 0) {
            throw new RuntimeException('Kategori masih dipakai produk. Ubah kategori produk terlebih dahulu agar data katalog tetap konsisten.');
        }
        $this->db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    }

    public function saveSupplier(?int $id, array $data): void
    {
        $this->assertUniqueValue('suppliers', 'name', $data['name'], $id);
        if ($id) {
            $statement = $this->db->prepare('UPDATE suppliers SET name = ?, phone = ?, address = ? WHERE id = ?');
            $statement
                ->execute([$data['name'], $data['phone'], $data['address'], $id]);
            if ($statement->rowCount() === 0 && !$this->one('SELECT id FROM suppliers WHERE id = ?', [$id])) {
                throw new RuntimeException('Supplier yang akan diubah tidak ditemukan.');
            }
            return;
        }
        $this->db->prepare('INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)')
            ->execute([$data['name'], $data['phone'], $data['address']]);
    }

    public function setSupplierActive(int $id, bool $active): void
    {
        if (!$active && (int) $this->scalar('SELECT COUNT(*) FROM purchases WHERE supplier_id = ? AND status = \'draft\'', [$id]) > 0) {
            throw new RuntimeException('Supplier masih dipakai pembelian draft. Selesaikan atau batalkan draft tersebut terlebih dahulu.');
        }
        $statement = $this->db->prepare('UPDATE suppliers SET active = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $id]);
        if ($statement->rowCount() === 0 && !$this->one('SELECT id FROM suppliers WHERE id = ?', [$id])) {
            throw new RuntimeException('Supplier tidak ditemukan.');
        }
    }

    public function deleteSupplier(int $id): void
    {
        $this->deleteUnusedMasterRecord('suppliers', $id, 'Supplier', [
            'SELECT COUNT(*) FROM purchases WHERE supplier_id = ?' =>
                'Supplier masih memiliki riwayat pembelian dan tidak dapat dihapus. Gunakan arsip agar histori tetap tersimpan.',
        ]);
    }

    public function saveIngredient(?int $id, array $data): void
    {
        $this->assertUniqueValue('ingredients', 'name', $data['name'], $id);
        if (!is_finite((float) $data['average_cost']) || (float) $data['average_cost'] < 0) {
            throw new RuntimeException('Harga bahan baku harus berupa angka nol atau lebih.');
        }
        if ($id) {
            $this->db->beginTransaction();
            try {
                $existing = $this->one('SELECT name, unit, stock, average_cost FROM ingredients WHERE id = ? FOR UPDATE', [$id]);
                if (!$existing) {
                    throw new RuntimeException('Bahan baku yang akan diubah tidak ditemukan.');
                }
                if ($data['unit'] !== $existing['unit']) {
                    if ((float) $existing['stock'] > 0) {
                        throw new RuntimeException('Satuan bahan tidak dapat diubah selama masih ada stok. Koreksi stok sampai nol atau buat bahan baru dengan satuan yang benar.');
                    }
                    if ((int) $this->scalar('SELECT COUNT(*) FROM recipe_items WHERE ingredient_id = ?', [$id]) > 0) {
                        throw new RuntimeException('Satuan bahan masih dipakai resep. Ubah resep terkait atau buat bahan baru agar HPP tidak berubah makna.');
                    }
                }
                $statement = $this->db->prepare(
                    'UPDATE ingredients SET name = ?, unit = ?, average_cost = ?, minimum_stock = ? WHERE id = ?'
                );
                $statement->execute([$data['name'], $data['unit'], $data['average_cost'], $data['minimum_stock'], $id]);
                if (abs((float) $existing['average_cost'] - (float) $data['average_cost']) >= 0.01) {
                    $this->recordCostHistory(
                        'ingredient',
                        $id,
                        (float) $existing['average_cost'],
                        (float) $data['average_cost'],
                        'manual',
                        $id,
                        $data['name']
                    );
                }
                $this->db->commit();
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
            return;
        }
        $this->db->prepare('INSERT INTO ingredients (name, unit, average_cost, minimum_stock) VALUES (?, ?, ?, ?)')
            ->execute([$data['name'], $data['unit'], $data['average_cost'], $data['minimum_stock']]);
    }

    public function setIngredientActive(int $id, bool $active): void
    {
        if (!$active) {
            $ingredient = $this->one('SELECT stock FROM ingredients WHERE id = ?', [$id]);
            if (!$ingredient) {
                throw new RuntimeException('Bahan baku tidak ditemukan.');
            }
            if ((float) $ingredient['stock'] > 0) {
                throw new RuntimeException('Bahan baku masih memiliki stok. Habiskan atau koreksi stok terlebih dahulu sebelum mengarsipkannya.');
            }
            if ((int) $this->scalar('SELECT COUNT(*) FROM recipe_items WHERE ingredient_id = ?', [$id]) > 0) {
                throw new RuntimeException('Bahan baku masih dipakai resep. Ubah resep terkait terlebih dahulu agar HPP dan penjualan tetap konsisten.');
            }
            if ((int) $this->scalar(
                'SELECT COUNT(*) FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id
                 WHERE pi.item_type = \'ingredient\' AND pi.item_id = ? AND p.status = \'draft\'',
                [$id]
            ) > 0) {
                throw new RuntimeException('Bahan baku masih dipakai pembelian draft. Selesaikan atau batalkan draft tersebut terlebih dahulu.');
            }
        }
        $statement = $this->db->prepare('UPDATE ingredients SET active = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $id]);
        if ($statement->rowCount() === 0 && !$this->one('SELECT id FROM ingredients WHERE id = ?', [$id])) {
            throw new RuntimeException('Bahan baku tidak ditemukan.');
        }
    }

    public function deleteIngredient(int $id): void
    {
        $ingredient = $this->one('SELECT id, name, stock, unit FROM ingredients WHERE id = ?', [$id]);
        if (!$ingredient) {
            throw new RuntimeException('Bahan baku tidak ditemukan.');
        }

        $purchaseCount = (int) $this->scalar('SELECT COUNT(*) FROM purchase_items WHERE item_type = \'ingredient\' AND item_id = ?', [$id]);
        $inventoryCount = (int) $this->scalar('SELECT COUNT(*) FROM inventory_movements WHERE item_type = \'ingredient\' AND item_id = ?', [$id]);
        $costCount = (int) $this->scalar('SELECT COUNT(*) FROM cost_history WHERE item_type = \'ingredient\' AND item_id = ?', [$id]);
        $recipeCount = (int) $this->scalar('SELECT COUNT(*) FROM recipe_items WHERE ingredient_id = ?', [$id]);

        if ((float) $ingredient['stock'] > 0) {
            throw new RuntimeException('Bahan baku ' . $ingredient['name'] . ' masih memiliki stok ' . format_quantity((float) $ingredient['stock']) . ' ' . $ingredient['unit'] . '. Habiskan atau koreksi stok sebelum menghapus permanen.');
        }

        $blockers = [];
        if ($purchaseCount > 0) {
            $blockers[] = 'riwayat pembelian';
        }
        if ($inventoryCount > 0) {
            $blockers[] = 'mutasi stok';
        }
        if ($costCount > 0) {
            $blockers[] = 'riwayat HPP';
        }
        if ($recipeCount > 0) {
            $relatedRecipes = $this->all(
                'SELECT DISTINCT p.name AS recipe_name FROM recipe_items ri
                 JOIN recipes r ON r.id = ri.recipe_id
                 JOIN products p ON p.id = r.product_id
                 WHERE ri.ingredient_id = ?
                 ORDER BY p.name LIMIT 10',
                [$id]
            );
            $details = implode(', ', array_map(static fn (array $row): string => $row['recipe_name'], $relatedRecipes));
            if ($details !== '') {
                throw new RuntimeException(
                    'Bahan baku ' . $ingredient['name'] . ' masih dipakai pada resep: ' . $details . '. Buka halaman resep yang bersangkutan dan hapus atau ganti penggunaan bahan terlebih dahulu.'
                );
            }
        }
        if ($blockers) {
            throw new RuntimeException(
                'Bahan baku ' . $ingredient['name'] . ' tidak dapat dihapus permanen karena masih terkait dengan ' . implode(', ', $blockers) . '. Arsipkan atau selesaikan catatan lama terlebih dahulu agar histori tetap aman.'
            );
        }

        $this->db->beginTransaction();
        try {
            if ($recipeCount > 0) {
                $this->db->prepare('DELETE FROM recipe_items WHERE ingredient_id = ?')->execute([$id]);
            }
            $statement = $this->db->prepare('DELETE FROM ingredients WHERE id = ?');
            $statement->execute([$id]);
            if ($statement->rowCount() === 0) {
                throw new RuntimeException('Bahan baku tidak dapat dihapus.');
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function saveProduct(?int $id, array $data): void
    {
        if (!in_array($data['type'], ['culinary', 'retail'], true)) {
            throw new RuntimeException('Jenis produk tidak valid.');
        }
        $this->assertUniqueValue('products', 'name', $data['name'], $id);
        if ($data['category_id'] && !$this->one('SELECT id FROM categories WHERE id = ?', [(int) $data['category_id']])) {
            throw new RuntimeException('Kategori produk tidak valid.');
        }
        if ($id) {
            $existing = $this->one('SELECT id, type, unit, stock FROM products WHERE id = ?', [$id]);
            if (!$existing) {
                throw new RuntimeException('Produk yang akan diubah tidak ditemukan.');
            }
            if ($data['unit'] !== $existing['unit']) {
                if ((float) $existing['stock'] > 0) {
                    throw new RuntimeException('Satuan produk tidak dapat diubah selama stok masih tersedia. Habiskan stok atau buat produk baru dengan satuan yang benar.');
                }
                if ($existing['type'] === 'culinary' && (int) $this->scalar('SELECT COUNT(*) FROM recipes WHERE product_id = ?', [$id]) > 0) {
                    throw new RuntimeException('Satuan produk kuliner masih terkait hasil resep. Pertahankan satuan atau buat produk baru agar HPP tetap konsisten.');
                }
            }
            $this->db->prepare(
                'UPDATE products SET category_id = ?, name = ?, unit = ?, selling_price = ?,
                    minimum_stock = ?, min_margin = ?, target_margin = ? WHERE id = ?'
            )->execute([
                $data['category_id'] ?: null, $data['name'], $data['unit'], $data['selling_price'],
                $data['minimum_stock'], $data['min_margin'], $data['target_margin'], $id,
            ]);
            return;
        }
        $this->db->prepare(
            'INSERT INTO products (category_id, name, type, unit, selling_price, minimum_stock, min_margin, target_margin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['category_id'] ?: null, $data['name'], $data['type'], $data['unit'],
            $data['selling_price'], $data['minimum_stock'], $data['min_margin'], $data['target_margin'],
        ]);
    }

    public function setProductActive(int $id, bool $active): void
    {
        if (!$active) {
            $product = $this->one('SELECT type, stock FROM products WHERE id = ?', [$id]);
            if (!$product) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }
            if ($product['type'] === 'retail' && (float) $product['stock'] > 0) {
                throw new RuntimeException('Produk retail masih memiliki stok. Habiskan atau koreksi stok sebelum mengarsipkannya.');
            }
            if ((int) $this->scalar(
                'SELECT COUNT(*) FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id
                 WHERE pi.item_type = \'product\' AND pi.item_id = ? AND p.status = \'draft\'',
                [$id]
            ) > 0) {
                throw new RuntimeException('Produk masih dipakai pembelian draft. Selesaikan atau batalkan draft tersebut terlebih dahulu.');
            }
        }
        $statement = $this->db->prepare('UPDATE products SET active = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $id]);
        if ($statement->rowCount() === 0 && !$this->one('SELECT id FROM products WHERE id = ?', [$id])) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }
    }

    public function deleteProduct(int $id): void
    {
        $product = $this->one('SELECT id, name, stock, type, unit FROM products WHERE id = ?', [$id]);
        if (!$product) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }

        $purchaseCount = (int) $this->scalar('SELECT COUNT(*) FROM purchase_items WHERE item_type = \'product\' AND item_id = ?', [$id]);
        $saleCount = (int) $this->scalar('SELECT COUNT(*) FROM sale_items WHERE product_id = ?', [$id]);
        $inventoryCount = (int) $this->scalar('SELECT COUNT(*) FROM inventory_movements WHERE item_type = \'product\' AND item_id = ?', [$id]);
        $costCount = (int) $this->scalar('SELECT COUNT(*) FROM cost_history WHERE item_type = \'product\' AND item_id = ?', [$id]);

        if ((float) $product['stock'] > 0) {
            throw new RuntimeException('Produk ' . $product['name'] . ' masih memiliki stok ' . format_quantity((float) $product['stock']) . ' ' . $product['unit'] . '. Habiskan atau koreksi stok sebelum menghapus permanen.');
        }

        $blockers = [];
        if ($purchaseCount > 0) {
            $blockers[] = 'riwayat pembelian';
        }
        if ($saleCount > 0) {
            $blockers[] = 'riwayat penjualan';
        }
        if ($inventoryCount > 0) {
            $blockers[] = 'mutasi stok';
        }
        if ($costCount > 0) {
            $blockers[] = 'riwayat HPP';
        }
        if ($blockers) {
            throw new RuntimeException(
                'Produk ' . $product['name'] . ' tidak dapat dihapus permanen karena masih terkait dengan ' . implode(', ', $blockers) . '. Arsipkan atau selesaikan catatan lama terlebih dahulu agar histori tetap aman.'
            );
        }

        $this->db->beginTransaction();
        try {
            if ((int) $this->scalar('SELECT COUNT(*) FROM recipes WHERE product_id = ?', [$id]) > 0) {
                $this->db->prepare('DELETE FROM recipes WHERE product_id = ?')->execute([$id]);
            }
            $statement = $this->db->prepare('DELETE FROM products WHERE id = ?');
            $statement->execute([$id]);
            if ($statement->rowCount() === 0) {
                throw new RuntimeException('Produk tidak dapat dihapus.');
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function deleteUnusedMasterRecord(string $table, int $id, string $label, array $references): void
    {
        if (!in_array($table, ['products', 'ingredients', 'suppliers'], true)) {
            throw new RuntimeException('Jenis data tidak dapat dihapus.');
        }
        if ($id <= 0) {
            throw new RuntimeException($label . ' tidak ditemukan.');
        }

        $this->db->beginTransaction();
        try {
            if (!$this->one("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$id])) {
                throw new RuntimeException($label . ' tidak ditemukan.');
            }
            foreach ($references as $sql => $message) {
                if ((int) $this->scalar($sql, [$id]) > 0) {
                    throw new RuntimeException($message);
                }
            }

            $this->db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function saveUser(?int $id, array $data): void
    {
        if ($data['role'] !== 'admin') {
            throw new RuntimeException('Peran operasional tidak valid.');
        }
        $this->assertUniqueEmail($data['email'], $id);
        if ($id) {
            $existing = $this->one('SELECT role FROM users WHERE id = ?', [$id]);
            if (!$existing || $existing['role'] === 'owner') {
                throw new RuntimeException('Akun pemilik tidak dapat diubah melalui pengelolaan akun operasional.');
            }
            if ($data['password'] !== '') {
                if (strlen($data['password']) < 10) {
                    throw new RuntimeException('Kata sandi baru minimal 10 karakter.');
                }
                $this->db->prepare('UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?')
                    ->execute([$data['name'], $data['email'], $data['role'], password_hash($data['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $this->db->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?')
                    ->execute([$data['name'], $data['email'], $data['role'], $id]);
            }
            return;
        }
        if (strlen($data['password']) < 10) {
            throw new RuntimeException('Kata sandi pengguna baru minimal 10 karakter.');
        }
        $this->db->prepare('INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)')
            ->execute([$data['name'], $data['email'], $data['role'], password_hash($data['password'], PASSWORD_DEFAULT)]);
    }

    public function setUserActive(int $id, bool $active, int $currentUserId): void
    {
        if ($id === $currentUserId && !$active) {
            throw new RuntimeException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
        }
        $statement = $this->db->prepare('UPDATE users SET active = ? WHERE id = ? AND role <> \'owner\'');
        $statement->execute([$active ? 1 : 0, $id]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Pengguna tidak ditemukan atau akun pemilik tidak dapat dinonaktifkan dari halaman ini.');
        }
    }

    public function settings(): array
    {
        $settings = [];
        foreach ($this->all('SELECT setting_key, setting_value FROM settings') as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
        return $settings;
    }

    public function saveSettings(array $settings): void
    {
        $allowedKeys = ['business_name', 'business_phone', 'business_address'];
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($allowedKeys as $key) {
                $value = trim((string) ($settings[$key] ?? ''));
                $maxLength = $key === 'business_address' ? 255 : 120;
                if (mb_strlen($value) > $maxLength) {
                    throw new RuntimeException('Nilai pengaturan ' . $key . ' terlalu panjang.');
                }
                $statement->execute([$key, $value]);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertUniqueValue(string $table, string $column, string $value, ?int $id): void
    {
        $allowedColumns = [
            'categories' => ['name'],
            'suppliers' => ['name'],
            'ingredients' => ['name'],
            'products' => ['name'],
        ];
        if (!in_array($column, $allowedColumns[$table] ?? [], true)) {
            throw new RuntimeException('Kolom pemeriksaan duplikat tidak valid.');
        }
        $sql = "SELECT id FROM $table WHERE LOWER($column) = LOWER(?)";
        $params = [$value];
        if ($id !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $id;
        }
        if ($this->one($sql . ' LIMIT 1', $params)) {
            throw new RuntimeException('Nama sudah digunakan. Pilih nama lain agar data tidak tercatat ganda.');
        }
    }

    private function assertUniqueEmail(string $email, ?int $id): void
    {
        $sql = 'SELECT id FROM users WHERE LOWER(email) = LOWER(?)';
        $params = [$email];
        if ($id !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $id;
        }
        if ($this->one($sql . ' LIMIT 1', $params)) {
            throw new RuntimeException('Email sudah digunakan oleh akun lain.');
        }
    }

    public function saveRecipe(int $productId, float $yield, array $items): void
    {
        if (!is_finite($yield) || $yield <= 0 || !$items) {
            throw new RuntimeException('Jumlah hasil resep harus lebih dari nol dan minimal satu bahan harus dipilih.');
        }
        foreach ($items as $ingredientId => $quantity) {
            if ((int) $ingredientId <= 0 || !is_finite((float) $quantity) || (float) $quantity <= 0) {
                throw new RuntimeException('Bahan resep dan jumlahnya harus valid serta lebih dari nol.');
            }
            if (!$this->one('SELECT id FROM ingredients WHERE id = ? AND active = 1', [(int) $ingredientId])) {
                throw new RuntimeException('Pilih bahan baku aktif yang valid untuk resep.');
            }
        }
        $this->db->beginTransaction();
        try {
            $product = $this->one('SELECT id, type, name FROM products WHERE id = ? FOR UPDATE', [$productId]);
            if (!$product || $product['type'] !== 'culinary') {
                throw new RuntimeException('Resep hanya dapat diatur untuk produk kuliner.');
            }
            $oldCost = $this->recipeCostIfAvailable($productId);
            $this->db->prepare(
                'INSERT INTO recipes (product_id, yield_quantity) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE yield_quantity = VALUES(yield_quantity)'
            )->execute([$productId, $yield]);
            $recipeId = (int) $this->scalar('SELECT id FROM recipes WHERE product_id = ?', [$productId]);
            $this->db->prepare('DELETE FROM recipe_items WHERE recipe_id = ?')->execute([$recipeId]);
            $insert = $this->db->prepare('INSERT INTO recipe_items (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)');
            foreach ($items as $ingredientId => $quantity) {
                if ($quantity > 0) {
                    $insert->execute([$recipeId, (int) $ingredientId, $quantity]);
                }
            }
            if ((int) $this->scalar('SELECT COUNT(*) FROM recipe_items WHERE recipe_id = ?', [$recipeId]) === 0) {
                throw new RuntimeException('Resep harus memiliki bahan baku dengan jumlah lebih dari nol.');
            }
            $newCost = $this->recipeCostIfAvailable($productId);
            if ($oldCost !== null && $newCost !== null && abs($oldCost - $newCost) >= 0.01) {
                $this->recordCostHistory('product', $productId, $oldCost, $newCost, 'recipe', $productId, (string) $product['name']);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteRecipeIngredient(int $productId, int $ingredientId): void
    {
        if ($productId <= 0 || $ingredientId <= 0) {
            throw new RuntimeException('Produk atau bahan resep yang dipilih tidak valid.');
        }
        $this->db->beginTransaction();
        try {
            $product = $this->one(
                "SELECT id, name FROM products WHERE id = ? AND type = 'culinary' FOR UPDATE",
                [$productId]
            );
            if (!$product) {
                throw new RuntimeException('Produk kuliner tidak ditemukan.');
            }
            $recipe = $this->one('SELECT id FROM recipes WHERE product_id = ? FOR UPDATE', [$productId]);
            if (!$recipe || !$this->one(
                'SELECT recipe_id FROM recipe_items WHERE recipe_id = ? AND ingredient_id = ? FOR UPDATE',
                [$recipe['id'], $ingredientId]
            )) {
                throw new RuntimeException('Bahan tersebut tidak ditemukan pada resep produk ini.');
            }
            $oldCost = $this->recipeCostIfAvailable($productId);
            $this->db->prepare('DELETE FROM recipe_items WHERE recipe_id = ? AND ingredient_id = ?')
                ->execute([$recipe['id'], $ingredientId]);
            $newCost = $this->recipeCostIfAvailable($productId);
            if ($oldCost !== null && $newCost !== null && abs($oldCost - $newCost) >= 0.01) {
                $this->recordCostHistory('product', $productId, $oldCost, $newCost, 'recipe', $productId, (string) $product['name']);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function createPurchase(array $data, array $lines, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $this->assertActiveSupplier((int) $data['supplier_id']);
            $this->db->prepare(
                'INSERT INTO purchases (supplier_id, user_id, invoice_number, purchased_at, total)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$data['supplier_id'], $userId, $data['invoice_number'] ?: null, $data['purchased_at']]);
            $purchaseId = (int) $this->db->lastInsertId();
            $total = $this->insertPurchaseItems($purchaseId, $lines);
            $this->db->prepare('UPDATE purchases SET total = ? WHERE id = ?')->execute([$total, $purchaseId]);
            $this->db->commit();
            return $purchaseId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateDraftPurchase(int $purchaseId, array $data, array $lines): void
    {
        $this->db->beginTransaction();
        try {
            $purchase = $this->one('SELECT id, status FROM purchases WHERE id = ? FOR UPDATE', [$purchaseId]);
            if (!$purchase || $purchase['status'] !== 'draft') {
                throw new RuntimeException('Hanya pembelian draft yang dapat diubah.');
            }
            $this->assertActiveSupplier((int) $data['supplier_id']);
            $total = $this->insertPurchaseItems($purchaseId, $lines, true);
            $this->db->prepare(
                'UPDATE purchases SET supplier_id = ?, invoice_number = ?, purchased_at = ?, total = ?
                 WHERE id = ? AND status = \'draft\''
            )->execute([
                $data['supplier_id'],
                $data['invoice_number'] ?: null,
                $data['purchased_at'],
                $total,
                $purchaseId,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertActiveSupplier(int $supplierId): void
    {
        if ($supplierId <= 0 || !$this->one('SELECT id FROM suppliers WHERE id = ? AND active = 1', [$supplierId])) {
            throw new RuntimeException('Pilih supplier aktif yang valid.');
        }
    }

    private function insertPurchaseItems(int $purchaseId, array $lines, bool $replace = false): float
    {
        if (!$lines) {
            throw new RuntimeException('Tambahkan minimal satu barang dengan kuantitas yang valid.');
        }
        if ($replace) {
            $this->db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$purchaseId]);
        }
        $insert = $this->db->prepare(
            'INSERT INTO purchase_items
             (purchase_id, item_type, item_id, ingredient_id, product_id, item_name_snapshot, unit_snapshot, quantity, unit_cost, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $total = 0.0;
        foreach ($lines as $line) {
            $itemType = (string) ($line['item_type'] ?? '');
            $itemId = (int) ($line['item_id'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitCost = (float) ($line['unit_cost'] ?? -1);
            if (
                !in_array($itemType, ['product', 'ingredient'], true)
                || $itemId <= 0
                || !is_finite($quantity)
                || $quantity <= 0
                || !is_finite($unitCost)
                || $unitCost < 0
            ) {
                throw new RuntimeException('Rincian pembelian memiliki barang, kuantitas, atau biaya yang tidak valid.');
            }
            $item = $itemType === 'product'
                ? $this->one('SELECT id, name, unit, type FROM products WHERE id = ? AND active = 1', [$itemId])
                : $this->one('SELECT id, name, unit FROM ingredients WHERE id = ? AND active = 1', [$itemId]);
            if (!$item || ($itemType === 'product' && $item['type'] !== 'retail')) {
                throw new RuntimeException('Barang pembelian tidak valid. Pembelian produk hanya berlaku untuk produk retail aktif.');
            }
            $lineTotal = round($quantity * $unitCost, 2);
            $total += $lineTotal;
            $insert->execute([
                $purchaseId,
                $itemType,
                $itemId,
                $itemType === 'ingredient' ? $itemId : null,
                $itemType === 'product' ? $itemId : null,
                $item['name'],
                $item['unit'],
                $quantity,
                $unitCost,
                $lineTotal,
            ]);
        }
        return round($total, 2);
    }

    public function confirmPurchase(int $purchaseId): void
    {
        $this->db->beginTransaction();
        try {
            $purchase = $this->one('SELECT * FROM purchases WHERE id = ? FOR UPDATE', [$purchaseId]);
            if (!$purchase || $purchase['status'] !== 'draft') {
                throw new RuntimeException('Pembelian tidak ditemukan atau sudah diproses.');
            }
            $items = $this->all('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY item_type, item_id', [$purchaseId]);
            if (!$items) {
                throw new RuntimeException('Pembelian tidak memiliki rincian barang.');
            }
            foreach ($items as $item) {
                $table = $item['item_type'] === 'product' ? 'products' : 'ingredients';
                $record = $this->one("SELECT stock, average_cost FROM $table WHERE id = ? FOR UPDATE", [$item['item_id']]);
                if (!$record) {
                    throw new RuntimeException('Barang pembelian tidak lagi tersedia.');
                }
                $oldStock = (float) $record['stock'];
                $oldCost = (float) $record['average_cost'];
                $quantity = (float) $item['quantity'];
                $newStock = $oldStock + $quantity;
                $newCost = $newStock > 0
                    ? (($oldStock * $oldCost) + ($quantity * (float) $item['unit_cost'])) / $newStock
                    : (float) $item['unit_cost'];
                $this->db->prepare("UPDATE $table SET stock = ?, average_cost = ? WHERE id = ?")
                    ->execute([$newStock, $newCost, $item['item_id']]);
                if (abs($oldCost - $newCost) >= 0.01) {
                    $this->recordCostHistory(
                        $item['item_type'],
                        (int) $item['item_id'],
                        $oldCost,
                        $newCost,
                        'purchase',
                        $purchaseId,
                        $item['item_name_snapshot']
                    );
                }
                $this->recordMovement(
                    $item['item_type'],
                    (int) $item['item_id'],
                    'purchase',
                    $purchaseId,
                    $quantity,
                    (float) $item['unit_cost'],
                    $item['item_name_snapshot'],
                    $item['unit_snapshot']
                );
            }
            $this->db->prepare("UPDATE purchases SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?")
                ->execute([$purchaseId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelPurchase(int $purchaseId): void
    {
        $statement = $this->db->prepare("UPDATE purchases SET status = 'cancelled' WHERE id = ? AND status = 'draft'");
        $statement->execute([$purchaseId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Hanya pembelian berstatus draft yang dapat dibatalkan; pembelian terkonfirmasi tidak dapat diubah agar stok historis tetap konsisten.');
        }
    }

    public function adjustInventory(string $type, int $id, float $delta, string $note, int $userId): void
    {
        if (!in_array($type, ['ingredient', 'product'], true) || $id <= 0 || !is_finite($delta) || $delta == 0) {
            throw new RuntimeException('Jenis barang, ID, atau jumlah penyesuaian tidak valid.');
        }
        $table = $type === 'product' ? 'products' : 'ingredients';
        $this->db->beginTransaction();
        try {
            $record = $this->one("SELECT stock, average_cost, name, unit" . ($type === 'product' ? ', type' : '') . " FROM $table WHERE id = ? AND active = 1 FOR UPDATE", [$id]);
            if (!$record || ($type === 'product' && $record['type'] !== 'retail')) {
                throw new RuntimeException('Barang tidak ditemukan atau bukan persediaan yang dapat disesuaikan.');
            }
            $newStock = (float) $record['stock'] + $delta;
            if ($newStock < 0) {
                throw new RuntimeException('Penyesuaian tidak dapat membuat stok menjadi negatif.');
            }
            $this->db->prepare("UPDATE $table SET stock = ? WHERE id = ?")->execute([$newStock, $id]);
            $this->recordMovement($type, $id, 'adjustment', $userId, $delta, (float) $record['average_cost'], $record['name'], $record['unit'], $note, $userId);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function setInventoryStock(string $type, int $id, float $stock, string $note, int $userId): void
    {
        if (!in_array($type, ['ingredient', 'product'], true) || $id <= 0 || !is_finite($stock)
            || trim($note) === '' || mb_strlen($note) > 255) {
            throw new RuntimeException('Barang, jumlah stok, dan alasan perubahan harus diisi dengan benar.');
        }
        if ($stock < 0) {
            throw new RuntimeException('Jumlah stok tidak boleh negatif.');
        }
        $table = $type === 'product' ? 'products' : 'ingredients';
        $this->db->beginTransaction();
        try {
            $record = $this->one(
                "SELECT stock, average_cost, name, unit" . ($type === 'product' ? ', type' : '') . " FROM $table WHERE id = ? AND active = 1 FOR UPDATE",
                [$id]
            );
            if (!$record || ($type === 'product' && $record['type'] !== 'retail')) {
                throw new RuntimeException('Barang tidak ditemukan atau bukan persediaan yang dapat diubah.');
            }
            $delta = round($stock - (float) $record['stock'], 3);
            if ($delta == 0.0) {
                throw new RuntimeException('Jumlah stok baru sama dengan jumlah stok saat ini.');
            }
            $this->db->prepare("UPDATE $table SET stock = ? WHERE id = ?")->execute([$stock, $id]);
            $this->recordMovement(
                $type,
                $id,
                'adjustment',
                $userId,
                $delta,
                (float) $record['average_cost'],
                $record['name'],
                $record['unit'],
                $note,
                $userId
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateInventoryMovement(int $movementId, float $quantity, string $note, string $createdAt, int $userId): void
    {
        if ($movementId <= 0 || !is_finite($quantity) || $quantity == 0.0
            || trim($note) === '' || mb_strlen($note) > 255) {
            throw new RuntimeException('Mutasi stok, jumlah, dan keterangan harus diisi dengan benar.');
        }
        $this->db->beginTransaction();
        try {
            $movement = $this->one(
                "SELECT * FROM inventory_movements
                 WHERE id = ? AND movement_type = 'adjustment' AND reference_type = 'manual'
                   AND (note IS NULL OR note NOT LIKE 'Pembatalan %') FOR UPDATE",
                [$movementId]
            );
            if (!$movement) {
                throw new RuntimeException('Hanya koreksi stok manual yang dapat diedit.');
            }
            $table = $movement['item_type'] === 'product' ? 'products' : 'ingredients';
            $item = $this->one("SELECT stock FROM $table WHERE id = ? FOR UPDATE", [$movement['item_id']]);
            if (!$item) {
                throw new RuntimeException('Barang terkait tidak ditemukan; histori stok tidak diubah.');
            }
            $newStock = (float) $item['stock'] + $quantity - (float) $movement['quantity_change'];
            if ($newStock < 0) {
                throw new RuntimeException('Perubahan akan membuat stok menjadi negatif.');
            }
            $this->auditInventoryMovement($movement, 'updated', $userId);
            $this->db->prepare("UPDATE $table SET stock = ? WHERE id = ?")->execute([$newStock, $movement['item_id']]);
            $this->db->prepare(
                'UPDATE inventory_movements SET quantity_change = ?, note = ?, created_at = ?, user_id = ? WHERE id = ?'
            )->execute([$quantity, $note, $createdAt, $userId, $movementId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteInventoryMovement(int $movementId, int $userId): void
    {
        if ($movementId <= 0) {
            throw new RuntimeException('Mutasi stok yang dipilih tidak valid.');
        }
        $this->db->beginTransaction();
        try {
            $movement = $this->one(
                "SELECT * FROM inventory_movements
                 WHERE id = ? AND movement_type = 'adjustment' AND reference_type = 'manual'
                   AND (note IS NULL OR note NOT LIKE 'Pembatalan %') FOR UPDATE",
                [$movementId]
            );
            if (!$movement) {
                throw new RuntimeException('Hanya koreksi stok manual yang dapat dihapus; histori transaksi tetap dilindungi.');
            }
            $table = $movement['item_type'] === 'product' ? 'products' : 'ingredients';
            $item = $this->one("SELECT stock FROM $table WHERE id = ? FOR UPDATE", [$movement['item_id']]);
            if (!$item) {
                throw new RuntimeException('Barang terkait tidak ditemukan; histori stok tidak diubah.');
            }
            $newStock = (float) $item['stock'] - (float) $movement['quantity_change'];
            if ($newStock < 0) {
                throw new RuntimeException('Mutasi ini tidak dapat dihapus karena akan membuat stok menjadi negatif.');
            }
            $this->auditInventoryMovement($movement, 'deleted', $userId);
            $this->db->prepare("UPDATE $table SET stock = ? WHERE id = ?")->execute([$newStock, $movement['item_id']]);
            $this->db->prepare('DELETE FROM inventory_movements WHERE id = ?')->execute([$movementId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function auditInventoryMovement(array $movement, string $action, int $userId): void
    {
        $this->db->prepare(
            'INSERT INTO inventory_movement_audit
             (movement_id, action, item_type, item_id, item_name_snapshot, unit_snapshot,
              movement_type, reference_type, reference_id, quantity_change, unit_cost, note, created_at, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $movement['id'],
            $action,
            $movement['item_type'],
            $movement['item_id'],
            $movement['item_name_snapshot'],
            $movement['unit_snapshot'],
            $movement['movement_type'],
            $movement['reference_type'],
            $movement['reference_id'],
            $movement['quantity_change'],
            $movement['unit_cost'],
            $movement['note'],
            $movement['created_at'],
            $userId,
        ]);
    }

    public function cancelSale(int $saleId, string $note, int $userId): void
    {
        if ($saleId <= 0 || trim($note) === '' || mb_strlen($note) > 255) {
            throw new RuntimeException('Transaksi dan alasan pembatalan harus diisi dengan benar.');
        }
        $this->db->beginTransaction();
        try {
            $sale = $this->one('SELECT id, receipt_number, status FROM sales WHERE id = ? FOR UPDATE', [$saleId]);
            if (!$sale || $sale['status'] !== 'completed') {
                throw new RuntimeException('Transaksi tidak ditemukan atau sudah dibatalkan.');
            }
            $movements = $this->all(
                "SELECT item_type, item_id, quantity_change, unit_cost, item_name_snapshot, unit_snapshot
                 FROM inventory_movements
                 WHERE movement_type = 'sale' AND reference_type = 'sale' AND reference_id = ?
                 ORDER BY item_type, item_id, id FOR UPDATE",
                [$saleId]
            );
            if (!$movements) {
                throw new RuntimeException('Mutasi stok transaksi tidak ditemukan. Pembatalan dihentikan agar stok tidak berubah tanpa jejak.');
            }
            $restock = [];
            foreach ($movements as $movement) {
                $quantity = (float) $movement['quantity_change'];
                if ($quantity >= 0 || !in_array($movement['item_type'], ['ingredient', 'product'], true)) {
                    throw new RuntimeException('Mutasi stok transaksi tidak valid. Pembatalan dihentikan untuk mencegah stok tidak konsisten.');
                }
                $key = $movement['item_type'] . ':' . $movement['item_id'];
                if (!isset($restock[$key])) {
                    $restock[$key] = [
                        'type' => $movement['item_type'],
                        'id' => (int) $movement['item_id'],
                        'quantity' => 0.0,
                        'unit_cost' => (float) $movement['unit_cost'],
                        'name' => $movement['item_name_snapshot'],
                        'unit' => $movement['unit_snapshot'],
                    ];
                }
                $restock[$key]['quantity'] += abs($quantity);
            }
            foreach ($restock as $item) {
                $table = $item['type'] === 'product' ? 'products' : 'ingredients';
                $record = $this->one("SELECT stock FROM $table WHERE id = ? FOR UPDATE", [$item['id']]);
                if (!$record) {
                    throw new RuntimeException('Persediaan transaksi tidak ditemukan. Pembatalan dihentikan agar histori stok tetap konsisten.');
                }
                $this->db->prepare("UPDATE $table SET stock = stock + ? WHERE id = ?")
                    ->execute([$item['quantity'], $item['id']]);
                $noteText = mb_substr('Pembatalan ' . $sale['receipt_number'] . ': ' . trim($note), 0, 255);
                $this->recordMovement(
                    $item['type'],
                    $item['id'],
                    'adjustment',
                    $saleId,
                    $item['quantity'],
                    $item['unit_cost'],
                    $item['name'],
                    $item['unit'],
                    $noteText,
                    $userId,
                    'sale_void'
                );
            }
            $statement = $this->db->prepare(
                "UPDATE sales SET status = 'voided', voided_at = NOW(), voided_by = ?, void_note = ?
                 WHERE id = ? AND status = 'completed'"
            );
            $statement->execute([$userId, trim($note), $saleId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Status transaksi berubah saat pembatalan diproses. Muat ulang halaman.');
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function recordMovement(
        string $type,
        int $id,
        string $movement,
        int $reference,
        float $quantity,
        float $cost,
        string $itemName,
        string $unit,
        ?string $note = null,
        ?int $userId = null,
        ?string $referenceType = null
    ): void
    {
        $this->db->prepare(
            'INSERT INTO inventory_movements
             (item_type, item_id, ingredient_id, product_id, user_id, item_name_snapshot, unit_snapshot,
              movement_type, reference_type, reference_id, quantity_change, unit_cost, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type,
            $id,
            $type === 'ingredient' ? $id : null,
            $type === 'product' ? $id : null,
            $userId,
            $itemName,
            $unit,
            $movement,
            $referenceType ?? ($movement === 'adjustment' ? 'manual' : $movement),
            $reference,
            $quantity,
            $cost,
            $note,
        ]);
    }

    private function recordCostHistory(
        string $type,
        int $id,
        float $oldCost,
        float $newCost,
        string $source,
        int $sourceId,
        string $itemName
    ): void {
        $this->db->prepare(
            'INSERT INTO cost_history
             (item_type, item_id, ingredient_id, product_id, item_name_snapshot, old_cost, new_cost, source_type, source_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type,
            $id,
            $type === 'ingredient' ? $id : null,
            $type === 'product' ? $id : null,
            $itemName,
            $oldCost,
            $newCost,
            $source,
            $sourceId,
        ]);
    }

    public function createExpense(array $data, int $userId): int
    {
        if (!is_finite((float) $data['amount']) || (float) $data['amount'] <= 0) {
            throw new RuntimeException('Biaya operasional harus lebih dari nol.');
        }
        if (!in_array($data['payment_method'], ['cash', 'transfer', 'qris', 'debit', 'other'], true)) {
            throw new RuntimeException('Metode pembayaran biaya operasional tidak valid.');
        }
        $this->db->prepare(
            'INSERT INTO operational_expenses (user_id, expense_date, category, description, amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $data['expense_date'],
            $data['category'],
            $data['description'],
            $data['amount'],
            $data['payment_method'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function voidExpense(int $id, string $note, int $userId): void
    {
        $statement = $this->db->prepare(
            "UPDATE operational_expenses SET status = 'voided', voided_at = NOW(), voided_by = ?, void_note = ?
             WHERE id = ? AND status = 'recorded'"
        );
        $statement->execute([$userId, $note, $id]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Biaya tidak ditemukan atau sudah dibatalkan sebelumnya.');
        }
    }

    public function expenses(string $from, string $to, string $search = ''): array
    {
        $sql = 'SELECT e.*, u.name AS created_by, v.name AS voided_by_name
                FROM operational_expenses e JOIN users u ON u.id = e.user_id
                LEFT JOIN users v ON v.id = e.voided_by
                WHERE e.expense_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($search !== '') {
            $sql .= ' AND (e.category LIKE ? OR e.description LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        return $this->all(
            $sql . ' ORDER BY e.expense_date DESC, e.id DESC',
            $params
        );
    }

    public function dashboard(string $from, string $to): array
    {
        $summary = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS expenses, COUNT(*) AS expense_count
             FROM operational_expenses WHERE status = 'recorded' AND expense_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $productCount = (int) $this->scalar('SELECT COUNT(*) FROM products WHERE active = 1');
        $lowRetail = (int) $this->scalar('SELECT COUNT(*) FROM products WHERE active = 1 AND type = "retail" AND stock <= minimum_stock');
        $lowIngredients = (int) $this->scalar('SELECT COUNT(*) FROM ingredients WHERE active = 1 AND stock <= minimum_stock');
        return [
            'summary' => $summary,
            'product_count' => $productCount,
            'low_stock' => $lowRetail + $lowIngredients,
            'recent_movements' => $this->all(
                "SELECT m.created_at, m.quantity_change, m.unit_snapshot, m.note,
                    COALESCE(m.item_name_snapshot, p.name, i.name, 'Barang') AS item_name
                 FROM inventory_movements m
                 LEFT JOIN products p ON p.id = m.product_id
                 LEFT JOIN ingredients i ON i.id = m.ingredient_id
                 ORDER BY m.created_at DESC, m.id DESC LIMIT 8"
            ),
            'recent_expenses' => $this->all(
                "SELECT category, description, amount, expense_date
                 FROM operational_expenses WHERE status = 'recorded'
                 ORDER BY expense_date DESC, id DESC LIMIT 5"
            ),
            'low_items' => $this->all(
                "SELECT name, stock, minimum_stock, unit, 'Produk retail' AS item_kind FROM products
                 WHERE active = 1 AND type = 'retail' AND stock <= minimum_stock
                 UNION ALL
                 SELECT name, stock, minimum_stock, unit, 'Bahan baku' AS item_kind FROM ingredients
                 WHERE active = 1 AND stock <= minimum_stock ORDER BY stock ASC LIMIT 8"
            ),
            'alerts' => $this->all(
                "SELECT p.id, p.name, p.selling_price, p.min_margin, q.current_cost,
                    CASE WHEN p.selling_price > 0 THEN ((p.selling_price - q.current_cost) / p.selling_price) * 100 ELSE -100 END AS margin
                 FROM products p JOIN (
                    SELECT p2.id, CASE WHEN p2.type = 'retail' THEN NULLIF(p2.average_cost, 0)
                        WHEN EXISTS (
                            SELECT 1 FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                            JOIN ingredients i ON i.id = ri.ingredient_id
                            WHERE r.product_id = p2.id AND i.average_cost <= 0
                        ) THEN NULL
                        ELSE (SELECT SUM(ri.quantity * i.average_cost) / NULLIF(r.yield_quantity, 0)
                            FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                            JOIN ingredients i ON i.id = ri.ingredient_id WHERE r.product_id = p2.id)
                    END AS current_cost
                    FROM products p2
                 ) q ON q.id = p.id WHERE p.active = 1 AND q.current_cost IS NOT NULL
                 HAVING margin < min_margin ORDER BY margin ASC LIMIT 5"
            ),
        ];
    }

    public function report(string $from, string $to): array
    {
        $summary = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
             FROM operational_expenses WHERE status = 'recorded' AND expense_date BETWEEN ? AND ?",
            [$from, $to]
        );
        return [
            'summary' => $summary,
            'daily_expenses' => $this->all(
                "SELECT expense_date AS day, SUM(amount) AS total, COUNT(*) AS count
                 FROM operational_expenses WHERE status = 'recorded' AND expense_date BETWEEN ? AND ?
                 GROUP BY expense_date ORDER BY expense_date DESC",
                [$from, $to]
            ),
            'expenses' => $this->expenses($from, $to),
        ];
    }
}
