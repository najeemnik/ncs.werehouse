# Validation — 2.3.6 Authenticator and trusted devices, 2026-11-06 (Asia/Kabul)

## Checks executed for 2.3.6

The new checks used isolated PHP 8.4 WebAssembly and SQLite, not the live host. All changed PHP files passed syntax checks and `git diff --check` passed. The `security` regression suite and new `mfa` suite completed successfully.

- Tenant enrollment required a valid current six-digit code; a new session then required MFA.
- A trusted cookie restored access without another code. The database contained only SHA-256 token/agent hashes, never the raw token.
- The trusted-device expiry was approximately 2,592,000 seconds; an expired row and a changed browser agent were rejected, while the original agent was accepted.
- Owner denial disabled employee MFA access, including an existing MFA session. An inactive employee could not use the service.
- Server-side revocation invalidated the cookie. Central lost-phone recovery removed the tenant secret and every device.
- Migration 024 was repeatable. The ordinary password/security page and password-change action retained their original permissions; only Authenticator mutations require `settings.mfa`.

Real Apache/MariaDB, browser cookie behavior and HTTPS on the user's host remain deployment checks. No external Authenticator service is contacted: codes are generated and verified locally using RFC 6238.

## Checks executed for 2.3.5

The new checks again used isolated PHP 8.4.27 WebAssembly and SQLite, not the live Apache/MariaDB host. All 156 PHP files under `public/` and `tests/` passed syntax checks, and `git diff --check` passed.

- Reproduced the account-design failure: the generic Office 2 form could create a tenant staff user with no business or branch, so dashboard authorization failed. That path now rejects unassigned tenant creation and directs the administrator to business-user management. Existing unassigned accounts can be explicitly assigned a branch from Office 2; no branch is guessed.
- Created a full-permission business employee, signed in with the temporary password, completed the mandatory password change and opened the authorized dashboard. Central full-access setup enabled detailed sales/treasury permissions while a business-level lock still won.
- The owner created two employees with different presets: full sales access and read-only access. Office 2 hidden mode hid the menu; locked mode kept it visible. The management screen rendered the quick presets, all three clear modes and user-preview links.
- Four separate real preview requests verified the workspace banner and exit control, rejected a sale POST as read-only, stopped preview and restored the original central identity. No sale was inserted. Preview also left all request-allowance counters unchanged. An owner could not start central-only preview.
- Regression suites `security`, `business-controls` and `staff-limits` completed after these changes. Previous suite results below remain historical and are not counted as rerun here.

Preview is intentionally not impersonation for conducting business. It is an optional read-only inspection mode. The central account remains the authenticated actor, the target must be active and assigned to the selected branch, central MFA must remain valid, and normal permission checks still apply. No new database migration is required for 2.3.5.

The actual ProFreeHost account, real browser, Apache/MariaDB and external services were not exercised. These results do not claim zero vulnerabilities or that every legacy unassigned account can be repaired automatically; an administrator must choose its correct business and branch.

## Checks executed for 2.3.4

These are new isolated **PHP 8.4.27 WebAssembly / SQLite** checks, not an Apache, MariaDB or ProFreeHost deployment test. The WASM runtime is an external test tool and is not bundled with the application.

- All 152 PHP files under `public/` and `tests/` passed PHP 8.4 syntax checks.
- `business-controls.php` passed: repeatable migrations 022/023, central contact/subscription/limit updates, zero-as-unlimited, owner denial for central controls, branch/staff ceilings, lock and expiry enforcement, branch-scoped sale keys, altered-content rejection and source guards for the two purchase dropdown queries and contract update. The central management screen was also rendered and checked for the real business-control form and selected-business invoice-design link.
- The real POS handler was executed in four separate PHP requests: initial sale, identical retry, altered reuse and verification. The identical retry produced one invoice, one accounting document and one stock movement; quantity changed from 5 to 3 and inventory value from 50 to 30 exactly once. Reusing the key with an altered paid amount showed the explicit rejection and changed no recorded totals.
- Fresh/isolated regression runs completed for security, transfers, inventory/backup, purchase returns, FX, corrections, partnerships, invoice design and staff limits. The new central business checks also passed after correcting the staff-count membership query.
- Earlier WASM attempts could not complete `finance` or `invoice-verification` because that runtime lacks Sodium; `policies` also has a non-CLI SAPI difference. Those are explicitly **not** counted as new passes. No MariaDB, Apache, browser, real host, external AI/Google/mail or concurrent-production-load check was performed in this patch.

