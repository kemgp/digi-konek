# Start Digi-Konek on your Mac

Your existing project is already installed and its database is initialized. Keep XAMPP installed at `/Applications/XAMPP`. You do not need npm, Composer, or the XAMPP Apache service to use this startup method.

## Each time you want to use it

1. Open **Terminal** from Applications → Utilities, or use Spotlight and search for Terminal.
2. Paste this command and press Return:

   ```sh
   cd /Users/keitherwinmikhail/Documents/ChatGPT/TechILO
   ```

3. Start the project:

   ```sh
   sh bin/start-local.sh
   ```

   The script checks the configured database. If needed, it starts the existing isolated MariaDB database in `storage/test-mysql/`, using `/tmp/digikonek-dev.sock`. It then starts PHP on port 8086. It never installs or recreates database tables.

4. Open **http://127.0.0.1:8086/** in Safari, Chrome, or another browser on this Mac.
5. Sign in using a demo account. Unless you changed it yourself, the password is **DigiKonek!2026**:

   | Account | Email |
   |---|---|
   | Retailer | retailer@demo.test |
   | Supplier | supplier@demo.test |
   | Administrator | admin@demo.test |
   | Second supplier | partner@demo.test |
   | Unverified supplier | pending@demo.test |

6. Keep Terminal open while testing. When finished, press **Control+C** in the terminal running the startup command. The script stops any database process it started itself; it leaves a database that was already running alone. Closing the website tab alone does not stop the servers.

Your saved accounts, orders, reviews, and settings remain in the local database for your next session. The website is only available on this Mac through this loopback address.

## What the messages mean

- **A web server is already running:** open the displayed address. If it is Digi-Konek, you are ready. This can happen while the development server started earlier is still running.
- **Something else uses port 8086:** use `PORT=8087 sh bin/start-local.sh`, then open http://127.0.0.1:8087/.
- **No .env found:** this is not a complete existing setup. Follow the Fresh setup section in the project README; do not overwrite a working `.env`.
- **Database startup failed:** inspect `storage/test-mysql/server.log`. Another process may already own the data directory/socket, or the credentials may not match `.env`. Do not delete database files to resolve this.
- **Install XAMPP or set PHP_BINARY:** the startup script cannot find the PHP executable at `/Applications/XAMPP/xamppfiles/bin/php`. Restore your XAMPP installation or supply your PHP executable using the `PHP_BINARY` environment variable.
- **Browser cannot connect:** confirm the startup terminal is still open and the URL uses the displayed port.
- **Old colors remain:** reload the page; if needed use Command+Shift+R in Chrome for a full refresh.

## After future application updates

If an update adds database columns or tables, run this while the database is running:

```sh
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php
```

You do not need to run this on every start. Current migrations have already been applied. Do not rerun `bin/setup.php --demo` on your existing database; that command is for a fresh empty installation.

## Blue palette

| Purpose | Light theme | Dark theme |
|---|---|---|
| Primary action | `#1D4ED8` royal blue | `#2563EB` bright blue |
| Navy brand / deep background | `#123458` | `#0B1729` |
| Page background | `#F4F7FB` | `#0B1729` |
| Cards and panels | `#FFFFFF` | `#14253D` |
| Main text | `#102A43` | `#E5EDF8` |
| Muted text | `#64748B` | `#A9BBD2` |
| Borders | `#DBE4F0` | `#33465F` |
| Accent | `#BFDBFE` pale blue | `#93C5FD` sky blue |

Green success, amber pending, and red rejection indicators retain their status meaning. Theme selection is under **Settings → Appearance → Theme**.
