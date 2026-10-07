<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
date_default_timezone_set('Asia/Manila');
if (is_file(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value));
    }
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $name = getenv('DB_NAME') ?: 'digi_konek';
        $socket = getenv('DB_SOCKET');
        $dsn = $socket ? "mysql:unix_socket=$socket;dbname=$name;charset=utf8mb4" : 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ";dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
    return $pdo;
}
function rows(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function row(string $sql, array $params = []): ?array { return rows($sql, $params)[0] ?? null; }
function execute(string $sql, array $params = []): void { $stmt = db()->prepare($sql); $stmt->execute($params); }
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(int $cents): string { return '₱' . number_format($cents / 100, 2); }
function url(string $page, array $params = []): string { return '/?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params), true, 303); exit; }
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function flash(string $text, string $type = 'success'): void { $_SESSION['flash'] = ['text' => $text, 'type' => $type]; }
function currentUser(): ?array { return isset($_SESSION['user_id']) ? row('SELECT u.id, u.name, u.email, u.role, u.active, u.session_version, COALESCE(s.business_name,r.business_name,a.business_name,\'\') AS business_name FROM users u LEFT JOIN suppliers s ON s.user_id=u.id LEFT JOIN retailers r ON r.user_id=u.id LEFT JOIN administrators a ON a.user_id=u.id WHERE u.id = ? AND u.active = 1 AND u.session_version = ?', [$_SESSION['user_id'], $_SESSION['session_version'] ?? 0]) : null; }
function requireRole(array $user, string ...$roles): void { if (!in_array($user['role'], $roles, true)) throw new DomainException('You do not have permission for this action.'); }
function positiveInt(mixed $v, int $max = 100000): int {
    $n = filter_var($v, FILTER_VALIDATE_INT);
    if ($n === false || $n < 1 || $n > $max) throw new DomainException('Enter a valid positive quantity.');
    return $n;
}
function field(array $input, string $key, int $max = 255): string {
    $v = trim((string)($input[$key] ?? ''));
    if ($v === '' || mb_strlen($v) > $max) throw new DomainException(ucfirst(str_replace('_', ' ', $key)) . " is required (maximum $max characters).");
    return $v;
}
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Marketplace.php';