Migration 022 adds optional business contact/subscription fields and branch/staff ceilings without changing existing values. Migration 023 adds nullable sale request key/content hash columns and a unique `(shop_id, sale_request_key)` index; historical sales remain nullable. The active request handler now supplies these values. Limits are enforced in the server-side management service, with a business-row lock on MySQL to serialize creation against ceiling changes. Central users retain their existing central-only invoice-design path.

The checks do not prove zero vulnerabilities or complete accounting coverage. The actual host still needs a backup, patch upload, automatic migration on the first authenticated request, and Apache/MariaDB smoke tests. Do not replace `config/hosting.php`, private keys, `.htaccess` files or existing runtime data during the update.

## Historical checks for 2.3.3

The diagnostic and preparation trimmed copied key whitespace while the installer compared raw input. Key verification is now shared and consistent. It is a separate CSRF-protected step; the verified session stores only a timestamp/binding and expires after 15 minutes. Administrator autofill fields are absent from the key step, and the raw key field is absent from the account step. Key rotation or a different runtime directory invalidates authorization. Existing configured accounts are detected with read-only queries after key verification, before displaying an administrator-creation form. A candidate nonempty database is rejected before its credentials are persisted.

The retry test also found a pre-existing global-variable collision: application configuration could overwrite the installer's generic `$dir` on requests after migrations had already run, making sealing target `//installed.lock`. Installer storage now uses a dedicated variable throughout. This was corrected before packaging.

- 16 dedicated key/session assertions pass, including copied outer whitespace, altered/oversized/structured keys, expiry, missing key, symlink, changed runtime and rotated profile key.
- A new empty MariaDB database uses a real account denied CREATE VIEW. Original ZIP plus the exact 17-file repair were exercised on real Apache/PHP with an unwritable application parent. The complete browser flow passes: preparation, unauthorized/wrong key checks, separate key/account forms, rejection of a nonempty candidate DB before private-config write, invalid-TOTP retry with nonsecret fields retained and secret fields blank, successful installation/seal, central MFA login, encrypted provider-setting save and direct private-file denial. Rule-tampering failure and recovery checks pass again.
- A separate existing-account database passes the new preflight; before key verification neither account existence nor the creation form is shown. After verification the existing-account notice/link appears. Unverified/forged-CSRF requests fail. Complete database-row and profile/proof snapshots remain unchanged; mobile layout fits.
- Final PHP syntax and modified browser-test syntax checks pass. The ZIP has 17 changed application files (16 PHP and one CSS), no profile/key/runtime/database/denial-rule replacement, correct readable file modes and source digest/CRC verification. It includes the previous 2.3.2 repair.

The earlier finance/view-privilege regressions below remain the 2.3.2 baseline; they were not rerun for these installer-only changes. The actual user hosting account, AI/Google/mail services and human use of specific browser password managers were not exercised. The key/account field separation is structurally tested; no claim is made that every browser disables autofill. Existing accounts/passwords are not replaced and a completed installation remains sealed.

## Checks for 2.3.2

The user's host report showed MariaDB 11.4.13, matching 2.3.1 files and valid protected storage, existing accounts, no installation lock/master key, migrations 001–014 applied, 015–021 pending and no confirmed CREATE VIEW grant. The old code was reproduced against a real MariaDB account explicitly denied CREATE VIEW; it failed at migration 015 with driver error 1142. This matched the pending stage and generic login failure without relying only on the grant hint.

The repair replaces mandatory database views with shared, allowlisted derived queries carrying the original shop/entity correction predicates. It updates all 35 report-source references, validates the two sources before recording migration 015, and leaves existing database views intact. It does not catch-and-ignore the failed migration or fall back to unfiltered financial data.

