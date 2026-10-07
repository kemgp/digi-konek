# Digi-Konek — TechILO

## Purpose

Digi-Konek is a business-to-business (B2B) electronics marketplace connecting retailers with verified suppliers. Retailers can browse and compare products, place wholesale orders, and review completed purchases. Suppliers manage listings, inventory, orders, and company profiles. Administrators oversee accounts, supplier verification, reviews, and marketplace settings.

## Members

**Team: TechILO**

| Member | Full name |
|---|---|
| 1 | Patiño, Keith Erwin Mikhail G. (Group Representative) |
| 2 | Peñas, Emmanuel Angelo |
| 3 | Balsomo, John Rey |
| 4 | Bolivar, Jaderick |
| 5 | Tan, Rainier Ryan |

Roster follows the TechILO Week 8 Project Planning Package.

## Technologies

- **Backend:** PHP 8.2+ with PDO prepared statements and PHP sessions.
- **Database:** MySQL 8+ or MariaDB 10.4+, using InnoDB and foreign keys.
- **Frontend:** HTML, CSS, JavaScript, and SVG assets.
- **Local environment:** XAMPP for PHP and MariaDB; PHP's built-in web server runs the application.
- **Required PHP extensions:** `pdo_mysql`, `mbstring`, and `fileinfo`. Automated tests additionally require `curl`, `proc_open`, and permission to create/drop test databases.
- No Composer or npm installation is required. The current application was tested with XAMPP PHP 8.2.4 and MariaDB 10.4.28 on macOS; Windows instructions have not been executed here.

## Quick start on the already-configured development Mac

The original development copy already has an initialized, isolated MariaDB database. Open Terminal and run:

```sh
cd /Users/keitherwinmikhail/Documents/ChatGPT/TechILO
sh bin/start-local.sh
```

Open **http://127.0.0.1:8086/** and keep Terminal open. Press **Control+C** when finished. The helper preserves existing data and stops only the database process it started itself. If it reports that a web server is already running, open the displayed address.

This helper is specific to the existing Mac setup: `.env` uses `/tmp/digikonek-dev.sock` and the database files are in `storage/test-mysql/`. It does not initialize a new installation. See [the startup and troubleshooting guide](docs/start-here.md).

## Fresh setup — Windows

These instructions assume XAMPP is installed at `C:\xampp` and the project is extracted to `C:\Projects\TechILO`. Adjust paths if yours differ.

1. Install XAMPP with PHP 8.2+ and the required extensions. Open **XAMPP Control Panel** and start **MySQL** and **Apache**. Apache is used here to access phpMyAdmin; the application will use PHP's built-in server.
2. Visit **http://localhost/phpmyadmin/**. Open the **SQL** tab and run:

   ```sql
   CREATE DATABASE digi_konek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Open **PowerShell** and enter the project directory:

   ```powershell
   cd C:\Projects\TechILO
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   notepad .env
   ```

4. Set the database values shown in **Database configuration** below. Save and close Notepad.
5. Create all tables, reference data, and demo accounts:

   ```powershell
   & "C:\xampp\php\php.exe" bin/setup.php --demo
   ```

6. Start the website from the same directory:

   ```powershell
   & "C:\xampp\php\php.exe" -d upload_max_filesize=5M -d post_max_size=8M -S 127.0.0.1:8086 -t public public/router.php
   ```

7. Open **http://127.0.0.1:8086/**. Keep PowerShell open while using the app. Press **Ctrl+C** to stop the website.

**Next time:** start MySQL in XAMPP, open PowerShell in the project folder, and repeat step 6. Do not repeat the database creation or setup commands.

## Fresh setup — macOS

These instructions assume native XAMPP is installed at `/Applications/XAMPP` and the project is in `~/Documents/ChatGPT/TechILO`. Adjust the project path if necessary.

1. Install XAMPP with PHP 8.2+ and the required extensions. Open its manager and start **MySQL Database** and **Apache Web Server**. Apache provides phpMyAdmin for the setup step.
2. Visit **http://localhost/phpmyadmin/**. Open the **SQL** tab and run:

   ```sql
   CREATE DATABASE digi_konek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Open **Terminal** and prepare the configuration:

   ```sh
   cd ~/Documents/ChatGPT/TechILO
   test -f .env || cp .env.example .env
   open -e .env
   ```

