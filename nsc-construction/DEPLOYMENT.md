# Secure PHP/MySQL installation

## Fresh ProFreeHost installation without SSH

The current host package includes a one-time installer at `/setup/`. Upload the **contents** of `public_html` directly into `htdocs`, including `.htaccess`, `api/` and `setup/`. Open the site's HTTPS `/setup/` address, enter the private setup key supplied separately to the owner, then the MySQL connection details. Enter the vPanel/MySQL password only on your own HTTPS site. The installer creates the schema and primary Office Seven account in an **empty database**. It never resets an existing database or account.

PHP needs write access to the account directory immediately **outside** `htdocs`; the installer saves `nsc-private-config.php` there with owner-only permissions. Keep this private configuration and `nsc-private-uploads/` outside the website. Successful installation locks `/setup/`. The success page provides the private Office Seven link; use the separately supplied username/password to sign in, change that password, then create company owners in **Accounts and services**. No demonstration login is enabled in production.

The account exists on the live host only after completing this installer. For an already configured database, use the existing deployment/migration workflow below; the installer cannot migrate an occupied database.

Use PHP 8.1+ with PDO MySQL and MySQL 8+ or MariaDB 10.6+. Use HTTPS and host-managed backups. Keep all secrets in the host environment outside public_html; `.env.server.example` is a template, not an automatically loaded secret file. The frontend and `/api` must use the same origin. APP_ENV must remain production.

1. Back up existing database and files before migration. SQL DDL auto-commits; backup restore is the rollback route.
2. Set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_ENV and APP_ORIGIN in PHP's environment. Configure NSC_MAIL_FROM and the host mail transport for password recovery.
3. Keep the full source in a private directory and run `php scripts/server/migrate.php` there. This creates new empty tables or adds missing columns without DROP. Existing unassigned business rows remain hidden. Known demonstration-password accounts are locked.
4. Create a unique master account using `php scripts/server/create-user.php --master --username=... --email=... --name=...` and pass a 12–72-byte random password on standard input. Never put the password in shell history or command arguments. Create a separate company owner without --master. Existing users can be reset with --reset=<user-id> using the same stdin password mechanism.
5. For a legacy database whose unassigned records belong to ONE verified company, run `php scripts/server/assign-legacy-tenant.php --owner=<id>` to inspect counts, then add --apply. Do not use this bulk assignment on mixed-company legacy records. Map them separately from documented provenance.
6. `npm ci` then `npm run build:host`. Upload only the contents of host_upload to public_html. Do not upload source, node_modules, database, tests, scripts, environment files, or ZIP archives. Apache must allow the supplied .htaccess rules.
7. Check sign-in, logout, company isolation, save errors and concurrent edits against the actual host before using real records.

The new browser workspace is stored as a revisioned per-company document in tenant_workspaces, with history in workspace_revisions. Financial reports in the app derive from that document. Older relational integration endpoints are separately tenant-scoped; do not mix them with workspace records without a migration/import workflow.

The bundled preview is a demo and does not prove production identity or hosting configuration. PWA and later feature changes are tracked separately in the implementation plan.

## Financial approval and contractor deductions

New financial records start as drafts. Only documents posted through the server approval endpoint enter the final journal; initial payments require their own approval. Submitted/approved documents must be returned to draft before correction. Posted documents are immutable; historical untagged records retain their prior behavior pending reviewed migration. Full reversals for workflow-posted expense, steel, concrete and payment documents are implemented. Separate reverse permission is required; reason/date/actor are preserved. Historical untagged records and partial reversals are excluded. Corrections create independent draft documents referencing their reversed originals.

Contractor retention and withholding percentages are saved on each bill. The configured rates do not determine statutory tax law. Contractor net payables (2250), retention (2400), tax (2600), and independent release/remittance payments are separate. Currency normalization rounding uses 8210 without changing original bill amounts.

Fresh relational chart defaults are English with zero opening balances. Existing account labels/balances are preserved by INSERT IGNORE. The modern workspace journal and legacy relational endpoints have distinct taxonomies; do not mix them without an explicit reviewed mapping/import.

A reversal posts in its own open period even if the original period is closed; it never changes the original amount/date/approval history. Related posted payments must be reversed before their bill. Reports retain original entries and add inverse entries on the reversal date. Sold-unit costs now adjust on dated cost events instead of shifting later costs/reversals into the original sale period. Review changes to unit inventory/area separately because the existing allocation model remains area based.

## Interface languages

New companies default to English. The owner can change the company's default interface language. Staff receive this safe setting without permission to edit company settings, and can keep a per-user, per-device preference for English, Afghan Dari or Pashto. Non-sensitive language preference and per-user notification-read identifiers are stored locally. User-entered names and descriptions are preserved. Accounting dates remain Gregorian ISO in storage and filters; the interface/CSV use localized Gregorian dates with Afghan month names. This does not migrate legacy relational records or inactive legacy screens.

## Sale reversals and installment receipts

Workflow-posted sold units can be fully reversed with a separate project/module reverse grant, after all related posted receipts have been reversed. Contract date, price, buyer, schedule and approval history remain immutable. Sale and dated COGS entries reverse in the open reversal period; later costs stop allocating to the canceled sale. Correction chains represent the same physical unit with fixed unit/floor/area, one active contract and one inventory-area contribution.

Set installment schedules while the contract is a draft. Posted contracts cannot mutate nested paid caches/history: receipts are separate Payment records with optional installmentId, approved independently. Targeted receipts use the contract currency (and AFN rate) and are capped per installment and per contract. Unassigned receipts beyond the quoted downpayment allocate in installment-number order. A sale reversal closes further collection. Reserved-contract cancellation and historical untagged migration remain separate; full payroll/investment reversals, partial cash reversals and source credits are implemented in later sections.

