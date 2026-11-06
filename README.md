# Nik Smart Count — Warehousing

Multi-business PHP application developed from the user-supplied `werehouse.zip`. Author: **Ahmad Najeem Nik**, +93 783 788 278. Version 2.3.8 gives Office 2 the complete grouped domain-permission matrix, keeps legacy access synchronized, and substantially improves typography and readable card/form layouts. Version 2.3.7 unifies sidebar, dashboard and Office 2 service controls, adds independently controlled dashboard panels, grouped navigation, friendly locked-service contact pages and a responsive card-based mobile POS. Version 2.3.6 adds optional Google Authenticator enrollment for tenant owners and employees, central/owner-controlled employee access, 30-day browser-bound trusted devices and audited lost-phone recovery. Central Office 2 keeps mandatory two-step verification. Version 2.3.5 guided permissions and read-only preview, plus version 2.3.4 business controls and retry-safe sales, remain included. Uploaded databases, live credentials and sessions are excluded.

## Implemented

- Business invoice defaults with branch exceptions, explicit replace-all confirmation, future-branch inheritance and optimistic editing checks.
- Declarative invoice designer with drag/keyboard section and column ordering, widths, font/spacing, A5/A4/80 mm, up to 6 multilingual custom sale-line fields and 4 text blocks. New invoices preserve their design snapshot.
- Signed QR verification for an actual recorded invoice on the configured HTTPS origin, with live payment/return status and no public customer/item/cost data. Samples never impersonate verified invoices.
- Correctly nested POS checkout form, retained input after validation failure, stale-template rejection and consistent assigned-employee limits across administration paths.

- Compact responsive header: one row on phones and desktop, mobile tools disclosure, accessible language controls, working account-currency navigation and permission-aware quick sale.

- Business → branches → assigned employees; central Office 2 administration separated from owners. Central-only business status, contact details, subscription expiry and business-wide branch/staff limits; branch comparison and activity history.
- Retry-safe POS submissions bound to branch, request key and exact content. An identical retry opens the recorded invoice without duplicating stock movements or journals; altered reuse is rejected.
- Guided full/read-only/custom permissions for central and owners. Every feature can be enabled, visibly locked, or locked and hidden. Tenant users require an assigned business branch; legacy unassigned accounts can be repaired centrally.
- One service registry drives grouped sidebar links and dashboard actions. Hidden services disappear consistently; locked services open a contact/activation page without exposing panel values. Dashboard cards have independent central/owner policies.
- Responsive POS rows become labelled cards on phones instead of compressing desktop table headings into vertical text.
- Optional central user preview uses the selected user's menus, branch and permissions without replacing the administrator identity. POST changes and quota consumption are blocked, with a persistent exit control.
- Central business/user policies and owner employee policies, service/action permissions, hidden or locked presentation and atomic daily/monthly request limits. Employee AI requires both central and owner approval, within the owner's central ceiling.
- Central and tenant TOTP with expiry, throttling and replay protection. Optional tenant enrollment, 30-day trusted devices, central/owner employee approval, lost-phone reset and device revocation on password rotation. Password rotation, session revocation, CSRF checks and branch-scoped mutations.
- Private-token installation with database, first central administrator and verified authenticator enrollment. No default accounts. An authenticated central setup page holds deferred AI, Google and contact settings encrypted outside the public root.
- Fixed-decimal double-entry posting for supported new purchase, sale, cost, expense, damage, cash/bank, capital, transfer and return documents. Per-currency trial balance, financial position and net income; verified openings, manual noncash journal reversal and financial-period locking.
- Reviewed partnership principal/profit/recovery accounts, actual funding/repayments, approved distributed returns, verified profit amendments, closing and explicit ownership release for settled contract goods.
- Documented currency exchange, foreign-currency debt settlement and allocation of existing foreign receipts, without collecting money twice.
- Audited corrections for mistaken open-period expenses, losses, capital and standalone debts; verified legacy purchase openings preserve historical cash.
- Server pagination/sorting on primary lists and history, accessible table sorting, input-truncation rejection, hidden profit-cost redaction and request allowances charged once per HTTP request.
- Cash/bank accounts, retry-safe opening balances, purchase payments, receivable/payable settlements, unpaid operating expense payments, customer refunds, supplier refund receipts, same-currency account/branch transfers and calculated daily cash reconciliation with another person's approval.
- Structured suppliers, regular/registered/walk-in customer relationships and separate retail/wholesale classifications.
- Purchase/movement history, in-transit stock reservation, cancellation/rejection/receipt of branch transfers, physical counts, partial/full sales and purchase returns. Original invoices and receipts remain intact.
- Gemini adapter with aggregate-only branch context and escaped read-only advice. No provider key is bundled.
- Encrypted database plus supported uploaded-file backups, checksums, compensating file recovery, session invalidation after restore, local scheduled retention, Google OAuth connection and a CLI Drive uploader with optional email notification.

