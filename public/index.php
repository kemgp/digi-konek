<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
foreach (['sessions', 'documents'] as $dir) if (!is_dir(ROOT . '/storage/' . $dir)) mkdir(ROOT . '/storage/' . $dir, 0700, true);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_save_path(ROOT . '/storage/sessions');
session_name('digikonek');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
if (isset($_SESSION['last_seen']) && $_SESSION['last_seen'] < time() - 3600) { $_SESSION = []; session_regenerate_id(true); }
$_SESSION['last_seen'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
$market = new Marketplace();
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
$allowedPages = ['settings','profile','seller','dashboard','catalog','product','compare','cart','orders','order','products','product-edit','verification','suppliers','users','reports','audit','notifications','login','register'];
if (!in_array($page, $allowedPages, true)) { http_response_code(404); $page = 'not-found'; }
$error = null;
try { $user = currentUser(); db(); }
catch (Throwable $e) {
    error_log($e->getMessage()); http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/app.css"><title>Digi-Konek · Setup</title><main class="setup"><span class="eyebrow">DIGI-KONEK</span><h1>Your marketplace starts here.</h1><p>The database connection is not ready. Follow the local setup steps in README.md, then refresh this page.</p><a class="button" href="/">Try again</a></main></html>'; exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(419); throw new DomainException('Your session expired. Refresh the page and try again.'); }
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            $id = $market->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? 'local');
            session_regenerate_id(true); $_SESSION['user_id'] = $id; $_SESSION['session_version'] = (int)row('SELECT session_version FROM users WHERE id=?', [$id])['session_version']; $_SESSION['csrf'] = bin2hex(random_bytes(32)); redirect('dashboard');
        }
        if ($action === 'register') {
            $market->register($_POST); flash('Your account is ready. Sign in to get started.'); redirect('login');
        }
        if (!$user) throw new DomainException('Please sign in to continue.');
        switch ($action) {
            case 'logout': $_SESSION = []; session_regenerate_id(true); redirect('login');
            case 'add-cart': $market->addCart($user, positiveInt($_POST['product_id'] ?? 0), positiveInt($_POST['quantity'] ?? 0)); flash('Product added to your cart.'); redirect('cart');
            case 'remove-cart': $market->removeCart($user, positiveInt($_POST['product_id'] ?? 0)); flash('Item removed.'); redirect('cart');
            case 'checkout':
                $ids = $market->checkout($user, (string)($_POST['quote'] ?? '')); flash(count($ids) . ' order(s) submitted. Your suppliers have been notified.'); redirect('orders');
            case 'order-status': $market->transition($user, positiveInt($_POST['order_id'] ?? 0), (string)($_POST['status'] ?? '')); flash('Order status updated.'); redirect('order', ['id' => (int)$_POST['order_id']]);
            case 'save-product': $market->saveProduct($user, $_POST); flash('Product and available inventory saved.'); redirect('products');
            case 'toggle-product':
                requireRole($user, 'supplier', 'administrator');
                $market->transaction(function () use ($user, $market) {
                    $p = row('SELECT * FROM products WHERE id=? FOR UPDATE', [positiveInt($_POST['product_id'] ?? 0)]);
                    if (!$p || ($user['role'] === 'supplier' && (int)$p['supplier_id'] !== (int)$user['id'])) throw new DomainException('Product not found.');
                    if ($user['role'] === 'supplier') $market->verified($user);
                    execute('UPDATE products SET active=? WHERE id=?', [$p['active'] ? 0 : 1, $p['id']]);
                    $market->audit((int)$user['id'], 'product.visibility', 'Product #' . $p['id'] . ($p['active'] ? ' archived' : ' published'));
                });
                flash('Product visibility updated.'); redirect('products');
            case 'verify-supplier': $market->verifySupplier($user, positiveInt($_POST['supplier_id'] ?? 0), (string)($_POST['status'] ?? ''), trim((string)($_POST['review_note'] ?? ''))); flash('Supplier review saved.'); redirect('suppliers');
            case 'toggle-user':
                requireRole($user, 'administrator');
                $market->transaction(function () use ($user, $market) {
                    $target = row('SELECT * FROM users WHERE id=? FOR UPDATE', [positiveInt($_POST['user_id'] ?? 0)]);
                    if (!$target || $target['role'] === 'administrator') throw new DomainException('Administrator accounts cannot be deactivated here.');
                    execute('UPDATE users SET active=? WHERE id=?', [$target['active'] ? 0 : 1, $target['id']]);
                    $market->audit((int)$user['id'], 'account.status', 'Account #' . $target['id'] . ($target['active'] ? ' deactivated' : ' activated'));
                }); flash('Account status updated.'); redirect('users');
            case 'save-settings': $market->savePreferences($user,$_POST); flash('Settings saved.'); redirect('settings');
            case 'change-password':
            case 'revoke-sessions':
                $_SESSION['session_version']=$market->secureAccount($user,$_POST,$action==='change-password');
                session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
                flash($action==='change-password'?'Password changed. Other sessions have been signed out.':'Other sessions have been signed out.'); redirect('settings');
            case 'save-taxonomy': $market->saveTaxonomy($user,$_POST); flash('Catalog configuration updated.'); redirect('settings');
            case 'save-document-requirements': $market->saveDocumentRequirements($user,$_POST); flash('Document requirements saved.'); redirect('settings');
            case 'save-profile': $market->updateProfile($user, $_POST); flash('Your profile has been updated.'); redirect('profile');
            case 'save-review': $market->saveReview($user, $_POST); flash('Your review has been saved.'); redirect('order', ['id'=>(int)$_POST['order_id']]);
            case 'read-notification': $market->markNotificationRead($user, positiveInt($_POST['notification_id'] ?? 0, PHP_INT_MAX)); flash('Notification marked as read.'); redirect('notifications');
            case 'read-notifications': execute('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL', [$user['id']]); flash('Notifications marked as read.'); redirect('notifications');
            case 'upload-document':
                requireRole($user, 'supplier');
                $file = $_FILES['document'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Choose a PDF, JPEG, or PNG file (up to 5 MB).');
                if ($file['size'] > 5 * 1024 * 1024 || $file['size'] < 1) throw new DomainException('Document must be between 1 byte and 5 MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!in_array($mime, ['application/pdf','image/jpeg','image/png'], true)) throw new DomainException('Only PDF, JPEG, and PNG documents are accepted.');
                $documentType=trim((string)($_POST['document_type']??'Business registration'));
                if (!in_array($documentType,requiredDocuments(),true)) throw new DomainException('Choose a current required document type.');
                $stored = bin2hex(random_bytes(24)); $path = ROOT . '/storage/documents/' . $stored;
                if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Upload storage failed.');
                chmod($path, 0600);
                try {
                    $market->transaction(function () use ($user, $market, $file, $stored, $mime, $documentType) {
                        $supplier = row('SELECT * FROM suppliers WHERE user_id=? FOR UPDATE', [$user['id']]);
                        $count = row('SELECT COUNT(*) AS n FROM supplier_documents WHERE supplier_id=?', [$user['id']]);
                        if ((int)$count['n'] >= 10) throw new DomainException('You have reached the 10-document limit. Contact your administrator.');
                        execute('INSERT INTO supplier_documents (supplier_id, original_name, stored_name, mime_type, document_type) VALUES (?, ?, ?, ?, ?)', [$user['id'], mb_substr(basename($file['name']), 0, 190), $stored, $mime, $documentType]);
                        if ($supplier['verification_status'] === 'rejected') execute("UPDATE suppliers SET verification_status='pending', review_note='' WHERE user_id=?", [$user['id']]);
                        $market->audit((int)$user['id'], 'supplier.document', 'Business document submitted');
                        foreach (rows("SELECT id FROM users WHERE role='administrator' AND active=1") as $admin) $market->notify((int)$admin['id'], $user['name'] . ' submitted a supplier document for review.', 'verification');
                    });
                } catch (Throwable $e) { unlink($path); throw $e; }
                flash('Document submitted for administrator review.'); redirect('verification');
            default: throw new DomainException('Unknown action.');
        }
    } catch (DomainException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log((string)$e); $error = 'We could not save that change. Please refresh and try again.'; }
}
if (!$user && !in_array($page, ['login', 'register'], true)) redirect('login');
if ($user && in_array($page, ['login', 'register'], true)) redirect('dashboard');
if ($user) {
    $restricted = ['cart' => ['retailer'], 'verification' => ['supplier'], 'product-edit' => ['supplier'], 'products' => ['supplier','administrator'], 'suppliers' => ['administrator'], 'users' => ['administrator'], 'audit' => ['administrator'], 'reports' => ['supplier','administrator']];
    if (isset($restricted[$page]) && !in_array($user['role'], $restricted[$page], true)) { http_response_code(403); $page = 'forbidden'; }
    if (isset($_GET['document'])) {
        $doc = row('SELECT * FROM supplier_documents WHERE id=?', [(int)$_GET['document']]);
        if (!$doc || ($user['role'] !== 'administrator' && (int)$doc['supplier_id'] !== (int)$user['id'])) { http_response_code(404); exit('Document not found.'); }
        $path = ROOT . '/storage/documents/' . $doc['stored_name'];
        if (!is_file($path)) { http_response_code(404); exit('Document not found.'); }
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: attachment; filename="document-' . (int)$doc['id'] . '.' . ['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg'][$doc['mime_type']] . '"');
        header('Content-Length: ' . filesize($path)); readfile($path); exit;
    }
}
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
require ROOT . '/views/layout.php';