## Payroll and investment reversals

Workflow-posted payroll and initial/additional investments now support a separate view/reverse project/module grant, open-period reversal and immutable original records. Payroll reversal mirrors accrual, advance application and integrated net cash payment exactly; pending payroll does not invent cash. Sold-unit cost adjustments occur on the reversal date. Payroll corrections start pending in the interface and require independent approval. The advance account remains the existing aggregate model; reconcile its opening balances from actual documents before real use.

Investment reversals mirror the original cash/bank/hawala account, equity class and rate. Submission requires a registered partner in the same project. Investment-only users receive a limited partner selector without private partner details. Auto-created initial investment records start as drafts. Partial payroll settlement now uses separately approved payroll Payment records and partial cash reversals; legacy untagged history remains subject to reviewed migration.

## Office Seven configuration

Only the exclusive primary Office Seven account can edit tenant design and global licensing; ordinary company owners retain business settings and employee grants. Global company administration (office7.php, cross-company selection and company creation) is restricted to this exclusive primary account, including against other master accounts. Staff cannot change company design/history. Dynamic informational pages use custom_* project grants; their content is redacted when staff lack access. Layouts are declarative, bounded and validated in api/design.php against api/ui-catalog.json; builds regenerate the catalog from the actual 16 modules and translation dictionary. Do not omit these two files when deploying.

Required core financial fields, approval transitions, signed amounts, tax/retention, journal rules and authorization cannot be disabled by a design setting. Service locks and disabled operations are enforced server-side. Added x_* fields can extend supported records without becoming accounting amounts; signed historical values survive removal of the field definition. Native preview is isolated from production. See WAVE6-OFFICE-GUIDE.md in the source release for all controls and scope.

## Required upgrade: private Office Seven and licenses (003)

Run scripts/server/migrate.php outside public_html before deploying this release. It adds users.office7_locked and office7_control without removing existing data. Existing finite account and company expiries are both enforced at the earlier date; review actual licenses before rollout. The first active standalone master becomes the persisted private-office principal. To explicitly select your real master account, use php scripts/server/configure-office7.php --owner-id=USER_UUID; this also rotates the navigation link. OFFICE7_OWNER_USER_ID is available only for initial binding. Ordinary owners and secondary masters cannot access the private office.

Sign in as the primary account and obtain/bookmark its private URL from Settings. The random hash route is navigation, not bearer authentication; APIs still require the exact principal, live account access and CSRF. User/license changes invalidate target sessions; locking or expiring a company blocks its staff. The primary account cannot lock or expire itself through this panel. See PRIVATE-OFFICE-LICENSE-GUIDE.md.

## Cleanup and account controls update
Run migration 004 before uploading this version. It removes retired client-only section passwords and Gemini API-key fields from workspaces and their history without changing financial records. Bootstrap only the primary administrator with the CLI --master option; create company owners inside private Office Seven. Own/owner/employee password changes and per-user service controls are available in Accounts and services. Personal themes and notifications are stored per account in the browser. Google Drive requires a public VITE_GOOGLE_CLIENT_ID and the real HTTPS origin in Google Cloud.


## آخرین به‌روزرسانی

اجرای migrate.php، مهاجرت‌های ۰۰۵ تا ۰۰۸ را هم اجرا می‌کند: مشخصات شرکت و صفحهٔ معرفی، بازگرداندن نمودار هوشمند، شناسه‌های مجاز پروژه در فایل‌ها و نگهداری خصوصی عکس پروژه. عکس بگراند و لوگو از media.php خوانده می‌شود؛ پوشهٔ UPLOAD_DIR باید بیرون public_html و قابل نوشتن PHP باشد. در پشتیبان هاست، MySQL و UPLOAD_DIR هر دو شامل شوند. دیتابیس آزمایشی و فایل‌های تست داخل بستهٔ public_html نیستند. رهنمای تازه در NEW-CHANGES-GUIDE.md است.


## Unicode and security update 009

Run the current migration after taking a MySQL and private-file backup. Security/workspace tables use InnoDB and utf8mb4_unicode_ci even when the host database defaults to latin1. Existing security-table collations are aligned. The app uses Kabul business dates and UTC database sessions. Run `php scripts/server/audit_database.php` from the source checkout for a read-only integrity report; do not upload scripts/tests to public_html. Configure PHP upload_max_filesize at least 10M and post_max_size at least 12M when allowing 10 MB documents.

## Visual and business studio update 010

Back up MySQL and private uploads, then run `php scripts/server/migrate.php` from the source checkout before uploading this build. Migration 010 adds four tenant-indexed InnoDB/utf8mb4 tables for drafts, published versions, custom business records and primary-administrator collaboration. It does not rename or delete construction records. Keep database/scripts/tests outside public_html.

Open the private Office Seven link, choose **Visual builder**, then select the company workspace. Drafts and published pages are separate; publishing atomically updates custom pages, services, formulas and grants. Record values are recomputed on PHP, final records preserve their schema and formula snapshot, and projects with custom records cannot be deleted. Collaboration is restricted to authenticated primary-administrator sessions; shared drafting saves after 1.5 seconds and polls other sessions every 4 seconds, with revision conflicts shown explicitly.

Core accounting posting, balancing, settlement and reversal constraints remain enforced. The studio's editable formulas apply to custom business fields, not arbitrary PHP/JavaScript or rewriting posted journals. Live business forms and their access controls require the API; exported page HTML contains the visual layout. Portable preview data stays in that browser and does not connect to production.
