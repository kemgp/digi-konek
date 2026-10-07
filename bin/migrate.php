<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
try {
    // Additive and repeatable: existing accounts, products, and orders are preserved.
    if (!row("SHOW COLUMNS FROM administrators LIKE 'business_name'")) {
        execute("ALTER TABLE administrators ADD COLUMN business_name VARCHAR(160) NOT NULL DEFAULT ''");
    }
    db()->exec(file_get_contents(ROOT . '/database/migrations/002_reviews.sql'));
    foreach ([['users','session_version','INT UNSIGNED NOT NULL DEFAULT 0'], ['inventory','low_stock_notified','BOOLEAN NOT NULL DEFAULT 0'], ['supplier_documents','document_type',"VARCHAR(80) NOT NULL DEFAULT 'Business registration'"]] as [$table,$column,$definition]) {
        if (!row("SHOW COLUMNS FROM $table LIKE '$column'")) execute("ALTER TABLE $table ADD COLUMN $column $definition");
    }
    db()->exec(file_get_contents(ROOT . '/database/migrations/003_settings.sql'));
    echo "All migrations applied. Existing data preserved.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
