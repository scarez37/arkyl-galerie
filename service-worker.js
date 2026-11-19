const CACHE_NAME = 'arkyl-v4';
const STATIC_ASSETS = [
  '/',
  '/style.css',
  '/favicon.ico',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

// Fichiers principaux (pages, scripts, styles) → toujours Network First :
// l'application installée reçoit ainsi les mises à jour au lieu de rester figée sur une ancienne version.
const NETWORK_FIRST = [
  '/',
  '/index.html',
  '/app.js',
  '/auth.js',
  '/api-client.js',
  '/style.css'
];

// Installation : mise en cache des vraies ressources statiques (icônes)
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .catch(err => console.error('[ARKYL SW] ❌ Erreur cache addAll:', err)) // non bloquant
  );
  self.skipWaiting(); // Prendre le contrôle immédiatement
});

// Activation : supprimer TOUS les anciens caches (arkyl-v1, v2, v3…)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(names => Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

// Stratégie fetch
self.addEventListener('fetch', event => {
  const request = event.request;

  // On ne touche qu'aux lectures (GET) : les envois (POST…) vont directement au serveur
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Autres sites (Google, Stripe, polices, API Render…) : le navigateur s'en occupe, sans cache
  if (url.origin !== self.location.origin) return;

  // Appels API PHP du site → toujours réseau, jamais de cache
  // (attention : « /api-client.js » n'est PAS une API, d'où « api_ » avec le tiret bas)
  if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api_') || url.pathname.startsWith('/api/')) return;

  // Pages et scripts principaux → Network First (réseau d'abord, cache si hors ligne)
  const isNavigation = request.mode === 'navigate';
  const isHome = url.pathname === '/' || url.pathname === '/index.html';
  if (isNavigation || NETWORK_FIRST.includes(url.pathname)) {
    event.respondWith(
      fetch(request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          // On mémorise l'accueil (sous la clé « / », quelle que soit l'adresse ?session_id=… )
          // et les scripts/styles ; jamais une autre page à la place de l'accueil.
          if (!isNavigation || isHome) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(isNavigation ? '/' : request, clone));
          }
        }
        return response;
      }).catch(() =>
        // Hors ligne : version en cache (pour une page, l'accueil mémorisé)
        caches.match(isNavigation ? '/' : request).then(cached => cached || Response.error())
      )
    );
    return;
  }

  // Autres fichiers du site (images, icônes…) → Cache First
  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
        }
        return response;
      });
    })
  );
});

// Notifications push
self.addEventListener('push', event => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch (e) { data = { body: event.data.text() }; }
  event.waitUntil(
    self.registration.showNotification(data.title || 'ARKYL', {
      body: data.body || 'Nouvelle œuvre disponible !',
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: data.url || '/' }
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(clients.openWindow(target));
});
