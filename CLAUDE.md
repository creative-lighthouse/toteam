# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ToTeam is a team management web application for non-profit organizations. It features a headless SilverStripe 6 backend (REST API) and a Vue 3 SPA frontend. The CMS locale is `de_DE`.

## Development Environment

This project uses **DDEV** (Docker). All commands should be run via `ddev exec` or their `ddev <tool>` shorthands.

```bash
ddev start          # Start containers; auto-runs yarn install, yarn build, composer install, sake dev/build
ddev stop           # Stop containers
```

The app is served at `https://silverstripe-toteam.ddev.site`.

### Frontend Development

`package.json` and `vite.config.js` live at the **repo root** (not under `app/client/`).

```bash
ddev exec yarn dev  # Start Vite HMR dev server (port 5173, host 0.0.0.0, strictPort)
ddev yarn build     # Production build → app/client/dist/ (with manifest + sourcemaps)
```

Vite dev server URL is configured via `.env` (`VITE_DEV_SERVER_URL`). `vite.config.js` aliases: `@` → `app/client/src`, plus more specific aliases into the Vue tree: `@components`, `@views`, `@stores`, `@utils`, `@models` (all under `app/client/src/vue/...`). Rollup has multiple entry points (`main.js`, `app.js`, `main.scss`, `editor.scss`).

### Backend

```bash
ddev sake dev/build flush=1   # Rebuild SilverStripe manifest (run after model/config changes)
ddev composer install
```

## Code Quality

```bash
ddev composer phpstan     # Static analysis (level 1)
ddev composer lint        # PHP CodeSniffer
ddev composer fix         # PHP CodeSniffer auto-fix
ddev composer rector-dry  # Preview code modernization (PHP 8.3 / SS6)
ddev composer rector      # Apply Rector changes
```

There are no frontend or backend test suites configured.

## Architecture

### Backend (SilverStripe 6 — `app/src/`)

- **Models** live in domain subdirectories: `Teams/`, `Events/`, `Calendar/`, `Announcements/`, `Notifications/`, `Tasks/`, `Food/`, `Maps/`, `Links/`, `SuggestionBox/`, `HumanResources/`, `Feedback/`, `Money/`, `Admins/`
- **Organization model** (`Teams/`): there is no `Department`/`Project` nesting — the hierarchy is flat.
  - `Organization` has_many `OrganizationMembership` (its `Memberships`) and `OrgRole` (its `OrgRoles`).
  - `OrganizationMembership` has_one `Member` + `Organization`, and many_many `OrgRole` (relation name `Roles`). It also carries a separate `Role` enum field (`applicant`/`member`) distinct from the `Roles` many-many.
  - Each `Organization` auto-creates 3 default `OrgRole`s on first save (Administrator/Moderator/Mitglied) via `createDefaultRoles()`, using permission codes defined in `OrgPermissions`.
- **Controllers** in `Controllers/`:
  - `ApiController.php` — base for all REST endpoints (`Controllers/Api/*Controller.php`). Sets CORS headers and JSON content-type in `init()`; provides `jsonResponse`/`successResponse`/`errorResponse` helpers producing `{success, data}` / `{success: false, error}`; `requireAuth()` returns `Security::getCurrentUser()` or null; `hasPermissionInAnyOrg()` for org-permission checks.
  - `Controllers/Api/*Controller.php` — domain-specific REST controllers (inherit `ApiController`): Announcements, Auth, Calendar, Dashboard, Feedback, Food, Links, Maps, Money, OrgRoles, Organizations, Profile, Register, SchedulingPoll, Settings, Tasks. Each self-declares `$url_segment = 'api/v1/<name>'`, and `app/_config/routes.yml` maps the matching `api/v1/<name>//$Action/$ID` routes to it.
  - `VueAppController.php` — serves the SPA shell (`url_segment = 'app'`, catches `$Action/$ID/$OtherID`, renders the `VueApp` template).
  - `BaseController.php` — a **separate**, legacy base (extends `Controller` directly, unrelated to `ApiController`) used by older non-API, non-Vue page controllers (e.g. `CalendarController`, `FoodController`, `LinksController`, `MapController`, etc.). Provides `getUserOrganizationIDs()`, `filterByUserOrganizations()`, `CheckUserPermission()`, `getAppVersion()`.
  - `PageController.php` (`app/src/PageController.php`) extends SilverStripe's `ContentController` — the standard CMS page controller, unrelated to the two bases above.
- **Extensions** in `Extensions/` (e.g., `MemberExtension`) add fields/methods to core SS classes
- API is mounted at `/api/v1/` (see `app/_config/routes.yml`). All API responses use `{ success: bool, data: {} }` or `{ authenticated: bool, user: {} }`
- Run `ddev sake dev/build flush=1` after any model or `_config/*.yml` change

### Frontend (Vue 3 — `app/client/src/vue/`)

- **`app.js`** — creates and mounts the Vue app with router and Pinia
- **`router/index.js`** — a single flat route array (Dashboard, Calendar, Food, Announcements, Profile, Map, Links, Organizations, Tasks, Money, Login, Register, plus detail/create/edit variants), history mode `createWebHistory('/app')`. The global `beforeEach` guard: lazily calls `authStore.checkAuth()` if the user isn't loaded yet, redirects unauthenticated users away from `meta: { requiresAuth: true }` routes to Login (preserving a `redirect` query param), redirects authenticated users away from Login/Register to Dashboard, and blocks routes whose `meta.totem` is disabled for the user's org (via `authStore.hasTotem(totemKey)`).
- **`stores/`** — Pinia stores (Composition API style, `defineStore('name', () => {...})`): `auth.js`, `announcements.js`, `dashboard.js`, `events.js`, `money.js`, `notifications.js`, `orgRoles.js`, `organizations.js`, `pageHeader.js`, `tasks.js`, `ui.js`
- **`utils/api.js`** — API helpers `apiGet`, `apiPost`, `apiPut`, `apiDelete`, `apiPostForm` (multipart uploads), `clearCache`/`clearCacheForEndpoint`. API base is hardcoded as `/api/v1` (no env var). Uses **localforage** (store name `toteam`/`api_cache`) for client-side caching with a 5-minute TTL (`CACHE_DURATION`); falls back to stale cached data on network errors.
- **`views/`** — page-level route components
- **`components/`** — reusable UI components

**Data flow:** Vue component → Pinia action → `utils/api.js` helper → fetch with session cookie → `Api*Controller` (extends `ApiController`) → JSON response → Pinia store → reactive component update

### Firebase

Push notifications use Firebase Cloud Messaging. Config keys come from `.env` (`VITE_FIREBASE_*`). A service worker handles background messages.

## Environment Variables

Copy `.env.example` to `.env`. Key variables:
- `SS_DATABASE_*` — MariaDB connection (matches DDEV defaults)
- `SS_ENVIRONMENT_TYPE`, `SS_DEFAULT_ADMIN_USERNAME`/`PASSWORD`, `SS_BASE_URL`
- `VITE_DEV_SERVER_URL` — Vite dev server origin
- `VITE_MANIFEST_PATH`, `VITE_OUTPUT_DIR` — Vite build integration paths for SilverStripe templates
- `VITE_FIREBASE_*` — Firebase project config (API_KEY, AUTH_DOMAIN, PROJECT_ID, STORAGE_BUCKET, MESSAGING_SENDER_ID, APP_ID, MEASUREMENT_ID, VAPID_KEY)
- `MAILER_DSN` — outgoing mail transport

There is no `VITE_API_BASE` variable — the frontend API base path (`/api/v1`) is hardcoded in `app/client/src/vue/utils/api.js`.
