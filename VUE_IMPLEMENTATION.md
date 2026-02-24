# ToTeam Vue Frontend Implementation

## Übersicht

Dieses Projekt nutzt Vue 3 als Frontend-Framework mit folgenden Technologien:

- **Vue 3** - Progressive JavaScript Framework
- **Vue Router** - Client-side Routing
- **Pinia** - State Management
- **LocalForage** - IndexedDB/LocalStorage Abstraction für Offline-Caching
- **Vite** - Build Tool und Dev Server

## Projektstruktur

```
app/client/src/
├── vue/
│   ├── app.js                 # Vue App Entry Point
│   ├── App.vue                # Root Component
│   ├── router/
│   │   └── index.js           # Vue Router Konfiguration
│   ├── stores/                # Pinia Stores
│   │   ├── auth.js            # Authentication State
│   │   ├── dashboard.js       # Dashboard Data
│   │   └── notices.js         # Notices/Mitteilungen
│   ├── views/                 # Page Components
│   │   ├── Dashboard.vue
│   │   ├── Login.vue
│   │   ├── Notices.vue
│   │   ├── Calendar.vue
│   │   ├── Food.vue
│   │   ├── Profile.vue
│   │   ├── Map.vue
│   │   └── Links.vue
│   ├── components/            # Reusable Components
│   │   └── AppHeader.vue
│   └── utils/                 # Helper Functions
│       └── api.js             # API Client mit Caching
├── scss/                      # Styles (weiterhin verwendet)
└── js/                        # Legacy JavaScript (weiterhin verwendet)

app/src/Controllers/
├── ApiController.php          # Base API Controller
├── VueAppController.php       # Serves Vue SPA
└── Api/
    ├── AuthApiController.php      # /api/v1/auth/*
    ├── DashboardApiController.php # /api/v1/dashboard
    └── NoticesApiController.php   # /api/v1/notices/*

app/templates/
└── VueApp.ss                  # Vue App Template
```

## API Endpunkte

### Authentication
- `GET /api/v1/auth/check` - Check auth status
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/logout` - Logout

### Dashboard
- `GET /api/v1/dashboard` - Get dashboard data

### Notices
- `GET /api/v1/notices` - Get all notices
- `POST /api/v1/notices/{id}/read` - Mark notice as read

## Entwicklung

### Dev Server starten

```bash
ddev exec yarn dev
```

Der Vite Dev Server läuft auf Port 5173 mit Hot Module Replacement (HMR).

### Production Build

```bash
ddev exec yarn build
```

Compiled assets werden nach `app/client/dist/` geschrieben.

## Vue App zugreifen

Die Vue SPA ist unter `/app` erreichbar:

```
https://toteam.ddev.site/app
```

## Features

### Offline-First Architecture

- **API Caching**: Alle GET-Requests werden automatisch gecached (LocalForage)
- **Stale-While-Revalidate**: Bei Netzwerk-Fehlern werden gecachte Daten verwendet
- **Cache-Dauer**: Konfigurierbar per Endpoint (default: 5 Minuten)

### State Management mit Pinia

Stores verwenden die Composition API und bieten:
- Reactive State
- Computed Getters
- Async Actions
- Automatisches Caching

Beispiel:
```javascript
import { useDashboardStore } from '@stores/dashboard'

const dashboardStore = useDashboardStore()
await dashboardStore.fetchDashboardData() // Cached
await dashboardStore.refresh() // Force refresh
```

### Routing

Vue Router mit Navigation Guards für Authentication:

```javascript
// Geschützte Route
{
  path: '/dashboard',
  component: Dashboard,
  meta: { requiresAuth: true }
}
```

### API Client

Der API Client (`utils/api.js`) bietet:
- Automatisches Caching
- Error Handling
- Offline Support

Beispiel:
```javascript
import { apiGet, apiPost } from '@utils/api'

// GET mit Caching (5 min)
const data = await apiGet('/dashboard')

// POST ohne Caching
await apiPost('/notices/123/read')

// Custom Cache-Dauer (10 min)
const data = await apiGet('/data', true, 10 * 60 * 1000)
```

## Styling

Das bestehende SCSS aus `app/client/src/scss/` wird **weiterhin verwendet**.

Die Vue-Komponenten nutzen:
- Globale SCSS-Variablen (z.B. `var(--ColorPrimary)`)
- Bestehende CSS-Klassen (z.B. `.section`, `.button`)
- Scoped Styles für komponenten-spezifisches CSS

## Migration von bestehenden Features

### 1. Neue API-Endpunkte erstellen

```php
<?php
namespace App\Controllers\Api;

use App\Controllers\ApiController;

class MyApiController extends ApiController
{
    private static $url_segment = 'api/v1/myendpoint';
    
    private static $allowed_actions = ['index'];
    
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        
        return $this->jsonResponse([
            'data' => // your data
        ]);
    }
}
```

### 2. Route registrieren

In `app/_config/routes.yml`:

```yaml
'api/v1/myendpoint//$Action': 'App\Controllers\Api\MyApiController'
```

### 3. Pinia Store erstellen

```javascript
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet } from '@utils/api'

export const useMyStore = defineStore('my', () => {
  const data = ref([])
  
  async function fetchData() {
    data.value = await apiGet('/myendpoint')
  }
  
  return { data, fetchData }
})
```

### 4. Vue Component erstellen

```vue
<template>
  <div class="section">
    <h1>{{ myStore.data }}</h1>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useMyStore } from '@stores/my'

const myStore = useMyStore()

onMounted(() => {
  myStore.fetchData()
})
</script>
```

## Service Worker

Der Service Worker (`public/service-worker.js`) cached:
- Static Assets
- API Responses (via Network-First Strategy)
- Firebase Cloud Messaging für Push-Notifications

## Nächste Schritte

1. **Weitere Stores implementieren**: Calendar, Food, Map, etc.
2. **Komponenten erweitern**: Detail-Views, Forms, Dialogs
3. **Service Worker erweitern**: Besseres API-Caching
4. **Tests hinzufügen**: Vitest für Unit/Integration Tests
5. **PWA-Features**: Install Prompt, Offline-Banner

## Hilfreiche Commands

```bash
# Dependencies installieren
ddev exec yarn install

# Dev Server starten
ddev exec yarn dev

# Production Build
ddev exec yarn build

# Silverstripe Cache leeren
ddev exec vendor/bin/sake dev/build flush=all

# Datenbank neu aufbauen
ddev exec vendor/bin/sake dev/build
```

## Debugging

### Vue DevTools

Installiere die Vue DevTools Browser Extension für:
- Component Inspector
- State Inspector (Pinia)
- Router Inspector
- Performance Monitoring

### API Debugging

Öffne Browser Console um API-Requests zu sehen:
```
[API] Using cached data for /dashboard
[API] Network error, using stale cache for /notices
```

### Cache leeren

Über Browser Console:
```javascript
import { clearCache } from '@utils/api'
await clearCache()
```
