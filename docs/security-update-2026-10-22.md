# Dependency security update - 22 October 2026

PHP2Shell is fixed in PHP 8.5.12. This checkout now requires that release
or a later 8.5 patch. The Windows runtime comes directly from php.net and
its SHA-256 is checked before extraction.

## Changes

- PHP: 8.3.14 to 8.5.12; run `.\php.cmd` on Windows.
- Vercel: pinned FrankenPHP 1.13.2 / PHP 8.5 Alpine image by digest.
  Docker Registry image configuration confirms `PHP_VERSION=8.5.12`;
  the build also rejects an older PHP version.
- GitHub Actions and Windows packaging target the patched runtime.
- Updated the MySQL SSL constant in `config/database.php` to its PHP 8.5
  replacement, preserving the same connection option without startup notices.
- Laravel: 13.24.0 to 13.38.0; 73 Composer packages installed or updated.
- 238 npm package entries installed or updated, including Vite 8.3.5,
  Vue 3.5.44, Transformers 4.3.1, and DOMPurify 3.4.17.
- The existing `lucide-vue-next` import name resolves to maintained
  `@lucide/vue` 1.61.0 through an npm alias.
- Removed redundant DOMPurify and Ziggy type stubs.
- Override `postcss-selector-parser` to patched 7.1.7. Production compilation
  verifies its compatibility with the existing Tailwind 3 configuration.

Tailwind 3, Inertia 2, Vue Router 4, TypeScript 5 and matching Node 22 types
remain on their latest compatible branches. Moving those to the newest major
versions requires a separate application migration.

## Verification

- Production frontend build: passed.
- Focused ESLint for Vite configuration and speech worker/client: passed.
- Composer validation and installed platform requirements: passed.
- Composer audit: zero advisories, down from eight across six packages.
- npm audit: seven high findings, down from 33 findings including one critical.
  All remaining findings trace to the same unpatched `braces` advisory,
  GHSA-vfj7-8cjw-p6xm, through Tailwind/watch/glob and ESLint tooling.
  The latest upstream `braces` release is still 3.0.3 and remains affected.
  No advisory has been suppressed.
- PHPUnit on isolated in-memory SQLite: 304 tests, 1,994 assertions;
  nine errors and 18 failures. The same 27 test names failed before updating.
  Existing filesystem permission failures account for part of this baseline.
- Type checking: four existing errors remain in assessment/project pages;
  no new diagnostics compared with the original checkout.
- `git diff --check`: passed.

No existing database was migrated or changed. Existing uncommitted application
changes were preserved. Package install scripts were skipped; Laravel package
discovery was then run explicitly and the frontend build passed.

This updates the checkout and future builds. The running Vercel deployment,
previously published installer, and already installed Windows copies still
require a separate deployment or installer update. No Docker engine was
available locally to build the complete production container.

## Shared Wamp update

The user also authorized updating the shared Wamp installation.

- Wampmanager: 3.3.7 to 3.4.2, using its signed official updater.
- Default Apache PHP and shared Windows CLI path: 8.3.14 to 8.5.12.
- Xdebug: 3.5.3; OPcache remains enabled.
- Preserved compatible settings and enabled extensions. The old IMAP extension
  is unavailable in the new runtime and was left disabled; PHP no longer bundles
  it, and its manual cautions against using it with thread-safe builds.
- Added PostgreSQL PDO support alongside the existing MySQL and SQLite drivers.
- Updated Apache's OpenSSL DLL pair to the matching PHP 3.5.9 libraries after an
  isolated Apache copy confirmed that the old 3.1.7 libraries blocked cURL.
  Original DLLs were backed up before replacement. Apache syntax, HTTP cURL
  initialization, and encryption/decryption all passed afterward.
- SNSU's installed production dependency platform requirements pass on 8.5.12.
- Moodle's installed HTMLPurifier dependency rejects PHP 8.5. Its existing
  `moodle.local` virtual host now uses patched PHP 8.4.27 through Wamp's standard
  FastCGI configuration. Its dependency platform requirements pass on 8.4.27.
  Existing Moodle upload, memory and input limits were preserved; two deprecated
  session-ID configuration directives were removed to prevent startup warnings.
  Moodle CLI commands should explicitly use
  `C:\wamp64\bin\php\php8.4.27\php.exe`.
- Localhost HTTP probes confirmed PHP 8.5.12 under Apache and PHP 8.4.27 under
  the actual Moodle FastCGI handler configuration. Both passed cURL and crypto
  checks. The probes used an isolated document root and did not bootstrap sites
  or access their databases.
- The Wamp Apache/MySQL/MariaDB services were stopped before this work and
  remain stopped. Temporary Apache probe processes were stopped after checks.
- Reopen terminals and VS Code to pick up the new shared CLI path and restart
  its language-server helpers. The old PHP language-server processes were
  stopped to release an installer log lock; editor windows were not closed.

Configuration, original DLLs and path backups are in
`scratch/wamp-upgrade/backup-20261022-093207/`. This directory is ignored by Git
and excluded from deployment uploads. Previous PHP installations were retained.

## Sources

- https://www.php.net/downloads.php?os=windows
- https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w
- https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp
- https://github.com/advisories/GHSA-vfj7-8cjw-p6xm
- https://wampserver.aviatechno.net/?lang=english
- https://www.php.net/manual/en/imap.installation.php
- https://www.php.net/ref.pdo-mysql
