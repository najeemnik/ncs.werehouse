# Installation, upgrade and integrations — 2.3.7

Restricted hosts: [browser installation guide](SHARED_HOST_DA.md), [protected storage assumptions and later migration](SHARED_HOST_STORAGE.md). The default outside-root deployment below remains unchanged; protected in-root storage requires an explicit individual profile and owner-authorized HTTP-denial checks.

## Hosting and private storage

Use PHP 8.3+, MySQL/MariaDB with transactional InnoDB tables, PDO MySQL, mbstring, Sodium, OpenSSL, zlib, fileinfo and cURL. SQLite is an explicit local-development alternative, never a fallback after MySQL failure. GD is required for logo re-encoding. `public/` is the web root. Keep `bin/`, `tests/`, `docs/` and `var/` outside it. Do not use PHP's development server publicly.

Apache must apply the supplied `.htaccess` rules. On Nginx implement equivalent denials for `config`, `migrations`, `sessions`, internal `includes` except the two intended preference/lock endpoints, dotfiles, database/backup/key/log files and executable scripts in uploads. Disable directory listings and PHP execution in uploads. Use HTTPS, `display_errors=Off`, `log_errors=On`, a private PHP error log, suitable upload/post limits, at least 1000 input variables, and synchronized server time. Retain TLS verification for outbound requests. Trust forwarded HTTPS headers only behind a known proxy, with `NIKXOR_TRUST_PROXY=1`.

Set private writable absolute locations when the host requires them:

- `NIKXOR_RUNTIME_DIR`: encrypted integration settings and installation/configuration state; default `var/` beside `public/`.
- `NIKXOR_PRIVATE_DIR`: TOTP material, security epoch and encrypted backups; default `var/private/`.
- `NIKXOR_SESSION_DIR`: sessions; default `var/sessions/`.

Directories should be mode 0700, private config/key files 0600, owned by the PHP account. The CLI scheduler must use the **same paths and account** as web PHP. Encryption keys remain on this host; encryption does not protect against an attacker controlling both code and keys. Preserve separate protected copies of private configuration, TOTP seeds and backup passphrases for disaster recovery.

## Fresh installation

1. Create an empty MySQL database and dedicated database user on the host. Grant the schema/migration and application privileges needed for installation; do not use the database root account. Restrict database network access and use the host's TLS configuration for remote DB connections.
2. Upload the source with the web root set to `public/`. Prepare private storage. Run `php bin/prepare-install.php` through the host terminal. It creates `var/install-token.key` without printing it. If terminal access is unavailable, create that private file through the protected host file manager with a cryptographically random 64-character hexadecimal token. Never create it under `public/` or send it in a URL.
3. Open `/install.php` over HTTPS. Enter the private token, DB connection fields, central username, password of at least 12 characters and confirmation. Generate an authenticator secret, enroll it in your authenticator app and enter its current code. Store recovery material offline.
4. Successful installation creates one central account, removes the token and seals installation. Existing accounts are never replaced. Installation rate limits and CSRF protection apply. `NIKXOR_ALLOW_LOCAL_INSTALL=1` is solely for isolated loopback tests; keep it unset on the host.
5. Sign in through `/admin/office_2.php`, complete the second factor, then open **Central setup**. Optional AI, Google, notification and developer email settings may be entered later. Blank secret fields preserve saved values; saved secret values are not redisplayed. Environment values override the saved values.
6. Create the business and owner in **Owners, employees & permissions**. Select allowed services/actions/quotas. The owner then adds branches and assigned employees within those central limits. Temporary passwords require first-login rotation. Enabling a service does not waive its per-action checks.
7. Declare and verify opening balances for cash/bank and the remaining accounting accounts before interpreting the books as complete. Entering an opening balance is a financial action, not an estimate of historical money.

An alternative CLI central-account creation tool remains at `bin/create-admin.php`, using privately supplied `NIKXOR_ADMIN_USERNAME`/`NIKXOR_ADMIN_PASSWORD`; it refuses to replace an existing central administrator. CLI enrollment requires the matching Base32 seed in `NIKXOR_CENTRAL_TOTP_<USER_ID>` or a private `totp-<USER_ID>.key`. Never put credentials in shell history or saved scripts.

