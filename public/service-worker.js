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

// Firebase Cloud Messaging
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// Initialize Firebase in the service worker
firebase.initializeApp({
  apiKey: "AIzaSyD21Ric9FEwsDEgh1B_rpW7CJ4Ywo2LTQg",
  authDomain: "toteam-app.firebaseapp.com",
  projectId: "toteam-app",
  storageBucket: "toteam-app.firebasestorage.app",
  messagingSenderId: "580532973038",
  appId: "1:580532973038:web:d2630cb1f792d96a9872ff",
  measurementId: "G-V9YPHXFJXH"
});

// Retrieve Firebase Messaging instance
const messaging = firebase.messaging();

console.log('[service-worker.js] Firebase Messaging initialized');

// Handle background messages (data-only messages)
messaging.onBackgroundMessage((payload) => {
  console.log('[service-worker.js] Received background message ', payload);

  // Handle data-only messages
  const notificationTitle = payload.data?.title || 'Neue Benachrichtigung';
  const notificationOptions = {
    body: payload.data?.body || '',
    icon: '/_resources/app/client/icons/icon_192.png',
    badge: '/_resources/app/client/icons/ToTeam-Favicon-x64.png',
    data: {
      url: payload.data?.url || '/'
    }
  };

  console.log('[service-worker.js] Showing notification:', notificationTitle);

  return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
  console.log('[service-worker.js] Notification click received.');

  event.notification.close();

  const urlToOpen = event.notification.data?.url || '/';

  event.waitUntil(
    clients.openWindow(urlToOpen)
  );
});

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
            // Clone once before reading the body
            const clonedResponse = networkResponse.clone();
            clonedResponse.text().then(text => {
              // Prüfe, ob die Seite nicht die Registrieren-/Login-Seite ist
              if (!text.includes('Registrieren') && !text.includes('Einloggen')) {
                caches.open(CACHE_NAME).then(cache => {
                  // Use the original networkResponse for caching
                  cache.put(DASHBOARD_URL, networkResponse.clone());
                });
              }
            }).catch(err => {
              console.log('[service-worker.js] Error checking response:', err);
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