## Fresh installation

For hosts without writable storage outside htdocs, use the individually keyed package described in [the short shared-host guide](docs/SHARED_HOST_DA.md). It installs through the browser after owner-key authorization and actual Apache denial checks. Read [the security assumptions and migration procedure](docs/SHARED_HOST_STORAGE.md); outside-root storage remains preferable. Generate a fresh package with `python3 bin/package-shared-host.py /absolute/private/path/release.zip`. Do not replace an existing installation's profile or keys during updates.

See [the Dari installation guide](docs/FRESH_INSTALL_DA.md). The distributable contains source only: no runtime configuration, databases, users, test credentials, sessions or tenant uploads. A fresh installation needs an empty database, a newly prepared private installation token, your HTTPS application address and your own central password/authenticator enrollment. The installer refuses existing accounts; it does not erase an existing host.

## Start

PHP 8.3+, PDO MySQL (or explicitly selected SQLite), mbstring, OpenSSL, Sodium, zlib, fileinfo and cURL. GD is needed for logo re-encoding. Use HTTPS and set the web server document root to **`public/`**. Private `var/`, CLI `bin/` and tests must stay outside it. Never expose PHP's development server publicly.

For a fresh installation, provision an empty MySQL database and account on the host. Prepare private storage, run `php bin/prepare-install.php`, retrieve the generated private token from `var/install-token.key` through the protected host file manager, then open `/install.php`. Enter the database details, a strong central password and verified authenticator code. Installation seals itself and cannot replace existing accounts. Use Office 2 → Central setup to enter optional service settings later.

Private settings use `NIKXOR_RUNTIME_DIR` (default `var/`), authentication/backup material uses `NIKXOR_PRIVATE_DIR` (default `var/private/`), and sessions use `NIKXOR_SESSION_DIR` (default `var/sessions/`). Each must be writable by PHP and outside `public/`. Process environment values override saved integration settings.

Existing accounts must use authenticated setup, not reinstall. See [deployment and upgrade instructions](docs/DEPLOYMENT.md), [Dari release/install guide](docs/UPDATE_DA.md), and [executed validation and limits](docs/VALIDATION.md).

Historical books need reviewed opening balances. Use the reviewed settlement and correction workflows rather than the superseded direct-edit/payout handlers. Cash, funding ownership, historical openings and cross-currency rates must be verified by the operator. Automatic market valuation, complex legal partnership allocation, large-host recovery and an independent security/load review are outside the executed validation. This release does not claim complete security or a complete enterprise accounting system.

## Full local verification image

The minimal `docker/Dockerfile` keeps the earlier cloud startup workflow. Logo upload tests use the full PHP 8.3 build in `docker/Dockerfile.full`, adding GD, cURL and mbstring. In the managed cloud, provide the session CA through a BuildKit secret (never copy it into the image):

```sh
docker build --secret id=proxy_ca,src="$CODEX_PROXY_CERT" -t warehouse-test-php-full -f docker/Dockerfile.full .
```

Do not use the development PHP server as production hosting. Fresh installation and archive contents are described in `docs/FRESH_INSTALL_DA.md`; executed results and limits are in `docs/VALIDATION.md`.
