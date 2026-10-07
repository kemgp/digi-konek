# Initial validation record

Date: September 13, 2026.

Environment: macOS; XAMPP PHP 8.2.4; isolated MariaDB 10.4.28; database engine InnoDB. The new application/test schemas use the project's own data directory, not the existing XAMPP database directory.

## Automated

- `tests/run.php`: **45 passed**, including separate-process concurrent checkout of the same final stock, rejected/duplicate cancellation, stale-price checkout, cross-account order/product mutations, supplier verification, and notification/audit persistence.
- `tests/http.php`: **53 passed**, using a separate local server and disposable schema. Checks cover actual form submissions, registration, session headers, CSRF, role-gated pages, search, product creation, checkout/acceptance/completion, document MIME restrictions, document ownership, administrator review, and invalidating active sessions after account deactivation.
- PHP syntax checked across application, templates, scripts, and tests.
- Git whitespace/error-marker check performed.

Tests do not access real suppliers, send email, process payments, or modify the preview's data. Fixtures and temporary test schemas are removed by the test scripts.

## Visual

- Desktop retailer dashboard reviewed at 1280 × 720: navigation, summary cards, hero, and empty-order state rendered without visible overlap.
- Compact layout inspected at approximately 620 px wide: horizontal navigation and content adapt to the narrower panel.
- Found and fixed a compact-layout sign-out issue caused by hiding the sidebar footer. A dedicated compact-header sign-out action was added.

## Limits

This record is development evidence, not instructor feedback or stakeholder acceptance. Full SRS traceability, real business verification, comprehensive accessibility review, multi-browser testing, deployment rehearsal, and performance/load evaluation remain outstanding.

## September 14 feature update

Added product/seller reviews, editable account/company profiles, and individual notification read controls. Applied the additive migration to the local preview without replacing records. All **65 service/database** and **63 HTTP** checks passed. Visually verified the profile layout and the review controls on existing completed order DK-00007. Earlier validation totals above describe the original baseline.

## Administrator sign-out layout fix

The longer administrator navigation could push its footer outside shorter desktop viewports. Sign-out is now available in the sticky header at every width, while desktop navigation scrolls independently and the sidebar footer remains reachable. Verified unobstructed sign-out controls at 1366×600, 1024×600, 768×500, 681×450, 680×500, 375×667, and 320×568. Clicking the header control at 320×568 successfully returned to the login screen. Temporary viewport overrides were reset after testing.

## Settings implementation — September 14

Implemented account appearance/notification preferences, password changes and other-session revocation, retailer catalog defaults, supplier inventory defaults and public business details, and administrator taxonomy/document configuration. Applied additive migrations without replacing existing application data.

Validation: **98 service/database checks passed; 82 HTTP checks passed.** Coverage includes low-stock alert suppression/rearming, notification switches, category constraints, required-document approval checks, catalog defaults, public supplier data, password replacement, and independent browser-session invalidation. PHP syntax and whitespace checks passed. Desktop dark/compact settings and mobile layout were visually reviewed; the demo administrator's original light/comfortable appearance was restored afterward. Password testing used disposable accounts only.
