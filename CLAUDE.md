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

```bash
ddev exec yarn dev  # Start Vite HMR dev server (port 5173, also exposed on host)
ddev yarn build     # Production build → app/client/dist/
```

Vite dev server URL is configured via `.env` (`VITE_DEV_URL`). The `vite.config.js` uses `@` as an alias for `app/client/src/vue/`.

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

- **Models** live in domain subdirectories: `Teams/`, `Events/`, `Calendar/`, `Notices/`, `Notifications/`, `Tasks/`, `Food/`, `Maps/`, `Links/`, `SuggestionBox/`, `HumanResources/`
- Core hierarchy: `Organization → Department → Project` (all `DataObject` subclasses in `Teams/`)
- **Controllers** in `Controllers/`:
  - `ApiController.php` — base for all REST endpoints; handles CORS, JSON response, session auth
  - `Api/*Controller.php` — domain-specific REST controllers (inherit `ApiController`)
  - `VueAppController.php` — serves the SPA shell for all non-API routes
- **Extensions** in `Extensions/` (e.g., `MemberExtension`) add fields/methods to core SS classes
- API is mounted at `/api/v1/`. All API responses use `{ success: bool, data: {} }` or `{ authenticated: bool, user: {} }`
- Run `ddev sake dev/build flush=1` after any model or `_config/*.yml` change

### Frontend (Vue 3 — `app/client/src/vue/`)

- **`app.js`** — creates and mounts the Vue app with router and Pinia
- **`router/index.js`** — client-side routes; routes with `meta: { requiresAuth: true }` redirect to login if not authenticated
- **`stores/`** — Pinia stores: `auth.js`, `dashboard.js`, `events.js`, `notices.js`, `notifications.js`
- **`utils/`** — API helpers (`apiGet`, `apiPost`, `apiPut`, `apiDelete`) with localforage-based client-side caching (5-min TTL). Cache falls back to stale data on network errors
- **`views/`** — page-level route components
- **`components/`** — reusable UI components

**Data flow:** Vue component → Pinia action → `utils/` API helper → fetch with session cookie → `ApiController` → JSON response → Pinia store → reactive component update

### Firebase

Push notifications use Firebase Cloud Messaging. Config keys come from `.env` (`VITE_FIREBASE_*`). A service worker handles background messages.

## Environment Variables

Copy `.env.example` to `.env`. Key variables:
- `SS_DATABASE_*` — MariaDB connection (matches DDEV defaults)
- `VITE_DEV_URL` — Vite dev server origin (e.g., `http://localhost:5173`)
- `VITE_API_BASE` — API base URL
- `VITE_FIREBASE_*` — Firebase project config