## Existing installations

Test a copy of the **complete host database** first. Take and verify independent DB and uploaded-file backups before any migration. The uploaded SQLite file had no trading data; it does not establish that a production backup is complete. This ZIP is not an instruction to overwrite a live host blindly.

Configure MySQL through `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, or a private PHP array file at `var/database.php` with `HOST`, `PORT`, `NAME`, `USER`, `PASS`. `NIKXOR_CONFIG_FILE` can select a different private file outside the root. Do not invoke the fresh installer for an existing DB. Authenticate using existing accounts and use Central setup for optional integrations. SQLite requires explicit `NIKXOR_DB_DRIVER=sqlite` and a private absolute `NIKXOR_SQLITE_PATH`.

Migrations 007–019 preserve source business records. Existing shops remain separate businesses rather than guessing common ownership. Verify owners, branch memberships and policies. Historical noncentral accounts without a shop are attached to original shop 1; central users remain separate. Known demonstration passwords are quarantined: rotate privately using `bin/rotate-account.php` and `NIKXOR_RESET_USERNAME`/`NIKXOR_RESET_PASSWORD`.

Migration 009 rebuilds SQLite branch uniqueness transactionally. Migration 010 adds accounting and partial returns and rebuilds SQLite return uniqueness; MySQL DDL needs staging verification and an independent rollback backup. Migration 011 reserves new transfer requests; old pending requests are marked unreserved and recheck availability at receipt. Migration 012 adds retry-safe fund-account request keys. Historical movements, profits and cash are not silently reconstructed. Legacy debt/invoice settlement creates an explicit balance-opening adjustment, not fictional historical receipts.

Version 2.3.7 needs no database migration. It adds a shared presentation registry for grouped navigation/dashboard actions, independently permissioned dashboard panels and a friendly locked-service activation page. Update the supplied application files while preserving `config/hosting.php`, private storage, `.htaccess`, uploaded branding and the database. Existing policies remain valid; new dashboard-panel policies inherit their current defaults until changed. Review each central/owner dashboard group after updating.

Version 2.3.6 adds migration 024 for hashed, browser-bound trusted-device tokens with fixed 30-day expiry. It does not replace users, passwords, Authenticator seeds or business records. Tenant users can enroll through Settings → Account security when permitted; employee access requires both central and owner approval. Office 2 → Users → Reset Authenticator removes a tenant user's lost-phone seed and trusted devices and revokes sessions. Existing databases must use the normal update path, not the fresh installer.

Version 2.3.5 needs no additional migration beyond 022–023. Use **Owners, employees & permissions** for tenant account creation. Office 2 → Users → Business & assigned branch repairs an existing unassigned account after the correct branch is verified. Central quick presets establish ordinary tenant access; employee AI still requires separate owner approval. **Read-only user preview** is optional and is never the default login destination. It blocks POST changes and quota usage; use its exit button to return to the real central workspace.

Migrations 022–023 run automatically through the normal application bootstrap after the source update. Migration 022 adds nullable business contact/subscription fields, status timestamps and zero-as-unlimited business-wide branch/staff ceilings. Migration 023 adds nullable POS request key/content hash columns and a unique branch/key index. Existing rows keep NULL request keys and existing financial values are not rewritten. The DB account needs ALTER/INDEX privileges for this one-time update. Confirm both versions appear in `schema_migrations`, then test one sale and a browser refresh/retry before resuming normal work.

Backups currently require the same database driver and exact schema. Keep the matching application version and host DB backup for recovery across upgrades. Earlier sequential migrations were tested on MariaDB; the new 022/023 checks in this patch were SQLite/WASM only. Simultaneous first deployment and large live datasets have not been load-tested, so stage the MySQL ALTER/INDEX operations and retain an independent recovery backup.

## AI

Central setup accepts `NIKXOR_GEMINI_API_KEY`, model (default `gemini-2.5-flash`) and AI state. Permit HTTPS to `generativelanguage.googleapis.com`. Provider free-tier availability depends on account, region, model and current quotas; it is not guaranteed or unlimited.

Employee use requires both central user policy and owner employee policy. Business and owner restrictions remain ceilings. The advisor sends authorized aggregate totals for the active branch and last 30 days, not customer identities, passwords or keys. Advice is escaped text and read-only; it never executes financial changes. The UI discloses the data transfer. Request allowances count provider attempts, including unsuccessful calls, not tokens. Other mutation allowances count authorized attempts after CSRF validation.

No live provider call was validated: enter your own key in private setup and verify the provider before launch.

## Google backup and Gmail notifications

In Central setup enter the HTTPS application address, Google OAuth client ID/secret, a backup passphrase of at least 12 characters and local retention count (2–365). Register the callback shown on the page in your Google application. Use **Connect Google & create backup folder** and authorize the intended Google/Gmail account. The connection requests `drive.file` scope and offline access. Browser-bound expiring state, central account version and one-use validation protect the callback; refresh tokens remain encrypted privately. Google app consent/test-user/verification requirements depend on your account. An expiring test-mode refresh token needs reconnection.

Advanced manual configuration also supports a refresh token and Drive folder ID. Fixed HTTPS destinations are `accounts.google.com`, `oauth2.googleapis.com`, `www.googleapis.com`. Preserve CA trust. Redact OAuth authorization codes from web-server access logs. No actual OAuth consent, Drive upload or mail delivery was validated without real credentials.

Schedule the private command once daily using hosting Cron Jobs, with absolute paths and the same PHP account/private paths as the web application:

```
php /absolute/application/bin/google-backup.php
```

Do not place credentials in the Cron command. The job creates and verifies a local encrypted `.nxb`, uploads it to Drive, applies **local** scheduled-copy retention after confirmed upload, and optionally requests an email notification. Configure notification address and the host's mail sender in Central setup. PHP `mail()` requires a correctly configured host mail service; acceptance by `mail()` is not proof of Gmail inbox delivery. The encrypted backup is in Drive associated with the Google account, not attached to Gmail. Remote Drive retention and scheduler installation are not automatic.

Backups contain database plus supported PNG/JPEG/WebP/GIF/ICO/PDF uploads, with paths, MIME checks and SHA-256 integrity. Uploads are limited to 8 MB combined, plaintext snapshot to 64 MB and encrypted restore file to 16 MB. Larger datasets need host-managed backups. Credentials, private setup/TOTP keys and application code require separate protected recovery copies. Restore is central-only, takes a recovery copy first, pauses application requests, compensates file changes on DB failure and invalidates sessions. Practice restore on staging, including interrupted recovery, before relying on it.

## Completed financial workflows and operating boundaries

Partial/full sales and purchase returns preserve the original invoices and allocate outstanding credit before refund balances. Actual payments and refunds remain separate cash/bank documents. The reviewed legacy purchase-opening panel records only the declared payable; the verified historical paid amount never creates fictional old cash. Inventory and other opening accounts require separate review.

Partnership settlement accounts record reviewed opening obligations, actual funding, principal/profit payments and overpayment recovery. New known same-currency profit changes allocate only the change after the reviewed baseline. Returning goods after distributions requires approval bound to the current contract/balance snapshot; overpaid profit becomes a receivable, not an automatic cash recall. Purchase returns need their own ownership review and never repay partner principal automatically. Fully settled contract goods can transfer only after an explicit release agreement; destination goods become business-owned. An unpaid or changed obligation blocks release. Closing requires all balances and remaining stock to be zero.

These are repayable partner-funding and agreed profit-allocation accounts. They do not certify any legal or religious contract model or infer loss-sharing terms. Inventory variances and mixed-currency profit require a verified profit/obligation review. A legacy general-partner stock position without a reviewed contract cannot be silently reclassified as business-owned. Manual noncash journals remain available for approved opening and allocation adjustments. Reviewed sharing terms cannot be rewritten through the old contract edit form.

The currency-exchange screen records actual amounts in each currency, exchanges cash/bank funds, settles a receivable/payable in another currency, or allocates an existing foreign receipt without collecting cash again. Every currency keeps balanced entries and an exchange-clearing position. Market valuation, automatic realized/unrealized exchange gains, and a consolidated converted profit figure are not inferred. Approved journals can use Exchange gains/losses accounts for separately verified valuations.

Correction documents cancel mistaken open-period expenses, verified recoverable stock losses, capital entries and standalone debt entries while retaining the originals and audit trail. They include compensating cash records and sufficient-funds checks. Operational reports omit cancelled mistaken entries; the ledger retains original and correction dates. Closed-period sources are protected. Returned sales/purchases use their own return flows. An FX-linked debt cancellation and a general-partner ownership dispute require reviewed adjustment documents rather than an automatic cancellation. This protects transactions whose legal/cash consequences cannot be inferred.

Primary customer/supplier/purchase/invoice/stock/debt/expense/loss/partner/capital lists and journal/payment/activity/movement/return/correction histories have page controls. Full summary totals remain independent of the displayed page. Legacy contract-currency summaries are calculated before their display page is selected. Accessible column sorting changes the displayed page; server controls select ordering across pages. Large datasets still need host/load testing.

## Migration additions in 2.2

Migration 013 preserves old full purchase returns and enables itemized partial returns. Migrations 014–017 add explicit FX, corrections, reviewed partner obligations/returns/profit evidence, correction-aware report sources and legacy purchase openings. In 2.3.2 these sources are parameter-compatible derived queries; CREATE VIEW is no longer required. Existing views are left intact but are not used by the app. Migration 015 validates both sources before recording completion, allowing a restricted account stopped at 014 to resume through 021 without replacing users, passwords or financial amounts. Table/index/ALTER and normal application read/write privileges remain required. Migration 018 adds branch/account/history indexes. New tables explicitly use InnoDB and utf8mb4_unicode_ci. Migration 019 aligns only known currency-code columns from earlier release tables, resolving differing default MySQL/MariaDB collations without revaluing or rewriting monetary amounts. Test the complete upgrade on staging; large ALTER operations need a host maintenance window and independent recovery backup.

Mutation and view allowances charge once per authorized HTTP request after permission checks (and CSRF checks for POST). Child service limits are separate from the already charged route allowance. AI allowances count provider attempts rather than page visits or tokens. Central AI resets preserve request history and reset the applicable user counters; business/owner ceilings still apply. A permission form truncated by hosting input limits is rejected without saving its partial matrix. Hidden profit preview also removes purchase cost data from the POS payload.

## About / contact

The original About page supplies **Ahmad Najeem Nik**, +93 783 788 278. No developer email was found in the checked source/recognized database email settings. Enter the correct `NIKXOR_SUPPORT_EMAIL` in Central setup; no address is invented. Review the displayed contact before publication.


## Invoice migration additions in 2.3

Migration 020 adds `business_settings`, `sales.invoice_snapshot` and `sale_items.invoice_fields`. It seeds only the original shop's legacy contact defaults into its own business; unrelated businesses cannot inherit that contact identity. Branch overrides take precedence over business defaults. Scoped automatic printing is now included in lookup/write policy. Migration 021 adds a per-invoice random verification nonce. Neither migration changes historical monetary values.

New invoice prints use a unified localized renderer for HTML/PDF/HTML-based Word export. Print design and custom line values are captured on a new sale. Legacy invoices without a snapshot continue to use current branding. The public verification endpoint exposes only an allowlisted summary and validates an HMAC signature using the installation's private `var/settings.key`. Protect and back up that key separately from database-only backups; replacing it invalidates old verification signatures. Set a stable HTTPS application origin during installation. Public verification needs no account, exposes no customer identity or custom item fields, and is rate-limited. It verifies the business record rather than independently verifying bank settlement.

The fresh-install distributable intentionally has no `var/`, database, credential, account, session or tenant-upload data. Use a new empty DB/private runtime for a clean install. A private out-of-band token prevents first-visitor installation takeover; existing accounts and `installed.lock` block reinstallation. Source-level and real-browser tests do not replace checking PHP modules, HTTPS, document root, private-directory permissions, mail, Google/AI connectivity and recovery on the actual host.
