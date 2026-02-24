# ToTeam - Vue 3 Frontend Setup

## Projekt-Übersicht

ToTeam ist eine Silverstripe 6 Anwendung mit einem Vue 3 Single Page Application (SPA) Frontend. Das Frontend ist unter der Route `/app` verfügbar und nutzt API-Endpunkte zur Kommunikation mit dem Backend.

## Technologie-Stack

### Frontend
- **Vue 3** (Composition API mit `<script setup>`)
- **Vue Router 4** (Client-seitiges Routing, Base: `/app`)
- **Pinia 2** (State Management)
- **LocalForage 1.10** (Offline-Caching für API-Requests)
- **Vite 5.0.8** (Build Tool)
- **Firebase 12.6.0** (Push Notifications - pre-existing)

### Backend
- **Silverstripe 6**
- **PHP 8.1+**
- **MariaDB/MySQL**
- **DDEV** (Development Environment)

## Projektstruktur

```
app/
├── client/
│   ├── dist/                    # Vite Build-Output (gitignored)
│   ├── icons/                   # Icons & Totems
│   │   ├── totems/             # Navigation Icons (dashboard_totem.png, etc.)
│   │   └── actions/            # Action Icons (action_logout.svg, etc.)
│   └── src/
│       ├── js/                 # Legacy JavaScript (main.js für traditionelle Pages)
│       ├── scss/               # Globale Styles (werden weiterhin verwendet!)
│       └── vue/                # Vue 3 Application
│           ├── app.js          # Vue Entry Point
│           ├── App.vue         # Root Component
│           ├── router/
│           │   └── index.js    # Vue Router Config
│           ├── stores/         # Pinia Stores
│           │   ├── auth.js     # Authentication State
│           │   ├── dashboard.js # Dashboard Data
│           │   └── notices.js  # Notices/Mitteilungen
│           ├── components/
│           │   ├── AppHeader.vue    # Navigation (Primary + Secondary Menu)
│           │   └── IntroBar.vue     # Page Title Component
│           ├── views/          # Route Views
│           │   ├── Dashboard.vue
│           │   ├── Login.vue
│           │   ├── Notices.vue
│           │   ├── Calendar.vue
│           │   ├── Food.vue
│           │   ├── Map.vue
│           │   ├── Links.vue
│           │   └── Profile.vue
│           └── utils/
│               └── api.js      # API Client mit Caching
├── src/
│   └── Controllers/
│       ├── ApiController.php          # Base API Controller (CORS, Auth)
│       ├── VueAppController.php       # Serves Vue SPA
│       └── Api/
│           ├── AuthApiController.php      # /api/v1/auth/*
│           ├── DashboardApiController.php # /api/v1/dashboard/*
│           └── NoticesApiController.php   # /api/v1/notices
├── templates/
│   └── VueApp.ss              # Vue SPA Template
└── _config/
    └── routes.yml             # Silverstripe Routes

vite.config.js                 # Vite Configuration
package.json                   # Node Dependencies
```

## Wichtige Design-Entscheidungen

### 1. **Styling: Externe SCSS, NICHT Component-Scoped**
- ⚠️ **Wichtig:** Styles sind NICHT in Vue-Components (`<style scoped>`)
- Alle Styles bleiben in `app/client/src/scss/`
- Vue-Components verwenden existierende CSS-Klassen
- Grund: Konsistenz mit bestehendem Silverstripe-Frontend

### 2. **Icon-Handling**
- Icons werden als ES-Module in Components importiert
- **Richtige Namen** (deutsch): `essen_totem.png`, `karten_totem.png`, `downloads_totem.png`
- Grund: Vite benötigt statische Imports für Build-Time-Optimierung

### 3. **Secondary Menu mit Body-Class**
- Toggles `secnav--open` Klasse am `<body>` Element
- Grund: Kompatibilität mit existierendem SCSS-Styling
- CSS-Transition statt Vue `v-if`

### 4. **API-Caching mit LocalForage**
- GET-Requests werden gecacht (Default: 5 Minuten)
- Auth-Check wird NICHT gecacht (`useCache: false`)
- Cache wird bei Mutations (POST/PUT/DELETE) geleert

### 5. **Session-Persistenz**
- `remember=true` bei Login-API
- Router checked Auth nur wenn `authStore.user` nicht vorhanden

## Entwicklungs-Workflow

### Setup (neuer Rechner)

```bash
# DDEV starten
ddev start

# Dependencies installieren
ddev composer install
ddev yarn install

# Database bauen
ddev exec vendor/bin/sake dev/build flush=1

# Frontend bauen
ddev yarn build
```

### Development

```bash
# Vite Dev Server (Hot Module Replacement)
ddev yarn dev

# Bei Änderungen in SCSS/Vue:
# - Dev Server läuft: HMR funktioniert automatisch
# - Für Production Build:
ddev yarn build

# Nach Backend-Änderungen (PHP):
ddev exec vendor/bin/sake dev/build flush=1
```

### Wichtige Befehle

```bash
# Vite Build für Production
ddev yarn build

# Silverstripe Cache leeren
ddev exec vendor/bin/sake dev/build flush=1

# Composer Autoload neu generieren
ddev composer dump-autoload

# Logs anschauen
ddev logs

# In Container einloggen
ddev ssh
```

## API-Endpunkte

### Authentication
- `GET /api/v1/auth/check` - Aktuellen User prüfen
- `POST /api/v1/auth/login` - Login (Body: `{email, password}`)
- `POST /api/v1/auth/logout` - Logout

### Dashboard
- `GET /api/v1/dashboard` - Dashboard-Daten

