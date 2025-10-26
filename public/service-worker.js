// Simple Service Worker for caching DashboardPage and static assets
const CACHE_NAME = 'dashboard-pwa-v1';
const OFFLINE_URL = '/dashboard-offline.html';
const DASHBOARD_URL = '/dashboard'; // Passe ggf. die URL an

const urlsToCache = [
  DASHBOARD_URL,
  OFFLINE_URL,
  '/favicon.ico',
  '/site.webmanifest',
  // Weitere Assets nach Bedarf
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate' && event.request.url.endsWith(DASHBOARD_URL)) {
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          // Nur cachen, wenn wirklich Dashboard-Seite (Status 200, kein Redirect, kein Registrieren/Login)
          if (
            networkResponse.status === 200 &&
            networkResponse.type === 'basic' &&
            networkResponse.url.endsWith(DASHBOARD_URL)
          ) {
            networkResponse.clone().text().then(text => {
              // Prüfe, ob die Seite nicht die Registrieren-/Login-Seite ist
              if (!text.includes('Registrieren') && !text.includes('Einloggen')) {
                caches.open(CACHE_NAME).then(cache => {
                  cache.put(DASHBOARD_URL, networkResponse.clone());
                });
              }
            });
          }
          return networkResponse;
        })
        .catch(() => {
          // Offline: Dashboard aus Cache oder Offline-Seite
          return caches.match(DASHBOARD_URL).then(cached => {
            return cached || caches.match(OFFLINE_URL);
          });
        })
    );
  } else if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match(OFFLINE_URL))
    );
  } else {
    event.respondWith(
      caches.match(event.request).then(response => {
        return response || fetch(event.request);
      })
    );
  }
});
