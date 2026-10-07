#!/bin/sh
set -eu
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
if [ -n "${PHP_BINARY:-}" ]; then
  PHP_EXEC=$PHP_BINARY
elif command -v php >/dev/null 2>&1; then
  PHP_EXEC=$(command -v php)
elif [ -x /Applications/XAMPP/xamppfiles/bin/php ]; then
  PHP_EXEC=/Applications/XAMPP/xamppfiles/bin/php
else
  echo 'PHP 8.2+ is required. Set PHP_BINARY to its executable path.' >&2
  exit 1
fi
cd "$PROJECT_DIR"
exec "$PHP_EXEC" -d upload_max_filesize=5M -d post_max_size=8M -S "127.0.0.1:${PORT:-8086}" -t public public/router.php