4. Set the values in **Database configuration** below and save the file. For this fresh installation, clear any old `DB_SOCKET=/tmp/digikonek-dev.sock` value so it connects to the XAMPP database instead.
5. Create all tables, reference data, and demo accounts:

   ```sh
   /Applications/XAMPP/xamppfiles/bin/php bin/setup.php --demo
   ```

6. Start the website:

   ```sh
   PHP_BINARY=/Applications/XAMPP/xamppfiles/bin/php sh bin/serve.sh
   ```

7. Open **http://127.0.0.1:8086/**. Keep Terminal open and press **Control+C** to stop the website.

**Next time:** start MySQL in XAMPP and repeat steps 3's `cd` command and 6. Setup is required only once. Use `bin/serve.sh` for this fresh XAMPP configuration; `bin/start-local.sh` automatically starts only the original isolated development database.

## Database configuration

For a fresh local XAMPP database, edit `.env` in the project's root folder:

```dotenv
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=digi_konek
DB_USER=root
DB_PASSWORD=
DB_SOCKET=
```

Set `DB_PASSWORD` to your MySQL password if one is configured, and adjust the port if XAMPP uses a different port. A populated `DB_SOCKET` overrides host and port; leave it empty for the TCP connection above. Environment variables override `.env`. Keep `.env` private; it is ignored by Git. In macOS Finder, **Command+Shift+.** shows hidden files such as `.env`.

`bin/setup.php --demo` installs the schema and demo data into an **empty database**; it refuses a database that already contains tables. Do not import `database/install.sql` and then run setup on the same database. The SQL file is an alternative table/reference-data installation method and does not include demo accounts.

To install without demo users/products, omit `--demo`, then run `bin/create-admin.php` with the same PHP executable to create an administrator interactively. Retailers and suppliers can then register through the website.

## Demo sign-in

After setup with `--demo`, all these accounts use **`DigiKonek!2026`** unless you change their passwords:

| Role | Email |
|---|---|
| Retailer | `retailer@demo.test` |
| Supplier | `supplier@demo.test` |
| Administrator | `admin@demo.test` |
| Second supplier | `partner@demo.test` |
| Unverified supplier | `pending@demo.test` |

Demo businesses, products, and verification decisions are fictional. Use the workflow below to create orders.

## Updates and troubleshooting

