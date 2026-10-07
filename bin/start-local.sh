#!/bin/sh
# Start this Mac's existing isolated Digi-Konek database and local PHP preview.
set -eu
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
XAMPP_DIR=/Applications/XAMPP/xamppfiles
PHP_EXEC=${PHP_BINARY:-$XAMPP_DIR/bin/php}
PORT=${PORT:-8086}
case "$PORT" in ''|*[!0-9]*) echo 'PORT must be a number.' >&2; exit 1;; esac
if [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then echo 'Use a port from 1024 to 65535.' >&2; exit 1; fi
if [ ! -x "$PHP_EXEC" ]; then echo 'Install XAMPP or set PHP_BINARY to your PHP executable.' >&2; exit 1; fi
cd "$PROJECT_DIR"
if [ ! -f .env ]; then echo 'No .env found. Follow the Fresh setup section in README.md first.' >&2; exit 1; fi
DB_PID=
cleanup() {
    if [ -n "$DB_PID" ] && kill -0 "$DB_PID" 2>/dev/null; then
        echo 'Stopping the database started by this command…'
        kill "$DB_PID" 2>/dev/null || true
        wait "$DB_PID" 2>/dev/null || true
    fi
}
trap cleanup 0
trap 'exit 130' INT
trap 'exit 143' TERM
database_ready() {
    "$PHP_EXEC" -r 'require "app/bootstrap.php"; try { db()->query("SELECT 1"); } catch (Throwable $e) { exit(1); }' 2>/dev/null
}
if ! database_ready; then
    SOCKET=$("$PHP_EXEC" -r 'require "app/bootstrap.php"; echo getenv("DB_SOCKET") ?: "";')
    if [ "$SOCKET" != /tmp/digikonek-dev.sock ] || [ ! -d storage/test-mysql/mysql ]; then
        echo 'Start the database configured in .env first. Automatic startup is only for the existing isolated Digi-Konek database.' >&2
        exit 1
    fi
    echo 'Starting the isolated Digi-Konek database…'
    "$XAMPP_DIR/sbin/mysqld" --no-defaults --basedir="$XAMPP_DIR" \
      --datadir="$PROJECT_DIR/storage/test-mysql" --socket="$SOCKET" \
      --pid-file="$PROJECT_DIR/storage/test-mysql/server.pid" --skip-networking \
      --log-error="$PROJECT_DIR/storage/test-mysql/server.log" &
    DB_PID=$!
    ATTEMPT=0
    until database_ready; do
        ATTEMPT=$((ATTEMPT + 1))
        if [ "$ATTEMPT" -ge 15 ] || ! kill -0 "$DB_PID" 2>/dev/null; then
            echo 'Database startup failed. See storage/test-mysql/server.log. Check .env and whether another database process already uses this directory.' >&2
            exit 1
        fi
        sleep 1
    done
fi
# An occupied HTTP port should never be stopped automatically.
if curl --silent --max-time 2 --output /dev/null "http://127.0.0.1:$PORT/"; then
    echo "A web server is already running at http://127.0.0.1:$PORT/ . Open it, or use PORT=8087 sh bin/start-local.sh."
    if [ -n "$DB_PID" ]; then
        echo 'This command started the database. Keep this terminal open; press Ctrl+C to stop it.'
        wait "$DB_PID"
    fi
    exit 0
fi
echo "Open http://127.0.0.1:$PORT/ in your browser. Keep this terminal open; press Ctrl+C to stop."
PHP_BINARY="$PHP_EXEC" PORT="$PORT" sh bin/serve.sh
