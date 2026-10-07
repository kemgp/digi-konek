<?php
declare(strict_types=1);

final class Marketplace {
    use Settings;
    public function updateProfile(array $user, array $input): void {
        $name = field($input, 'name', 120);
        $company = field($input, 'business_name', 160);
        $email = strtolower(field($input, 'email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
        $table = ['supplier'=>'suppliers', 'retailer'=>'retailers', 'administrator'=>'administrators'][$user['role']] ?? null;
        if (!$table) throw new DomainException('Invalid account role.');
        try {
            $this->transaction(function () use ($user, $input, $name, $company, $email, $table) {
                $account = row('SELECT * FROM users WHERE id=? AND active=1 FOR UPDATE', [$user['id']]);
                if (!$account) throw new DomainException('Account not available.');
                if ($email !== $account['email'] && !password_verify((string)($input['current_password'] ?? ''), $account['password_hash'])) {
                    throw new DomainException('Enter your current password to change your email address.');
                }
                execute('UPDATE users SET name=?, email=? WHERE id=?', [$name, $email, $user['id']]);
                execute("UPDATE $table SET business_name=? WHERE user_id=?", [$company, $user['id']]);
                $this->audit((int)$user['id'], 'profile.updated', 'Account name, email, and company profile saved');
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('That email address is already in use.');
            throw $e;
        }
    }
    public function markNotificationRead(array $user, int $id): void {
        if (!row('SELECT id FROM notifications WHERE id=? AND user_id=?', [$id, $user['id']])) throw new DomainException('Notification not found.');
        execute('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?', [$id, $user['id']]);
    }
    public function saveReview(array $user, array $input): void {
        requireRole($user, 'retailer');
        $orderId = positiveInt($input['order_id'] ?? 0, PHP_INT_MAX);
        $rating = positiveInt($input['rating'] ?? 0, 5);
        $comment = field($input, 'comment', 2000);
        $type = $input['review_type'] ?? '';
        if (!in_array($type, ['product','seller'], true)) throw new DomainException('Choose a product or seller review.');
        $this->transaction(function () use ($user, $input, $orderId, $rating, $comment, $type) {
            $order = row("SELECT * FROM orders WHERE id=? AND retailer_id=? AND status='completed' FOR UPDATE", [$orderId, $user['id']]);
            if (!$order) throw new DomainException('You can review only your own completed purchases.');
            if ($type === 'product') {
                $target = positiveInt($input['product_id'] ?? 0, PHP_INT_MAX);
                if (!row('SELECT id FROM order_items WHERE order_id=? AND product_id=?', [$orderId, $target])) throw new DomainException('This product is not part of that completed order.');
                $table = 'product_reviews'; $column = 'product_id';
            } else { $target = (int)$order['supplier_id']; $table = 'seller_reviews'; $column = 'supplier_id'; }
            execute("INSERT INTO $table (retailer_id,$column,order_id,rating,comment) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE order_id=VALUES(order_id),rating=VALUES(rating),comment=VALUES(comment)", [$user['id'],$target,$orderId,$rating,$comment]);
            $this->notify((int)$order['supplier_id'], 'A retailer saved a ' . $type . ' review for order DK-' . $orderId . '.', 'reviews');
            $this->notify((int)$user['id'], 'Your ' . $type . ' review for order DK-' . $orderId . ' was saved.', 'reviews');
            $this->notifyAdministrators('A retailer saved a ' . $type . ' review for order DK-' . $orderId . '.', 'reviews');
            $this->audit((int)$user['id'], 'review.saved', "$type #$target; completed order DK-$orderId");
        });
    }
    public function transaction(callable $work): mixed {
        db()->beginTransaction();
        try { $result = $work(); db()->commit(); return $result; }
        catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
    }
    public function audit(int $userId, string $action, string $detail): void {
        execute('INSERT INTO audit_logs (user_id, action, detail) VALUES (?, ?, ?)', [$userId, $action, $detail]);
    }
    public function notify(int $userId, string $message, string $type = 'orders'): void {
        if (!in_array($type, ['orders','reviews','verification','stock'], true)) throw new InvalidArgumentException('Invalid notification type.');
        if (!preferences($userId)['notify_' . $type]) return;
        execute('INSERT INTO notifications (user_id, message) VALUES (?, ?)', [$userId, $message]);
    }
    public function register(array $input): int {
        $name = field($input, 'name', 120); $email = strtolower(field($input, 'email', 190));
        $business = field($input, 'business_name', 160); $role = $input['role'] ?? '';
        $password = (string)($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
        if (!in_array($role, ['supplier', 'retailer'], true)) throw new DomainException('Select a retailer or supplier account.');
        if (strlen($password) < 12 || strlen($password) > 72) throw new DomainException('Use a password between 12 and 72 bytes.');
        if ($password !== ($input['password_confirmation'] ?? '')) throw new DomainException('Passwords do not match.');
        try {
            return $this->transaction(function () use ($name, $email, $business, $role, $password) {
                execute('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                $id = (int)db()->lastInsertId();
                execute("INSERT INTO {$role}s (user_id, business_name) VALUES (?, ?)", [$id, $business]);
                if ($role === 'retailer') execute('INSERT INTO carts (user_id) VALUES (?)', [$id]);
                $this->audit($id, 'account.registered', "$role account created");
                return $id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('This email address is already registered.');
            throw $e;
        }
    }
    public function login(string $email, string $password, string $ip): int {
        $email = strtolower(trim($email));
        $key = hash('sha256', $email . '|' . $ip);
        $attempt = row('SELECT * FROM login_attempts WHERE identity_hash = ?', [$key]);
        if ($attempt && (int)$attempt['attempts'] >= 8 && strtotime($attempt['last_attempt']) > time() - 900) throw new DomainException('Too many sign-in attempts. Try again in 15 minutes.');
        $user = row('SELECT * FROM users WHERE email = ?', [$email]);
        $valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid || !$user['active']) {
            execute('INSERT INTO login_attempts (identity_hash, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = IF(last_attempt < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1), last_attempt = NOW()', [$key]);
            throw new DomainException('The email or password is incorrect, or the account is inactive.');
        }
        execute('DELETE FROM login_attempts WHERE identity_hash = ?', [$key]);
        $this->audit((int)$user['id'], 'account.login', 'Signed in');
        return (int)$user['id'];
    }
    public function verified(array $user): void {
        requireRole($user, 'supplier');
        $supplier = row('SELECT s.verification_status, u.active FROM suppliers s JOIN users u ON u.id=s.user_id WHERE s.user_id = ?', [$user['id']]);
        if (!$supplier || !$supplier['active'] || $supplier['verification_status'] !== 'verified') throw new DomainException('Your supplier account must be verified before you can manage products or accept orders.');
    }
    public function saveProduct(array $user, array $input): void {
        $this->verified($user);
        $name = field($input, 'name', 160); $sku = field($input, 'sku', 80); $description = field($input, 'description', 5000);
        $price = trim((string)($input['price'] ?? ''));
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $price)) throw new DomainException('Enter a valid price with up to two decimal places.');
        $parts = explode('.', $price); $cents = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
        if ($cents < 1) throw new DomainException('Price must be greater than zero.');
        $min = positiveInt($input['minimum_quantity'] ?? 0);
        $stock = filter_var($input['stock'] ?? '', FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0 || $stock > 1000000) throw new DomainException('Stock must be between 0 and 1,000,000.');
        $category = positiveInt($input['category_id'] ?? 0); $brand = positiveInt($input['brand_id'] ?? 0);
        if (!row('SELECT id FROM categories WHERE id = ?', [$category]) || !row('SELECT id FROM brands WHERE id = ?', [$brand])) throw new DomainException('Select a valid category and brand.');
        $id = (int)($input['id'] ?? 0);
        try {
            $this->transaction(function () use ($user, $id, $name, $sku, $description, $cents, $min, $stock, $category, $brand) {
                $this->verified($user);
                if ($id) {
                    if (!row('SELECT id FROM products WHERE id = ? AND supplier_id = ? FOR UPDATE', [$id, $user['id']])) throw new DomainException('Product not found.');
                    execute('UPDATE products SET name=?, sku=?, description=?, price_cents=?, minimum_quantity=?, category_id=?, brand_id=? WHERE id=?', [$name, $sku, $description, $cents, $min, $category, $brand, $id]);
                    execute('UPDATE inventory SET stock=? WHERE product_id=?', [$stock, $id]);
                } else {
                    execute('INSERT INTO products (supplier_id, name, sku, description, price_cents, minimum_quantity, category_id, brand_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$user['id'], $name, $sku, $description, $cents, $min, $category, $brand]);
                    $id = (int)db()->lastInsertId();
                    execute('INSERT INTO inventory (product_id, stock) VALUES (?, ?)', [$id, $stock]);
                }
                $this->checkLowStock($id);
                $this->audit((int)$user['id'], 'product.saved', "Product #$id; available stock $stock");
            });
        } catch (PDOException $e) { if ($e->getCode() === '23000') throw new DomainException('You already have a product with that SKU.'); throw $e; }
    }
    public function addCart(array $user, int $productId, int $quantity): void {
        requireRole($user, 'retailer'); positiveInt($quantity);
        $this->transaction(function () use ($user, $productId, $quantity) {
            row('SELECT user_id FROM carts WHERE user_id=? FOR UPDATE', [$user['id']]);
            $p = row("SELECT p.*, i.stock FROM products p JOIN inventory i ON i.product_id=p.id JOIN suppliers s ON s.user_id=p.supplier_id JOIN users u ON u.id=s.user_id WHERE p.id=? AND p.active=1 AND s.verification_status='verified' AND u.active=1", [$productId]);
            if (!$p) throw new DomainException('This product is no longer available.');
            $old = row('SELECT quantity FROM cart_items WHERE user_id=? AND product_id=?', [$user['id'], $productId]);
            $total = $quantity + (int)($old['quantity'] ?? 0);
            if ($total < (int)$p['minimum_quantity'] || $total > (int)$p['stock'] || $total > 100000) throw new DomainException('Quantity must meet the minimum order and available stock.');
            execute('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)', [$user['id'], $productId, $total]);
        });
    }
    public function removeCart(array $user, int $productId): void {
        requireRole($user, 'retailer');
        $this->transaction(function () use ($user, $productId) {
            row('SELECT user_id FROM carts WHERE user_id=? FOR UPDATE', [$user['id']]);
            execute('DELETE FROM cart_items WHERE user_id=? AND product_id=?', [$user['id'], $productId]);
        });
    }
    public function checkout(array $user, string $quote): array {
        requireRole($user, 'retailer');
        return $this->transaction(function () use ($user, $quote) {
            row('SELECT user_id FROM carts WHERE user_id=? FOR UPDATE', [$user['id']]);
            $cart = $this->cart((int)$user['id']);
            if (!$cart) throw new DomainException('Your cart is empty.');
            // Lock products in deterministic order before validating the current quote.
            foreach ($cart as $item) {
                row('SELECT id FROM products WHERE id=? FOR UPDATE', [$item['id']]);
                row('SELECT product_id FROM inventory WHERE product_id=? FOR UPDATE', [$item['id']]);
                row('SELECT user_id FROM suppliers WHERE user_id=? LOCK IN SHARE MODE', [$item['supplier_id']]);
                row('SELECT id FROM users WHERE id=? LOCK IN SHARE MODE', [$item['supplier_id']]);
            }
            $cart = $this->cart((int)$user['id']);
            if (!hash_equals($this->quote($cart), $quote)) throw new DomainException('Your cart or prices changed. Review the updated cart before placing your order.');
            $groups = [];
            foreach ($cart as $item) {
                if (!$item['active'] || !$item['supplier_active'] || $item['verification_status'] !== 'verified') throw new DomainException('A product or supplier is no longer available. Remove that item to continue.');
                if ($item['quantity'] < $item['minimum_quantity'] || $item['quantity'] > $item['stock']) throw new DomainException('Stock or minimum quantity changed for ' . $item['name'] . '. Please update your cart.');
                $groups[$item['supplier_id']][] = $item;
            }
            $ids = [];
            foreach ($groups as $supplierId => $items) {
                $total = array_sum(array_map(fn($i) => (int)$i['price_cents'] * (int)$i['quantity'], $items));
                execute('INSERT INTO orders (retailer_id, supplier_id, total_cents) VALUES (?, ?, ?)', [$user['id'], $supplierId, $total]);
                $orderId = (int)db()->lastInsertId(); $ids[] = $orderId;
                foreach ($items as $item) {
                    execute('INSERT INTO order_items (order_id, product_id, product_name, quantity, price_cents) VALUES (?, ?, ?, ?, ?)', [$orderId, $item['id'], $item['name'], $item['quantity'], $item['price_cents']]);
                    execute('UPDATE inventory SET stock=stock-? WHERE product_id=?', [$item['quantity'], $item['id']]);
                    $this->checkLowStock((int)$item['id']);
                }
                $this->notifyAdministrators("Wholesale order DK-$orderId was submitted.", "orders");
                $this->notify((int)$supplierId, "New wholesale order DK-$orderId is awaiting your review.");
                $this->notify((int)$user['id'], "Order DK-$orderId submitted. Awaiting supplier confirmation.");
                $this->audit((int)$user['id'], 'order.placed', "Order DK-$orderId; total $total centavos");
            }
            execute('DELETE FROM cart_items WHERE user_id=?', [$user['id']]);
            return $ids;
        });
    }
    public function cart(int $userId): array {
        return rows('SELECT p.*, c.quantity, i.stock, s.business_name, s.verification_status, u.active AS supplier_active FROM cart_items c JOIN products p ON p.id=c.product_id JOIN inventory i ON i.product_id=p.id JOIN suppliers s ON s.user_id=p.supplier_id JOIN users u ON u.id=s.user_id WHERE c.user_id=? ORDER BY p.id', [$userId]);
    }
    public function quote(array $cart): string { return hash('sha256', json_encode(array_map(fn($i) => [(int)$i['id'], (int)$i['quantity'], (int)$i['price_cents']], $cart), JSON_THROW_ON_ERROR)); }
    public function transition(array $user, int $id, string $next): void {
        $this->transaction(function () use ($user, $id, $next) {
            $order = row('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id]);
            if (!$order) throw new DomainException('Order not found.');
            $supplier = $user['role'] === 'supplier' && (int)$order['supplier_id'] === (int)$user['id'];
            $retailer = $user['role'] === 'retailer' && (int)$order['retailer_id'] === (int)$user['id'];
            $allowed = ($supplier && $order['status'] === 'pending' && in_array($next, ['accepted', 'rejected'], true)) || ($supplier && $order['status'] === 'accepted' && $next === 'completed') || ($retailer && $order['status'] === 'pending' && $next === 'cancelled');
            if (!$allowed) throw new DomainException('This order update is not allowed. Refresh to see the latest status.');
            if ($supplier && $next === 'accepted') $this->verified($user);
            if (in_array($next, ['rejected', 'cancelled'], true)) {
                foreach (rows('SELECT product_id, quantity FROM order_items WHERE order_id=? ORDER BY product_id', [$id]) as $item) { execute('UPDATE inventory SET stock=stock+? WHERE product_id=?', [$item['quantity'], $item['product_id']]); $this->checkLowStock((int)$item['product_id']); }
            }
            execute('UPDATE orders SET status=? WHERE id=?', [$next, $id]);
            $this->notifyAdministrators("Order DK-$id is now $next.", "orders");
            $this->notify((int)$order['retailer_id'], "Order DK-$id is now $next.");
            $this->notify((int)$order['supplier_id'], "Order DK-$id is now $next.");
            $this->audit((int)$user['id'], 'order.' . $next, "Order DK-$id");
        });
    }
    public function verifySupplier(array $user, int $supplierId, string $status, string $note): void {
        requireRole($user, 'administrator');
        if (!in_array($status, ['verified', 'rejected'], true) || mb_strlen($note) > 500) throw new DomainException('Enter a valid review decision and note.');
        if ($status === 'rejected' && trim($note) === '') throw new DomainException('Explain why the supplier was rejected.');
        $this->transaction(function () use ($user, $supplierId, $status, $note) {
            if (!row('SELECT user_id FROM suppliers WHERE user_id=? FOR UPDATE', [$supplierId])) throw new DomainException('Supplier not found.');
            if ($status === 'verified') foreach (requiredDocuments() as $type) { if (!row('SELECT id FROM supplier_documents WHERE supplier_id=? AND document_type=?', [$supplierId,$type])) throw new DomainException('Missing required document: ' . $type); }
            execute('UPDATE suppliers SET verification_status=?, review_note=? WHERE user_id=?', [$status, trim($note), $supplierId]);
            $this->notify($supplierId, 'Your supplier verification is ' . $status . '. ' . trim($note), 'verification');
            $this->audit((int)$user['id'], 'supplier.' . $status, "Supplier #$supplierId");
        });
    }
}