- **Database connection fails:** confirm MySQL is running and `.env` has the correct host, port, database, credentials, and socket setting.
- **Port 8086 is occupied:** on Windows, change `127.0.0.1:8086` to `127.0.0.1:8087` in the PHP server command. On macOS, run `PORT=8087 sh bin/serve.sh`. Open the matching browser address.
- **Missing PHP extension:** enable the required extension in the active PHP configuration, then restart the PHP server. `php -m` (using your PHP executable's full path) lists loaded extensions.
- **Uploads or sessions fail:** ensure PHP can write to `storage/`. The run commands set upload limits to 5 MB per file and 8 MB per request.
- **After an application update with schema changes:** keep MySQL running and run `bin/migrate.php` with your PHP executable. This preserves existing data; do not rerun fresh setup.

Windows migration command:

```powershell
& "C:\xampp\php\php.exe" bin/migrate.php
```

macOS migration command:

```sh
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php
```

The built-in server is for local development. If serving through Apache instead, configure a virtual host with **DocumentRoot set to this project's `public/` directory**. Subfolder URLs such as `/TechILO/public/` are unsupported because application URLs are root-relative. Keep `storage/` and `.env` outside the public web root; use HTTPS for a public deployment.

## Try the main workflow

1. Sign in as the retailer. Browse/search products, compare two to four listings, and open product details.
2. Add at least the stated minimum quantity. Review the cart and place an order. Items from different suppliers become separate orders.
3. Sign out and sign in as the matching supplier. Open the order, accept it, then mark it completed when business fulfillment is confirmed.
4. Return to the retailer account to inspect status updates and notifications. Supplier reports show completed order value.
5. Separately, sign in as the unverified supplier and upload a fictional PDF/JPEG/PNG business document. Sign in as administrator, download/review it, and approve or reject the supplier. Approved suppliers can create listings.

Rejection/cancellation can be tested on a separate pending order. Retailers can cancel only their own pending orders. Suppliers can reject their own pending orders or complete accepted orders. Administrators can inspect orders but cannot bypass this workflow.

## Implemented in this version

| Established module | Working behavior |
|---|---|
| Authentication | Registration, confirmation field, password hashing, role-based sessions, logout, inactivity expiration, failed-login throttling |
| Supplier Management | Shared User/profile identity, private document upload/download, administrator verification, rejection notes, resubmission |
| Product Catalog | Search, category/brand filters, price sorting, page navigation, details, comparison, supplier product create/edit/archive |
| Shopping Cart | Persistent per-retailer cart, minimum quantity and availability checks, item removal, review before checkout |
| Order Management | One order per supplier, immutable price/name snapshots, pending/accepted/rejected/completed/cancelled workflow |
| Inventory | Atomic checkout reservations, rejection/cancellation stock restoration, supplier available-stock editing |
| Notification | In-app order and verification updates, unread count, mark all read |
| Administration | Account activation/deactivation, supplier review, product visibility moderation |
| Reporting | Date-filtered counts and order values by status, print-friendly report |
| Audit | Recorded registration/login, product, verification, and order actions; administrator event list |

The interface includes responsive navigation, dashboards with actual database totals, empty states, validation messages, keyboard focus states, confirmation prompts, and original SVG category illustrations.

## Important implementation decisions

- **Identity:** `suppliers.user_id`, `retailers.user_id`, and `administrators.user_id` are both primary keys and foreign keys to `users.id`. `products.supplier_id` and order role columns reference those shared identities, not separate supplier/retailer IDs.
- **Stock reservation:** available stock is reduced when a pending order is submitted, to prevent competing orders from consuming the same units. Acceptance/completion does not deduct it again. Rejection/cancellation releases it exactly once. This is an implementation clarification to reconcile the earlier “update on confirmation” descriptions with concurrent ordering.
- **Transactions:** InnoDB row locks and READ COMMITTED isolation protect checkout and order transitions. Competing products are locked in ID order. Failed work rolls back; deadlock/connection failures return a retry message.
- **Price review:** checkout compares a server-calculated cart fingerprint with the review-page quote. Changed prices or cart contents require another review. Historical order lines retain the purchased name and price.
- **Money:** prices and totals use integer centavos, displayed in Philippine pesos. Order values do not represent recorded payments.
- **Access:** every protected action is authorized server-side. Form actions require CSRF tokens. Queries use bound parameters; rendered user text is escaped. Supplier documents are stored under random names outside `public/` and served through an ownership/admin check as downloads.
- **Status:** inactive users lose access on their next request. Public signup cannot create administrators. Product archiving retains order history.
- **Scope:** no payments, delivery/courier integrations, warehouse operations, accounting, lending, installation, repairs, or warranty processing.

## Tests

```sh
php tests/run.php
php tests/http.php
```

Both create uniquely named disposable databases on the configured database server and remove only those databases afterward. They do not modify the configured application schema. Test credentials need CREATE/DROP DATABASE permission; use a local development server. HTTP tests start and stop a separate PHP server and remove their uploaded fixtures.

- **45 service/database checks:** role restrictions, shared identities, minimum quantities, current prices, checkout rollback, order ownership, status transitions, stock restoration, notifications/audit, and two genuinely concurrent checkout processes.
- **53 HTTP checks:** CSRF, session cookie flags, registration/login/logout, protected routes, search, product creation, checkout through completion, document uploads/download access, supplier approval, deactivation, and private configuration isolation.
- Browser visual review completed for the desktop dashboard and compact layout. Full accessibility, load, and cross-browser testing remain pending.

## Structure

```text
app/                  Configuration, database helpers, domain services
bin/                  Schema setup, demo seeding, admin creation, local server
database/schema.sql   MySQL relational schema and constraints
public/               Only public web root; controller and CSS/JS assets
views/                Role-aware layouts and page templates
storage/              Ignored runtime sessions, private documents, local DB
tests/                Disposable-database service and HTTP integration tests
docs/                 Development plan and validation record
```

## Next iterations

This is a working first development baseline, not a completed production release. Remaining work includes actual product photo uploads, direct cart quantity editing, additional supplier profile enhancements, password recovery/email verification, broader pagination/export, SRS reconciliation, accessibility and load testing, and deployment/backup operations. See `docs/development-plan.md` for the proposed six-week plan. Estimates are planning assumptions, not recorded team contributions or instructor approvals.

Technical references: [PHP password hashing](https://www.php.net/passwords), [PDO transactions](https://www.php.net/manual/en/pdo.transactions.php), [PHP session security](https://www.php.net/manual/en/features.session.security.management.php).

## Profiles, reviews, and individual notifications

Added September 14, 2026:

- **My profile** (navigation or avatar): edit full name, company name, and email. Changing email requires the current password. Company names reuse the existing supplier/retailer business records; administrators also have a company field.
- **Orders → Completed → View → Review your purchase**: retailers can rate products and sellers from 1–5 and write feedback. Only the buyer of a completed order may review its products or seller. One review per retailer/product or retailer/seller is allowed; subsequent saves edit it.
- **Product details** display product feedback. The seller link opens a company profile with seller feedback and aggregate rating. Reviews display the retailer name and company.
- **Notifications** retain “Mark all as read” and now include “Mark as read” on each unread notification. Users can mark only their own notifications.

For an existing installation, run `php bin/migrate.php` once (safe to repeat). This additive migration preserves existing data. Fresh setup already includes the new schema. The local development database has been migrated.

Updated test totals: **65 service/database checks + 63 HTTP checks = 128 passing checks**. New coverage includes purchase-bound review permissions, duplicate review updates, escaped review text, profile/email edits, administrator company names, and individual notification ownership.

## Settings

Open **Settings** in the navigation. Preferences are saved per account and apply after selecting **Save settings**.

| Role | Available settings |
|---|---|
| All accounts | Light/dark theme; comfortable/compact density; in-app order, review, and verification notification preferences; change password; sign out other sessions |
| Retailers | Default catalog sorting and multiple preferred categories |
| Suppliers | Low-stock threshold and alerts; default minimum quantity for new products; public company description, contact email/phone, and business hours |
| Administrators | Add/rename/remove unused categories and brands; configure required supplier document types |

Password changes and session revocation require the current password. Other sessions are invalidated on their next request; the caller remains signed in. A password change also revokes other sessions. Password recovery is still separate and remains unimplemented.

Notification preferences control new in-app alerts, not existing notification history. Suppliers receive feedback alerts, reviewers receive save confirmations, and administrators receive order and review activity alerts according to their preferences. Stock alerts fire at or below the configured available-stock threshold once until replenishment rearms the alert; enabling stock alerts or changing the threshold rechecks current inventory.

Preferred categories filter the catalog when first opened. Selecting **All categories** overrides that preference for the current browse. Deleted unused categories are ignored in saved preferences. Minimum quantity defaults affect new product forms; existing product quantities are unchanged.

Public company details appear on the seller profile. Supplier contact details are optional and distinct from the private sign-in email.

Document requirements accept 1–10 types. Suppliers select a type during upload; approval requires every configured type. Existing documents retain their type, and existing approvals are not revoked automatically. The initial type is **Business registration**. Taxonomy entries used by products cannot be deleted.

Run `php bin/migrate.php` on an existing installation for the settings schema. This migration has already been applied to the local preview. Current validation: **98 service/database checks and 82 HTTP checks**, including real password changes and two-session invalidation in disposable test accounts. Actual demo account passwords were not changed by these tests.
