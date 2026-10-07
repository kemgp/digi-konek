<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
try {
    $existing = rows('SHOW TABLES');
    if ($existing) throw new RuntimeException('The database is not empty. Setup never overwrites existing tables. Use an empty database.');
    db()->exec(file_get_contents(ROOT . '/database/schema.sql'));
    foreach (['Computing','Peripherals','Networking','Storage','Audio','Components'] as $name) execute('INSERT INTO categories (name) VALUES (?)', [$name]);
    foreach (['Lenovo','Logitech','TP-Link','Kingston','JBL','Intel','Samsung','Acer'] as $name) execute('INSERT INTO brands (name) VALUES (?)', [$name]);
    echo "Schema and reference data installed.\n";
    if (!in_array('--demo', $argv, true)) {
        echo "Next: php bin/create-admin.php (interactive). Use bin/seed.php only for a demo database.\n";
    } else { require __DIR__ . '/seed.php'; }
} catch (Throwable $e) { fwrite(STDERR, "Setup failed: " . $e->getMessage() . "\n"); exit(1); }
