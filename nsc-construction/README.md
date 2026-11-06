# Nik Smart Count — construction workspace

Recovered construction accounting source, with a separate empty browser-local test build. This directory is independent of the warehouse application already in the repository.

## Ready-to-upload test

Extract **NSC-Upload-To-htdocs.zip** directly into your site's `htdocs`. `index.html` must sit alongside `assets`, `icons`, `sw.js`, `manifest.webmanifest`, `offline.html`, `.htaccess` and `test-mode.json`.

This is the requested empty no-login test: no PHP/MySQL setup, no sample financial records, and apartments/sales locked. Each browser keeps separate records and attachments. Download a JSON backup from Settings before clearing browser storage.

See [Dari upload instructions](docs/UPLOAD-DA.md) and [implementation limits](docs/RELEASE-STATUS.md). This recovery release does not claim that every requested feature is complete.

## Build

Use Node 22.12+ or Node 24 LTS:

```sh
npm ci
npm run lint
npm run build:blank-test
```

Upload the contents of `test_upload/` for the local test. Dependencies and generated builds are excluded from source control.

```sh
npm run build:host
```

`host_upload/` is the authenticated PHP/MySQL production build. It needs PHP 8.3+, MySQL/MariaDB, HTTPS, private server configuration and migrations. See [DEPLOYMENT.md](DEPLOYMENT.md). It is not the empty test package.

Private bootstrap credentials are not distributed. Create the first administrator with `scripts/server/create-user.php --master`, or configure the variables in `api/first-install-credentials.example.php` privately before using the web installer. Never commit real credentials or database passwords. Existing private bootstrap files on an installed host must not be overwritten with the example.

## Verification

Run `npm run lint` and the `scripts/verify-*.ts` scripts with `node --import tsx`. PHP integration checks in `tests/security/` require a disposable test database; never use production. Current release evidence is included in the source archive. Physical phones and the live hosting account have not been tested in this recovery release.
