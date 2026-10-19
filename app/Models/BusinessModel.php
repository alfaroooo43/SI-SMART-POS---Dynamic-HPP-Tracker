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

    public function products(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE p.active = 1' : '';
        return $this->all(
            "SELECT p.*, c.name AS category_name,
                CASE WHEN p.type = 'retail' THEN p.average_cost
                     ELSE COALESCE((SELECT SUM(ri.quantity * i.average_cost) / NULLIF(r.yield_quantity, 0)
                         FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                         JOIN ingredients i ON i.id = ri.ingredient_id WHERE r.product_id = p.id), 0)
                END AS current_cost
             FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY p.name"
        );
    }

    public function productCost(int $productId, bool $lock = false): array
    {
        $product = $this->one('SELECT * FROM products WHERE id = ? AND active = 1' . ($lock ? ' FOR UPDATE' : ''), [$productId]);
        if (!$product) {
            throw new RuntimeException('Produk tidak ditemukan atau sudah dinonaktifkan.');
        }

        if ($product['type'] === 'retail') {
            return ['product' => $product, 'unit_cost' => (float) $product['average_cost'], 'ingredients' => []];
        }

        $recipe = $this->one('SELECT * FROM recipes WHERE product_id = ?', [$productId]);
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
        $recipeCost = array_sum(array_map(
            static fn (array $item): float => (float) $item['quantity'] * (float) $item['average_cost'],
            $ingredients
        ));
        return [
            'product' => $product,
            'unit_cost' => $recipeCost / (float) $recipe['yield_quantity'],
            'ingredients' => $ingredients,
            'yield' => (float) $recipe['yield_quantity'],
        ];
    }

    public function saveCategory(?int $id, string $name): void
    {
        if ($id) {
            $statement = $this->db->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $statement->execute([$name, $id]);
            return;
        }
        $this->db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    }

    public function saveSupplier(?int $id, array $data): void
    {
        if ($id) {
            $this->db->prepare('UPDATE suppliers SET name = ?, phone = ?, address = ? WHERE id = ?')
                ->execute([$data['name'], $data['phone'], $data['address'], $id]);
            return;
        }
        $this->db->prepare('INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)')
            ->execute([$data['name'], $data['phone'], $data['address']]);
    }

    public function saveIngredient(?int $id, array $data): void
    {
        if ($id) {
            $this->db->prepare('UPDATE ingredients SET name = ?, unit = ?, minimum_stock = ? WHERE id = ?')
                ->execute([$data['name'], $data['unit'], $data['minimum_stock'], $id]);
            return;
        }
        $this->db->prepare('INSERT INTO ingredients (name, unit, minimum_stock) VALUES (?, ?, ?)')
            ->execute([$data['name'], $data['unit'], $data['minimum_stock']]);
    }

    public function saveProduct(?int $id, array $data): void
    {
        $values = [
            $data['category_id'] ?: null, $data['name'], $data['type'], $data['unit'],
            $data['selling_price'], $data['minimum_stock'], $data['min_margin'], $data['target_margin'],
        ];
        if ($id) {
            $this->db->prepare(
                'UPDATE products SET category_id = ?, name = ?, type = ?, unit = ?, selling_price = ?,
                    minimum_stock = ?, min_margin = ?, target_margin = ? WHERE id = ?'
            )->execute([...$values, $id]);
            return;
        }
        $this->db->prepare(
            'INSERT INTO products (category_id, name, type, unit, selling_price, minimum_stock, min_margin, target_margin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute($values);
    }

    public function setProductActive(int $id, bool $active): void
    {
        $this->db->prepare('UPDATE products SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    public function saveUser(?int $id, array $data): void
    {
        if ($id) {
            if ($data['password'] !== '') {
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
        $this->db->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    public function saveRecipe(int $productId, float $yield, array $items): void
    {
        if ($yield <= 0 || !$items) {
            throw new RuntimeException('Jumlah hasil resep harus lebih dari nol dan minimal satu bahan harus dipilih.');
        }
        $this->db->beginTransaction();
        try {
            $product = $this->one('SELECT id, type FROM products WHERE id = ? FOR UPDATE', [$productId]);
            if (!$product || $product['type'] !== 'culinary') {
                throw new RuntimeException('Resep hanya dapat diatur untuk produk kuliner.');
            }
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
        $lines = array_values(array_filter($lines, static fn (array $line): bool =>
            (int) $line['item_id'] > 0 && (float) $line['quantity'] > 0 && (float) $line['unit_cost'] >= 0
        ));
        if (!$lines) {
            throw new RuntimeException('Tambahkan minimal satu barang dengan kuantitas yang valid.');
        }
        $this->db->beginTransaction();
        try {
            $total = array_sum(array_map(
                static fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_cost'],
                $lines
            ));
            $this->db->prepare(
                'INSERT INTO purchases (supplier_id, user_id, invoice_number, purchased_at, total)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$data['supplier_id'], $userId, $data['invoice_number'] ?: null, $data['purchased_at'], $total]);
            $purchaseId = (int) $this->db->lastInsertId();
            $insert = $this->db->prepare(
                'INSERT INTO purchase_items (purchase_id, item_type, item_id, quantity, unit_cost, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($lines as $line) {
                $itemType = $line['item_type'] === 'product' ? 'product' : 'ingredient';
                $item = $itemType === 'product'
                    ? $this->one('SELECT id, type FROM products WHERE id = ? AND active = 1', [(int) $line['item_id']])
                    : $this->one('SELECT id FROM ingredients WHERE id = ? AND active = 1', [(int) $line['item_id']]);
                if (!$item || ($itemType === 'product' && $item['type'] !== 'retail')) {
                    throw new RuntimeException('Barang pembelian tidak valid. Pembelian produk hanya berlaku untuk produk retail.');
                }
                $insert->execute([
                    $purchaseId, $itemType, (int) $line['item_id'], $line['quantity'],
                    $line['unit_cost'], (float) $line['quantity'] * (float) $line['unit_cost'],
                ]);
            }
            $this->db->commit();
            return $purchaseId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
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
                    $this->db->prepare(
                        'INSERT INTO cost_history (item_type, item_id, old_cost, new_cost, source_type, source_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$item['item_type'], $item['item_id'], $oldCost, $newCost, 'purchase', $purchaseId]);
                }
                $this->db->prepare(
                    'INSERT INTO inventory_movements
                     (item_type, item_id, movement_type, reference_type, reference_id, quantity_change, unit_cost)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$item['item_type'], $item['item_id'], 'purchase', 'purchase', $purchaseId, $quantity, $item['unit_cost']]);
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

    public function createSale(array $quantities, float $discount, float $paid, string $method, int $userId): int
    {
        $quantities = array_filter($quantities, static fn (float $quantity): bool => $quantity > 0);
        if (!$quantities) {
            throw new RuntimeException('Pilih minimal satu produk untuk transaksi.');
        }
        if (!in_array($method, ['cash', 'transfer', 'qris', 'debit'], true)) {
            throw new RuntimeException('Metode pembayaran tidak valid.');
        }
        $this->db->beginTransaction();
        try {
            ksort($quantities);
            $items = [];
            $subtotal = 0.0;
            $costTotal = 0.0;
            foreach ($quantities as $productId => $quantity) {
                $cost = $this->productCost((int) $productId, true);
                $product = $cost['product'];
                $quantity = (float) $quantity;
                if ($product['type'] === 'retail' && (float) $product['stock'] < $quantity) {
                    throw new RuntimeException('Stok produk "' . $product['name'] . '" tidak mencukupi.');
                }
                if ($product['type'] === 'culinary') {
                    foreach ($cost['ingredients'] as $ingredient) {
                        $required = (float) $ingredient['quantity'] / $cost['yield'] * $quantity;
                        if ((float) $ingredient['stock'] < $required) {
                            throw new RuntimeException('Stok bahan "' . $ingredient['name'] . '" tidak cukup untuk produk "' . $product['name'] . '".');
                        }
                    }
                }
                $lineTotal = (float) $product['selling_price'] * $quantity;
                $lineCost = $cost['unit_cost'] * $quantity;
                $subtotal += $lineTotal;
                $costTotal += $lineCost;
                $items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_cost' => $cost['unit_cost'],
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'ingredients' => $cost['ingredients'],
                    'yield' => $cost['yield'] ?? 1,
                ];
            }
            if ($discount < 0 || $discount > $subtotal) {
                throw new RuntimeException('Diskon tidak boleh melebihi subtotal transaksi.');
            }
            $total = $subtotal - $discount;
            if ($paid < $total) {
                throw new RuntimeException('Jumlah pembayaran kurang dari total transaksi.');
            }
            $receipt = 'TRX-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $this->db->prepare(
                'INSERT INTO sales
                 (receipt_number, user_id, sold_at, subtotal, discount, total, cost_total, payment_method, amount_paid, change_amount)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$receipt, $userId, $subtotal, $discount, $total, $costTotal, $method, $paid, $paid - $total]);
            $saleId = (int) $this->db->lastInsertId();
            $insertLine = $this->db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, unit_cost, line_total, line_cost)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $product = $item['product'];
                $insertLine->execute([
                    $saleId, $product['id'], $product['name'], $item['quantity'], $product['selling_price'],
                    $item['unit_cost'], $item['line_total'], $item['line_cost'],
                ]);
                if ($product['type'] === 'retail') {
                    $this->db->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')
                        ->execute([$item['quantity'], $product['id']]);
                    $this->recordMovement('product', (int) $product['id'], 'sale', $saleId, -(float) $item['quantity'], $item['unit_cost']);
                    continue;
                }
                foreach ($item['ingredients'] as $ingredient) {
                    $used = (float) $ingredient['quantity'] / $item['yield'] * $item['quantity'];
                    $this->db->prepare('UPDATE ingredients SET stock = stock - ? WHERE id = ?')
                        ->execute([$used, $ingredient['ingredient_id']]);
                    $this->recordMovement('ingredient', (int) $ingredient['ingredient_id'], 'sale', $saleId, -$used, (float) $ingredient['average_cost']);
                }
            }
            $this->db->commit();
            return $saleId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function recordMovement(string $type, int $id, string $movement, int $reference, float $quantity, float $cost): void
    {
        $this->db->prepare(
            'INSERT INTO inventory_movements
             (item_type, item_id, movement_type, reference_type, reference_id, quantity_change, unit_cost)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$type, $id, $movement, 'sale', $reference, $quantity, $cost]);
    }

    public function dashboard(string $from, string $to): array
    {
        $summary = $this->one(
            'SELECT COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(total - cost_total), 0) AS gross_profit,
                COUNT(*) AS transactions
             FROM sales WHERE DATE(sold_at) BETWEEN ? AND ?',
            [$from, $to]
        );
        $lowRetail = (int) $this->scalar('SELECT COUNT(*) FROM products WHERE active = 1 AND type = "retail" AND stock <= minimum_stock');
        $lowIngredients = (int) $this->scalar('SELECT COUNT(*) FROM ingredients WHERE active = 1 AND stock <= minimum_stock');
        return [
            'summary' => $summary,
            'low_stock' => $lowRetail + $lowIngredients,
            'recent_sales' => $this->all(
                'SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id ORDER BY s.sold_at DESC LIMIT 8'
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
                    SELECT p2.id, CASE WHEN p2.type = 'retail' THEN p2.average_cost
                        ELSE COALESCE((SELECT SUM(ri.quantity * i.average_cost) / NULLIF(r.yield_quantity, 0)
                            FROM recipes r JOIN recipe_items ri ON ri.recipe_id = r.id
                            JOIN ingredients i ON i.id = ri.ingredient_id WHERE r.product_id = p2.id), 0) END AS current_cost
                    FROM products p2
                 ) q ON q.id = p.id WHERE p.active = 1
                 HAVING margin < min_margin ORDER BY margin ASC LIMIT 5"
            ),
        ];
    }

    public function report(string $from, string $to): array
    {
        return [
            'summary' => $this->one(
                'SELECT COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(cost_total), 0) AS cogs,
                    COALESCE(SUM(total - cost_total), 0) AS gross_profit, COUNT(*) AS transactions
                 FROM sales WHERE DATE(sold_at) BETWEEN ? AND ?',
                [$from, $to]
            ),
            'daily' => $this->all(
                'SELECT DATE(sold_at) AS day, COUNT(*) AS transactions, SUM(total) AS revenue,
                    SUM(cost_total) AS cogs, SUM(total - cost_total) AS gross_profit
                 FROM sales WHERE DATE(sold_at) BETWEEN ? AND ? GROUP BY DATE(sold_at) ORDER BY day DESC',
                [$from, $to]
            ),
            'top_products' => $this->all(
                'SELECT si.product_name, SUM(si.quantity) AS quantity, SUM(si.line_total) AS revenue,
                    SUM(si.line_cost) AS cogs, SUM(si.line_total - si.line_cost) AS gross_profit
                 FROM sale_items si JOIN sales s ON s.id = si.sale_id
                 WHERE DATE(s.sold_at) BETWEEN ? AND ? GROUP BY si.product_id, si.product_name
                 ORDER BY revenue DESC LIMIT 10',
                [$from, $to]
            ),
            'purchases' => $this->all(
                "SELECT p.id, p.invoice_number, p.purchased_at, p.total, p.status, s.name AS supplier
                 FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
                 WHERE p.purchased_at BETWEEN ? AND ? ORDER BY p.purchased_at DESC",
                [$from, $to]
            ),
        ];
    }
}