- Syntax: 149 PHP files pass; the final added correction assertions also executed successfully on both backends.
- Fourteen backend suites pass on SQLite and the restricted MariaDB account, plus the dedicated restricted-host suite on MariaDB. After strengthening the capital/expense aggregate checks, corrections passed again on both backends. Distinct PASS labels: 260 SQLite; 275 restricted MariaDB (shared security checks repeat across suites).
- The restricted-host suite proves CREATE VIEW returns error 1142, both legacy views are absent, migrations through 021 complete, scoped aliased reports/currency summaries work and unrecognized entities/SQL aliases are rejected.
- Real Apache: original 2.3.1 code and an existing-account partial-schema fixture reproduced HTTP 500. Applying the exact 14-file deliverable restored HTTP 200 login and completed migrations 015–021 with zero database views. Account identity/password verifier, existing expense/capital rows and amounts were byte-for-byte equivalent under the selected-row snapshot. The installation profile, root denial rules and protection proof retained identical hashes. The existing owner then signed in with the unchanged password.
- After that exact ZIP upgrade, 28 owner screens passed HTTP/visible PHP-error checks, English UI checks, no uncaught JavaScript errors, mobile fit and owner-to-central API denial. These include losses, expenses, capital, stock, treasury, portfolio, reports and the AI-advisor screen. No external AI request was made.
- The patch ZIP's CRC, all source-file hashes, precise 14-file changed set and 0644 PHP file modes were verified. It includes no hosting profile, .htaccess, runtime keys, uploaded business files, database credentials or database export. Existing folders must be merged; never replace entire site folders or the protected profile.

These checks are synthetic; the user's actual host has not been updated by the assistant. The patch does not replace accounts, reset passwords, enroll central MFA, seal an incomplete installer or turn an existing database into a new empty installation. It addresses the proven view-privilege migration failure. Uploading the patch and checking the real sign-in page remain required. Subsequent authentication/recovery depends on the existing accounts and retained credentials. The diagnostics should be removed from the host after use.

## Checks repeated for 2.3.1

- Syntax: 147 PHP files and 19 JavaScript/browser-test files pass.
- All 14 backend suites listed below complete successfully again on independent SQLite and MariaDB 11.4 databases: 258 distinct PASS labels per backend. These runs retain the default outside-root storage mode.
- 20 additional protected-storage assertions pass, covering owner profile validation, denied access before preparation, proof/path/rule binding, public-path restrictions, rejection of public native session storage, guarded key stability, encrypted settings, fail-closed rule changes, traversal rejection and key compatibility outside the web root.
- A real Apache 2.4/PHP 8.3 fixture runs as www-data with an unwritable application parent. The final hosting ZIP was extracted separately and installed against a fresh MariaDB database entirely through browser forms. Actual text and PHP probes both returned 403 before activation. Unauthorized activation, a wrong private key and a false access result were rejected before credentials were written.
- The extracted ZIP passed fresh installation, sealed installer, central MFA, provider-native sessions, encrypted settings without secret redisplay and direct HTTP denial of config, credentials, master key and TOTP material. Removing both directory-denial rules in this synthetic fixture stopped app use; PHP-guarded keys remained unavailable. Restoring the rules resumed the existing session. No uncaught JavaScript errors occurred.
- The installed ZIP also passed real forms for creating two businesses, adding a Herat branch, inherited invoice identity, owner temporary-password change, empty initial stock and denial of a foreign business switch.
- Archive CRC, every bundled source-file digest and individual setup-token hash were verified. The hosting archive includes no database, live accounts, tenant uploads, private runtime or preinstalled application keys. Only the separate private installation-key file contains the newly generated setup token.

The installed synthetic site was also copied to a second Apache instance with the profile removed and runtime/private material outside the public root. After assigning the copied private files to the new PHP account, the unchanged guarded master/TOTP keys, decrypted provider setting, real central login/MFA, two existing businesses and Herat branch all worked. The local HTTP fixture correctly denied installer access because HTTPS was required. This was a migration smoke test; full disaster recovery and every transaction after migration were not rerun.

