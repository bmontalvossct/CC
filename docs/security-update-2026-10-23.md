# Dependency security update - 23 October 2026

The release replaces the dependency paths that pulled in unpatched `braces` (CVE-2026-93687 / GHSA-vfj7-8cjw-p6xm). The published-main baseline had seven high npm findings; the existing development checkout had eight because of additional local dependencies. Both derive from the same upstream issue.

## Removed or replaced packages and effects

| Package / path | Action | Effect |
| --- | --- | --- |
| `braces`, `micromatch`, `fast-glob`, `chokidar` through Tailwind 3 and the Vue lint helper | Remove these dependency paths entirely | Vulnerable brace-pattern processing is no longer installed. No application feature is disabled. |
| `tailwindcss` 3.4.19 | Replace with 4.3.3 and `@tailwindcss/vite` 4.3.3 | CSS is still compiled during development and production builds. The existing JS theme and animation plugin remain. Small shadows/blur and forced-color keyboard outlines retain compatibility settings. Sidebar/menu CSS-variable syntax is updated to prevent overlapping content. |
| `@vue/eslint-config-typescript` | Remove; configure `typescript-eslint` and `vue-eslint-parser` directly | Vue and TypeScript linting remain enabled without the helper's vulnerable glob dependency. |
| `autoprefixer` | Remove the separate build plugin | Tailwind 4 handles vendor prefixing. |
| `tailwind-merge` 2.6.1 | Update to 3.7.0 | Component class merging supports Tailwind 4. |
| PHP/Composer packages | Update compatible dependencies, including `nette/schema` 1.3.7 to 1.4.0 | No PHP package or extension needed disabling: Composer audit reports zero advisories and zero abandoned packages. |

Tailwind 4 requires Safari 16.4+, Chrome/Edge 111+, or Firefox 128+. Older browsers can render incorrectly. Native browser color values and some framework defaults can differ slightly from Tailwind 3. See the [official migration guide](https://tailwindcss.com/docs/upgrade-guide).

## PHP

PHP.net lists **8.5.12** as the latest stable release at verification time. The existing project constraint (`~8.5.12`), CI, runtime installer and pinned production image already target this patched release. Both the active Windows CLI and the checksum-verified isolated project runtime report 8.5.12. Shared Wamp configuration was not modified by this update.

Sources: [PHP downloads](https://www.php.net/downloads.php), [PHP release metadata](https://www.php.net/releases/index.php?json&version=8.5), [braces advisory: no patched version](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm).

## Verification

- Clean lockfile installation (`npm ci`), browser build and SSR build pass.
- Full `npm audit` and production-only `npm audit --omit=dev`: **zero vulnerabilities**. `npm ls braces fast-glob micromatch chokidar` finds none.
- Composer strict validation, platform checks and audit pass.
- PHP suite before and after: **293 tests, 2,078 assertions, zero errors/failures**, with the same six existing PHPUnit notices.
- ESLint passes. TypeScript retains the same **23 existing diagnostics**; no new diagnostics were introduced.
- Playwright on a separate, newly created SQLite database verifies registration, login/logout, dashboard/onboarding, section creation, student enrollment, assessment creation, saving/reloading an 8/10 score, gradebook showing 80%, attendance-session creation and attendance persistence, module navigation, account menus, desktop/mobile rendering, and dark mode. Browser testing exposed and verified the sidebar compatibility repair.
- Local console findings are limited to the expected Vercel Speed Insights script 404 outside Vercel; no application JavaScript exceptions were observed.
- The attendance page has existing horizontal overflow at a 390px viewport: both original and updated assets measure 453px. This update does not introduce or repair that separate layout issue.
- CI now audits both npm and Composer dependencies, including development dependencies.

The release is built from a clean checkout of `origin/main`. The scoped security changes are also applied to the existing development checkout, preserving its local voice dependency and other feature work; its npm audit and Vite build pass. Existing user databases are preserved. No migration is added or changed.