### Notices
- `GET /api/v1/notices` - Alle Notices mit Kategorien
- `POST /api/v1/notices/{ID}/read` - Notice als gelesen markieren

### Response Format
```json
{
  "success": true,
  "data": { ... }
}
```

Oder bei Fehler:
```json
{
  "success": false,
  "error": "Fehlermeldung"
}
```

## Routing-Konfiguration

### `app/_config/routes.yml`
```yaml
SilverStripe\Control\Director:
  rules:
    # Vue App (catch-all für /app/*)
    'app//$Action/$ID/$OtherID': 'App\Controllers\VueAppController'
    
    # API Routes (müssen VOR generischen Routes stehen!)
    'api/v1/auth//$Action': 'App\Controllers\Api\AuthApiController'
    'api/v1/dashboard//$Action': 'App\Controllers\Api\DashboardApiController'
    'api/v1/notices': 'App\Controllers\Api\NoticesApiController'
    
    # Traditional Silverstripe Routes
    'dashboard': 'App\Controllers\DashboardController'
    # ...
```

**Wichtig:** API-Routes müssen ÜBER den generischen Routes stehen!

### Vue Router (`app/client/src/vue/router/index.js`)
```javascript
// Base Path: '/app'
const router = createRouter({
  history: createWebHistory('/app'),
  routes: [
    { path: '/', name: 'Dashboard', component: Dashboard },
    { path: '/login', name: 'Login', component: Login, meta: { requiresAuth: false } },
    // ...
  ]
})
```

## Vite-Konfiguration (`vite.config.js`)

```javascript
export default {
  plugins: [vue()],
  build: {
    rollupOptions: {
      input: {
        main: 'app/client/src/js/main.js',
        app: 'app/client/src/vue/app.js',        // Vue Entry
        'main-styles': 'app/client/src/scss/main.scss',
        'editor-styles': 'app/client/src/scss/editor.scss'
      }
    }
  },
  resolve: {
    alias: {
      '@components': '/app/client/src/vue/components',
      '@views': '/app/client/src/vue/views',
      '@stores': '/app/client/src/vue/stores',
      '@utils': '/app/client/src/vue/utils'
    }
  }
}
```

## Häufige Probleme & Lösungen

### 1. "Action 'login' isn't available"
- **Problem:** Route matcht falschen Controller
- **Lösung:** Prüfe Reihenfolge in `routes.yml` - spezifische Routes zuerst!

### 2. "That record was not found" bei GridField
- **Problem:** Versuch, Related Records zu erstellen bevor Parent gespeichert ist
- **Lösung:** `if ($this->isInDB())` Check vor GridField

### 3. 500 Error bei API-Calls
- **Problem:** Autoloader kennt Klasse nicht oder Route falsch
- **Lösungen:**
  - `ddev composer dump-autoload`
  - `ddev exec vendor/bin/sake dev/build flush=1`
  - Backslashes in `routes.yml` prüfen (einfach `\`, nicht doppelt `\\`)

### 4. Icons werden nicht gefunden (404)
- **Problem:** `/_resources/` Pfade funktionieren nicht im Vite Build
- **Lösung:** Icons als ES-Module importieren:
  ```javascript
  import dashboardTotem from '../../../icons/totems/dashboard_totem.png'
  ```

### 5. Secondary Menu öffnet nicht
- **Problem:** `v-if` entfernt Element aus DOM, CSS benötigt aber permanentes Element
- **Lösung:** Body-Class `.secnav--open` togglen statt `v-if`

### 6. Session geht bei Reload verloren
- **Problem:** Auth-Check wird gecacht oder `remember` nicht gesetzt
- **Lösungen:**
  - `remember=true` in Login-API
  - `useCache: false` für Auth-Check
  - Router checked nur wenn `!authStore.user`

## Production Deployment

1. **Build Assets:**
   ```bash
   ddev yarn build
   ```

2. **Silverstripe Build:**
   ```bash
   ddev exec vendor/bin/sake dev/build flush=1
   ```

3. **Assets committen:**
   - `app/client/dist/` ist normalerweise in `.gitignore`
   - Für Production: Assets in Repo oder separater Build-Step

## ViteHelper Integration

Das Projekt nutzt `atwx/silverstripe-vitehelper`:

### In Templates (`VueApp.ss`):
```html
<!-- Vite Client (HMR in Dev Mode) -->
$ViteClient.RAW

<!-- Load Assets -->
$Vite('app/client/src/scss/main.scss')
$Vite('app/client/src/vue/app.js')
```

### Im Controller NICHT Requirements nutzen:
```php
// ❌ FALSCH - Requirements überschreibt ViteHelper
Requirements::javascript('...');

// ✅ RICHTIG - Template handhabt Assets
return $this->renderWith('VueApp');
```

## Nützliche Debugging-Tipps

### Browser DevTools
- Vue DevTools Extension installieren
- Network Tab: API-Calls beobachten
- Console: Pinia Store State prüfen

### API Testing
```bash
# Auth check
ddev exec "curl -s http://localhost/api/v1/notices"

# Mit Session (in Container)
ddev ssh
curl -c cookies.txt -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
curl -b cookies.txt http://localhost/api/v1/notices
```

### Cache leeren
```javascript
// In Browser Console
localStorage.clear()
indexedDB.deleteDatabase('toteam')
```

## Kontakt & Weiterführende Infos

- **Silverstripe Docs:** https://docs.silverstripe.org/
- **Vue 3 Docs:** https://vuejs.org/
- **Pinia Docs:** https://pinia.vuejs.org/
- **Vite Docs:** https://vitejs.dev/

---

**Letzte Aktualisierung:** 24. Februar 2026  
**Status:** Production-ready für Dashboard, Notices, Login