These results do not establish live ProFreeHost installation or external Google/AI/mail connectivity. Protected in-root storage relies on server denial rules; its assumptions and migration steps are in `docs/SHARED_HOST_STORAGE.md` in source and `PRIVATE-STORAGE-AND-MOVING.md` in the hosting ZIP. The earlier broader UI/transaction/QR checks below are retained as the **2.3.0 baseline**; they were not all rerun in this patch.

## 2.3.0 baseline

## Executed checks

All tests used generated synthetic accounts and independent SQLite/MariaDB databases, runtime keys, session directories and uploaded-logo storage outside the checkout. No live business database, hosting account, provider key or customer information was used.

| Area | Result |
| --- | --- |
| PHP syntax | 144 PHP files pass |
| JavaScript syntax | 17 standalone application/browser-test files pass |
| Backend regressions | 14 suites complete with exit code 0 on SQLite and MariaDB 11.4 through PDO MySQL |
| Distinct backend assertion labels | 258 per backend; suites repeat the same 33 shared security checks |
| Owner screens | 26 pages per backend: successful responses, no uncaught browser errors, no tested English UI labels leaking Dari/Pashto, mobile customer form fits |
| Existing transaction/security browser flows | 10 per backend, including persistent customer/branch creation, actual purchase/sale stock decrement, CSRF denial, owner-to-central denial, internal-file denial, stored text safety and central MFA |
| Cash/refund browser flows | 6 per backend: cash opening, actual purchase payment, sale receipt, partial return/retry, actual refund/retry and balanced financial position |
| Partnership/FX/security browser flows | 11 per backend, including actual partner cash, recovery, FX, correction, partial supplier return/refund, employee approval denial, once-per-request quotas, hidden-cost redaction and safe list/confirmation controls |
| Responsive header | Three languages × 12 widths (320–1440 px) per backend; currency/language navigation, theme save, menu keyboard/focus behavior, quick sale and actual screen lock |
| Invoice designer and POS browser flows | Business defaults, custom column/order, multilingual labels, safe live preview, mobile form, actual sale with serial values, preserved print snapshot, stale-form denial, mandatory fields, overpayment/type rejection, retained input and no inventory mutation after rejected sales |
| Logo upload with GD | Real multipart PNG upload, PNG re-encoding strips appended payload, false image data rejected; synthetic writable uploads separate from source |
| QR verification | Real printed SVG rasterized and independently decoded with ZXing-C++ to the exact signed HTTPS URL on both backends; unauthenticated record verification, tampered-code denial and customer/item privacy checked |
| Print/export | HTML-based Word response, localized HTML, one-page test PDF at A5 and one-page test thermal PDF of approximately 80 mm width with content-fitted length; invalid format and missing invoice denied |
| Fresh MariaDB installer | Incorrect token denial, local TOTP secret generation, database/admin/TOTP/HTTPS-origin setup, sealed installer, central MFA login, encrypted integration setup with no key redisplay and anonymous setup denial |
| Empty install state | Exactly one central administrator; zero stock, sales, debts, inventory purchases and fund transactions |
| Distributable | ZIP CRC/file digests verified, source-only exclusions checked, then the ZIP extracted into a separate checkout and its fresh installer exercised against another empty MariaDB database |

The last row records the packaged-code acceptance check completed before delivery. Assertion counts are check labels, not vulnerability counts, independent penetration tests or guarantees of correctness. PDO MySQL was tested with MariaDB 11.4; an independent Oracle MySQL server/version was not exercised.

## Fixes verified in this release

