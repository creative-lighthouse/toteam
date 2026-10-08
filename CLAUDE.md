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

The app is served at `https://toteam.ddev.site`.

### Frontend Development

`package.json` and `vite.config.js` live at the **repo root** (not under `app/client/`).

```bash
ddev exec yarn dev  # Start Vite HMR dev server (port 5173, host 0.0.0.0, strictPort)
ddev yarn build     # Production build → app/client/dist/ (with manifest + sourcemaps)
```

Vite dev server URL is configured via `.env` (`VITE_DEV_SERVER_URL`). `vite.config.js` aliases: `@` → `app/client/src`, plus more specific aliases into the Vue tree: `@components`, `@views`, `@stores`, `@utils`, `@models` (all under `app/client/src/vue/...`). Rollup has multiple entry points (`main.js`, `app.js`, `main.scss`, `editor.scss`).

Storybook (component development/preview, config in `.storybook/`):

```bash
ddev exec yarn storybook          # Dev server on port 6006
ddev exec yarn build-storybook    # Static build
```

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

- **Models** live in domain subdirectories: `Teams/`, `Events/`, `Calendar/`, `Announcements/`, `Notifications/`, `Tasks/`, `Food/`, `Maps/`, `Links/`, `SuggestionBox/`, `HumanResources/`, `Feedback/`, `Money/`, `Inventory/`, `Admins/`
- **Organization model** (`Teams/`): there is no `Department`/`Project` nesting — the hierarchy is flat.
  - `Organization` has_many `OrganizationMembership` (its `Memberships`) and `OrgRole` (its `OrgRoles`).
  - `OrganizationMembership` has_one `Member` + `Organization`, and many_many `OrgRole` (relation name `Roles`). It also carries a separate `Role` enum field (`applicant`/`member`) distinct from the `Roles` many-many.
  - `Organization::getLogoColor()` is the org's accent color (dominant hue of the logo, computed with GD on first access and cached in `LogoColor`; reset when the logo changes; null without a colorful logo). The calendar API sends it as `OrganizationLogos[].Color`; `EventCard` uses the first org's color for the left stripe (`--EventAccent` via `utils/orgColor.js`; orgs without a logo get the name-hash hue of the `AppOrgLogo` placeholder, colorless logos fall back to `--ColorPrimary`) — all appointments look the same otherwise, all-day ones included.
  - Each `Organization` auto-creates 3 default `OrgRole`s on first save (Administrator/Moderator/Mitglied) via `createDefaultRoles()`, using permission codes defined in `OrgPermissions`.
  - `OrgEvent` (UI: "Event", e.g. "Halloweenhaus 2026") belongs to an `Organization` and groups `Appointment`s (`Appointment.Event`). Food suggestions can be made per event (`Food.Event`) and are then assigned to exactly one meal by a food planner. Not to be confused with the legacy `App\Events\EventDay*` classes or the frontend's calendar `Event` model (a single appointment).
  - The internal part of the event page (`views/EventDetail.vue`) shows one card per kind of content as soon as the event has some (Termine if ≥1 appointment, Kassen if ≥1 `MoneyAccount` with `Event` = this event — only balance/target/budget bars via `OrgEventMoney`, Skripte if ≥1 linked script), plus a "Hinzufügen" card (`OrgEventAddActions`) whose buttons depend on the user's rights (`addActions` in `EventDetail.vue` — new kinds go there).
  - `OrgEventInterest` (Event + Member + Type `Interested`/`Going`, one per member and event): anyone who can view an event can mark it (`orgEventInterest` in `CalendarApiController`, counts via `OrgEvent::interestToApi()`). Marked events appear on the dashboard ("Deine anstehenden Events", next 28 days) and in the profile (`ProfileEventsCard`), both fed by `myOrgEvents`. The buttons use the generic `ui/AppCountButton` (button with an attached counter).
  - `OrgEvent` many_many `Script` (a script can belong to several events). `ScriptRoleAssignment` (Event + ScriptRole + Member + Date, optional TimeStart/TimeEnd) is the per-day casting; it's separate from the event-independent `ScriptRole.Members` used by the script's focus/learn modes. Casting days are the days of the event's appointments with `Appointment.EnableRoleCasting` (toggle "Rollenplan", like meals/agenda); the appointment dialog shows them in `EventRoleCastingSection`. Each linked script gets a card with `OrgEventRoleTable` (days × roles); in edit mode a cell click opens `OrgEventRoleAssignModal`. Only people who accepted an appointment with role plan that day can be assigned, and nobody can hold two overlapping roles (checked in `SkriptApiController::eventAssignmentStore()`).
  - Site plans & borrowed inventory per event: `InventoryRental.OrgEvent` (has_one; set via `OrgEventID` in `rentalStore`, the request modal opens with an `orgEvent` preset from the event page). An event owns its site plans: `Map.Event` (has_one; `OrgEvent.SitePlans` has_many, cascade-deleted with the event — `Map` in `OrgEvent::toApi()` is the surrounding-area map). They are created empty or as a copy (`Map::copyForEvent()`: layers + POIs, images shared not duplicated, room markers become plain markers across orgs) via `maps/eventPlanCreate`, never shown under "Allgemein", and editable by whoever can manage the event (`canManageMap()`/`canManageLayers()` in `MapsApiController`, besides `MAPS_MANAGE_*`; managing the event = `CALENDAR_MANAGE` or `MAPS_MANAGE_LAYERS`). `OrgEventItemPlacement` (Event + Item + optional Map, `Coordinates` "lat,lng" like `MapPOI`, free-text `Note`, e.g. DMX address) is unique per event and item; deleting a plan only unplaces (notes stay). Items = those in the event's rentals (not rejected/cancelled); placements of items no longer rented stay visible. Event plans open in the normal `views/MapDetail.vue`: `maps/view` adds `event`, `eventPlans` and `items`; the items are an extra last layer (`type: 'item'`, list in `maps/MapEventItems`), movable/placeable only in edit mode and saved with "Speichern" via `eventPlacementSave` (notes and an optional `MarkerText` — max. 4 chars, default the running number — via `OrgEventItemEditModal`, read-only outside edit mode). Item markers and list badges are colored by item title (`utils/eventItems.js`: same name → same color, hex for the canvas). `?edit=1` opens edit mode (used after creating a plan). `InventoryRental::swapItem()` carries the placement over to the replacement item.
  - The Lagepläne totem (`views/Map.vue`) gets a bottom tab pill "Allgemein / Events" (`ui/AppTabNav`, shared with the food totem; `?tab=events`) as soon as `GET /maps` returns `eventPlans` (one entry per event plan for events of the user's orgs). Event cards (`maps/MapEventPlanLink`) show the event title/date; past events go into a collapsed archive.
- **RSVP reset** ("Keine Antwort", e.g. tapping the active answer again in `AppButtonGroup`): the `AppointmentParticipation` is deleted (its existence counts as "answered" in ~30 queries), but notes/custom time/ride are kept in `AppointmentParticipationStash` (one per appointment + member) and restored into the next participation in `CalendarApiController::participation()`. Deleting a participation logs all its fields in the history (`history_empty_values` keeps `RideType` "None" out), so merged history entries stay truthful. Time and ride are only shown for Accept/Maybe (`ParticipantCard`, `EventParticipationForm`), even if stored for Decline.
- **ICS feed** (`Controllers/ICSController`, `/ics?user=<Member.Hash>`): the link is configured in `CalendarIcsLinkModal` and options travel as URL params — `filter` (`all`/`notdeclined`/`invited`/`accepted`, the latter incl. "Vielleicht"; "invited" = listed in `Appointment.InvitedMembers`) `orgs=der_verein,7` (org usernames, ID for orgs without one; only sent when not all of the user's orgs are selected; intersected with their memberships) and `allday=HH:MM-HH:MM` (all-day appointments as one timed entry from first-day start to last-day end). Missing/invalid params fall back to the old behavior, so existing subscriptions keep working. New options go into both places.
- **Feed** (announcements totem, `views/Announcements.vue`, detail page `views/AnnouncementDetail.vue`): `Announcements/FeedPost` replaced the old `Announcement` model completely (old tables are left in the DB, unused). `FeedPost::visibleTo()` decides who sees what (feed, archive of expired posts); `FeedPost::toApi()` is the single API format. Posts made as an organization trigger a push job `new_feed_post` (`PushNotificationService::notifyNewFeedPost()`, member setting `NotifyAnnouncements`; scheduled posts are deferred until their `ReleaseDate`). A post is written as a person or — with `ANNOUNCEMENTS_CREATE` — as an organization, and is `Public` (all of ToTeam) or `Internal` (members of `InternalOrganization`; as an organization always that one). `@username` mentions are resolved server-side (`FeedPost::mentionedMembers()`, only existing usernames) and linked in `FeedPostCard` via `utils/feedContent.js`. Content is light HTML from a TipTap editor (bold/italic/underline only; allowlist in `FeedPost::sanitizeContent()`, checked again client-side); `FeedComposer` suggests people via `announcements/mentionSearch`. Events can be shared into the feed (`FeedPost.Event`, `OrgEventFeedShareModal` on the event page reusing `FeedComposer` with `inline`): the text becomes optional, the post shows a wide `OrgEventCard`, and internal events can only be shared internally for their own organization. Posts can be scheduled (`FeedPost.ReleaseDate`) and expire (`ExpiryDate` → archive): until release only the author (or whoever may post for the organization) sees them; visibility is checked at query time.
- **Controllers** in `Controllers/`:
  - `ApiController.php` — base for all REST endpoints (`Controllers/Api/*Controller.php`). Sets CORS headers and JSON content-type in `init()`; provides `jsonResponse`/`successResponse`/`errorResponse` helpers producing `{success, data}` / `{success: false, error}`; `requireAuth()` returns `Security::getCurrentUser()` or null; `hasPermissionInAnyOrg()` for org-permission checks.
  - `Controllers/Api/*Controller.php` — domain-specific REST controllers (inherit `ApiController`): Announcements, Auth, Calendar, Dashboard, Feedback, Food, Inventory, Links, Maps, Money, OrgRoles, Organizations, Profile, Register, SchedulingPoll, Settings, Tasks. Each self-declares `$url_segment = 'api/v1/<name>'`, and `app/_config/routes.yml` maps the matching `api/v1/<name>//$Action/$ID` routes to it.
  - `VueAppController.php` — serves the SPA shell (`url_segment = 'app'`, catches `$Action/$ID/$OtherID`, renders the `VueApp` template).
  - `BaseController.php` — a **separate**, legacy base (extends `Controller` directly, unrelated to `ApiController`) used by older non-API, non-Vue page controllers (e.g. `CalendarController`, `FoodController`, `LinksController`, `MapController`, etc.). Provides `getUserOrganizationIDs()`, `filterByUserOrganizations()`, `CheckUserPermission()`, `getAppVersion()`.
  - `PageController.php` (`app/src/PageController.php`) extends SilverStripe's `ContentController` — the standard CMS page controller, unrelated to the two bases above.
- **Extensions** in `Extensions/` (e.g., `MemberExtension`) add fields/methods to core SS classes
- API is mounted at `/api/v1/` (see `app/_config/routes.yml`). All API responses use `{ success: bool, data: {} }` or `{ authenticated: bool, user: {} }`
- Run `ddev sake dev/build flush=1` after any model or `_config/*.yml` change

### Frontend (Vue 3 — `app/client/src/vue/`)

- **`app.js`** — creates and mounts the Vue app with router and Pinia
- **`router/index.js`** — a single flat route array (Dashboard, Calendar, Food, Announcements, Profile, Map, Links, Organizations, Tasks, Money, Login, Register, plus detail/create/edit variants), history mode `createWebHistory('/app')`. The global `beforeEach` guard: lazily calls `authStore.checkAuth()` if the user isn't loaded yet, redirects unauthenticated users away from `meta: { requiresAuth: true }` routes to Login (preserving a `redirect` query param), redirects authenticated users away from Login/Register to Dashboard, and blocks routes whose `meta.totem` is disabled for the user's org (via `authStore.hasTotem(totemKey)`).
- **`stores/`** — Pinia stores (Composition API style, `defineStore('name', () => {...})`): `auth.js`, `announcements.js`, `dashboard.js`, `events.js`, `inventory.js`, `money.js`, `notifications.js`, `orgRoles.js`, `organizations.js`, `pageHeader.js`, `tasks.js`, `ui.js`
- **`utils/api.js`** — API helpers `apiGet`, `apiPost`, `apiPut`, `apiDelete`, `apiPostForm` (multipart uploads), `clearCache`/`clearCacheForEndpoint`. API base is hardcoded as `/api/v1` (no env var). Uses **localforage** (store name `toteam`/`api_cache`) for client-side caching with a 5-minute TTL (`CACHE_DURATION`); falls back to stale cached data on network errors.
- **`views/`** — page-level route components
- **Menu badges**: `AppMenu` shows a red count (`.nav_badge`) directly on the menu entry where something needs doing — not on the "Weitere Bereiche" toggle. Currently: Inventar = open rental requests the user may decide on (`inventory/pendingCount`, `inventoryStore.pendingRentals`), Geld = unapproved entries in accounts where the user has `MONEY_APPROVE_ENTRIES` (`money/pendingCount`, `moneyStore.pendingEntries`), Organisationen = open applications in orgs where the user has `ORG_MANAGE_MEMBERS` (`organizations/pendingCount`, `organizationsStore.pendingApplicants`, refreshed after accept/reject in `ApplicantsModal`). Refreshed together with the notification inbox via `refreshBadges()` in `App.vue` (login, app becoming visible, foreground push) and after every rental/entry change in the stores.
- **`components/`** — grouped by folder: `ui/` for generic building blocks (`App*`, `OrganizationPicker`, `MemberPicker`, `AppFileUpload`, `AppCollapse`, …), `layout/` for the app shell (header, menu, notifications, settings/feedback modals), and one folder per domain (`tasks/`, `money/`, `food/`, `calendar/` incl. `calendar/event-dialog/`, `skript/`, `marketing/`, `maps/`, `organizations/`, `rooms/`, `profile/`, `history/`, `announcements/`, `inventory/`). Import via `@components/<folder>/<Name>.vue`. Modals are named `<Object><Action>Modal` (e.g. `TaskCreateModal`, `FoodSuggestModal`); their root CSS class is the kebab-case name (`task-create-modal`).

### Styles (`app/client/src/scss/`)

- `main.scss` / `editor.scss` are the entry points; `main.scss` lists every partial explicitly and its order is the cascade order.
- `base/` — variables, fonts, normalize, typography, `forms.scss` (the shared `.modalform`/`.field` form system), `dialog.scss` (base `<dialog>` styles), `icon-mask.scss`.
- `components/` mirrors `vue/components/` 1:1: `components/tasks/TaskCreateModal.vue` → `scss/components/tasks/TaskCreateModal.scss`. PascalCase files belong to exactly one component; kebab-case files are shared partials (e.g. `money/money-modals.scss`).
- `pages/` — styles scoped to a view (`.section--<Name>Page`), `landingpage/` — the public landing page.
- Dark mode: `body.theme--dark` redefines only the color variables in `base/variables.scss` (names keep their role: `--ColorWhite` = card surface, `--ColorBlack` = text, `--ColorPrimary` turns light blue). State lives in `utils/theme.js` (localStorage `theme`), applied in `app.js` before mount; toggle in `SettingsModal`. Use variables instead of hex colors, and `icon-mask` instead of `<img>` for single-color SVG icons so they follow the theme. Icons inside `AppIconButton` are always 18px (set in `AppIconButton.scss`) — don't override the size per component; if an icon looks too big, its SVG lacks inner padding (fix the `viewBox`, as done for `action_edit.svg`).
- Layout widths only use the four tokens from `base/variables.scss`: `--WidthNarrow` (400px, compact cards/forms), `--WidthMedium` (600px, single-column content like the calendar sheet), `--WidthWide` (1200px, default page width of every `.section` and the header) and `--WidthFull` (100%, edge-to-edge views like the map). Set them on the element that needs the limit, not per page. Modals and floating elements (menu, banners, popups) keep their own sizes.

**Data flow:** Vue component → Pinia action → `utils/api.js` helper → fetch with session cookie → `Api*Controller` (extends `ApiController`) → JSON response → Pinia store → reactive component update

### Event-Karte & Job-Queue

The event page (`views/EventDetail.vue` → `components/events/OrgEventMap.vue`) shows a MapLibre map from **self-hosted** vector tiles — no third-party requests from the browser (GDPR), so no consent dialog.
- `OrgEvent::geocode()` (on write, only when the address changed) fills `Latitude`/`Longitude` server-side via Nominatim (`App\Maps\Geocoder`, max 1 req/s). `GeocodeOrgEventsTask` backfills old events.
- Tiles: a Protomaps/OSM extract as one `.pmtiles` file plus fonts/sprites in `public/tiles/` (gitignored, served directly by the webserver via range requests). `UpdateMapTilesJob` (silverstripe-queuedjobs) downloads it; it runs the download as a detached `setsid` process and polls it in short steps, because the queue restarts jobs whose step count stalls.
- CMS section "Kartendaten" (`Admins/MapTilesAdmin` + singleton `Maps/MapTilesSettings`): status, bbox/zoom/interval, "jetzt aktualisieren". Defaults in `app/_config/maps.yml` (live: Germany, dev: Schleswig-Holstein + Hamburg). The job reschedules itself after each run.
- The API only sends `event.Map` if tiles exist and the event lies inside the downloaded bbox.
- The queue needs a runner: on servers a cron `* * * * * php vendor/bin/sake tasks:ProcessJobQueueTask`; in DDEV the `job-queue` daemon in `.ddev/config.queuedjobs.yaml`. Requires `exec`/`shell_exec`, the `posix` extension, `curl`, `tar` and `setsid` on the server.

### Firebase

Push notifications use Firebase Cloud Messaging. Config keys come from `.env` (`VITE_FIREBASE_*`). A service worker handles background messages.
- Backend: `PushNotificationService` sends data-only messages via the FCM v1 API, authenticated with `firebase-service-account.json` in the repo root (gitignored; path overridable via `FIREBASE_SERVICE_ACCOUNT_PATH`). Tokens FCM reports as invalid are deleted from `NotificationToken`.
- All notifications go through `PushNotificationService::deliver($memberIDs, $orgIDs, $type, …)`: the `notify*()` methods only pick the affected people and the organization(s) the thing belongs to. `deliver()` then keeps only members (`Role` = `member`) of those orgs whose setting `Notify<Type>` (`TYPE_SETTINGS`) is on, and gives each of them an inbox entry (`SavedNotification`) **and** a push to all their devices. The setting is the only switch; `$orgIDs = null` only for personal things without an org (privately lent equipment).
- `NotificationApiController` is mounted at `api/v1/notifications` (Bearer JWT) and the legacy `api/notifications` (session). `inbox` only returns **unread** notifications — marking one as read removes it from the sidebar (`AppNotifications`).
- Frontend: `utils/push.js` (Firebase loaded lazily, no Analytics). `App.vue` registers the device token after login when permission is already granted; permission is requested from the "Aktivieren" button in `SettingsModal` (needs a user gesture; on iOS only as installed PWA). Logout removes the device token.
- `ProcessPendingNotificationsTask` sends the deferred notifications (appointments, polls, feed posts via `PendingNotificationJob`) and needs its own cron on the server.

## Environment Variables

Copy `.env.example` to `.env`. Key variables:
- `SS_DATABASE_*` — MariaDB connection (matches DDEV defaults)
- `SS_ENVIRONMENT_TYPE`, `SS_DEFAULT_ADMIN_USERNAME`/`PASSWORD`, `SS_BASE_URL`
- `VITE_DEV_SERVER_URL` — Vite dev server origin
- `VITE_MANIFEST_PATH`, `VITE_OUTPUT_DIR` — Vite build integration paths for SilverStripe templates
- `VITE_FIREBASE_*` — Firebase project config (API_KEY, AUTH_DOMAIN, PROJECT_ID, STORAGE_BUCKET, MESSAGING_SENDER_ID, APP_ID, MEASUREMENT_ID, VAPID_KEY)
- `MAILER_DSN` — outgoing mail transport
- `NOMINATIM_EMAIL` — optional contact address sent with geocoding requests

There is no `VITE_API_BASE` variable — the frontend API base path (`/api/v1`) is hardcoded in `app/client/src/vue/utils/api.js`.