- Invoice inheritance now follows branch exception → business default → permitted visual defaults. Other businesses do not inherit legacy contact identity. Automatic-print lookup/write is correctly scoped.
- Explicit APPLY confirmation replaces shared design exceptions only within the selected business while preserving branch names/contact addresses. Future branches inherit without copying. Branch editors detect changes in inherited business settings.
- Layout data is declarative and validated. Arbitrary DB column sources, missing mandatory financial columns, oversized/unknown custom fields, invalid scope/CSRF, owner access to central editing and stale revisions are rejected.
- The POS form now encloses both product and checkout columns correctly. Entered fields/cart survive validation failure. Invalid payment modes and overpayment are rejected; stale template forms cannot relabel an old field silently. Required custom fields are enforced on the server.
- New sales preserve an invoice design snapshot and custom line values. Legacy invoices without one continue with current branding. The invoice renderer localizes UI language/direction and labels payment state, time zone and returns from recorded values. A zero return amount no longer marks an invoice returned.
- Thermal printing uses a real paper width and a measured page length; the earlier invalid `80mm auto` CSS fallback was removed. A5 and thermal sample PDFs were inspected and their physical sizes checked.
- QR contains a signed invoice URL, not an invented confirmation or static contact summary. Samples print no genuine verification QR. No valid HTTPS origin means no substitute verification QR. Codes bind to real rows using a per-invoice nonce and a private installation key. Public responses contain only allowlisted invoice/business fields and show actual payment/return status.
- Employee limits are consistent across Office 2 and business management. Assigned staff count even if based at another branch; membership editing cannot exceed a full/disabled branch. Existing members can still be edited. The former hard cap of three was removed from the older panel.
- Generated logo protection rules avoid Options directives that restricted shared hosts may reject; images are re-encoded with GD. Previously uploaded logos remain available for historical snapshots.

## Reproducing tests safely

Tests deliberately modify synthetic information and require `NIKXOR_TEST_MODE=1`. `tests/security.php` resets fixture policies/memberships. Run **only against isolated databases**. `tests/factory-reset.php` clears the selected synthetic financial/inventory data after making a verified encrypted recovery copy; run it last.

Backend suites: `finance`, `transfers`, `backup-files`, `oauth`, `inventory`, `purchase-returns`, `fx`, `corrections`, `partnerships`, `policies`, `invoice-design`, `staff-limits`, `invoice-verification`, `factory-reset` (`tests/*.php`). Each final output was checked for completion and exit code 0.

Browser tests read generated `NSC_TEST_ACCOUNTS`; `NSC_TEST_URL` selects the loopback-only test server. `browser-workflows.cjs` reads `NSC_WORKFLOW_FIXTURES`. `browser-invoice-design.cjs` also supports `NSC_TEST_ARTIFACT_PREFIX` to separate backend screenshots/PDF/QR artifacts. Test accounts, environment files, private keys and generated documents stay outside the repository/distributable.

`docker/Dockerfile` retains the minimal earlier development workflow. `docker/Dockerfile.full` adds GD, cURL and mbstring for full verification; its networked build uses the session CA as a BuildKit secret. PHP's built-in server and `tests/router.php` are development tools, not production deployment instructions.

## Remaining external checks and limits

No real Gemini request, Google consent/token exchange/Drive upload, Gmail inbox delivery or deployment on the user's host was performed. Saved integration settings do not prove service connectivity; provider credentials, quotas, mail configuration and domain/HTTPS setup remain user-specific.

This is not an exhaustive review of all operating-system/web-server configurations, legacy documents, language combinations, browsers, printers or Microsoft Word versions. The Word download is HTML with a .doc extension, not native DOCX. Host load/concurrent first migration, independent penetration testing and real-host disaster recovery remain outside these results.

A public QR verifies the stored business invoice, not independent bank settlement or legal ownership of the business. Keep a stable HTTPS origin and the private `var/settings.key`; losing/replacing that key invalidates old signed links. Customer information, item custom fields, stock costs and profit remain absent from the public verification response. Back up private hosting keys, authenticator recovery material and runtime settings separately from in-app database/upload backups.

Historical monetary values are not rewritten. Opening balances, legal agreements, mixed-currency valuation and legacy partner reclassification still require reviewed records. Backup size/schema/driver restrictions and the private-directory/document-root requirements are detailed in `DEPLOYMENT.md` and `FRESH_INSTALL_DA.md`.

Passing these checks does not establish zero errors or zero vulnerabilities. No source was pushed and no live site or account was changed.
