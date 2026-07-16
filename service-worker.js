const CACHE_NAME = 'arkyl-v3';  // ⬆️ Version incrémentée — invalide tous les anciens caches
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/style.css',
  '/logo-512.png',
  '/favicon-192.png',
  '/favicon-32.png',
  '/favicon.ico',
  '/galerie_publique.html',
  '/connexion.html',
  '/inscription.html',
  '/artist_dashboard.html'
  // app.js retiré des assets statiques → toujours chargé depuis le réseau
];

// Fichiers JS/HTML principaux → toujours Network First (jamais en cache)
const NETWORK_FIRST = [
  '/app.js',
  '/auth.js',
  '/2-pages-content.html',
  '/3-admin-modales.html',
  '/1-header-nav.html',
  '/sections.html'
];

// Installation : mise en cache UNIQUEMENT des vraies ressources statiques (images, icons)
self.addEventListener('install', event => {
  console.log('[ARKYL SW] 🔧 Installation en cours...');
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[ARKYL SW] ✅ Cache v3 ouvert — assets statiques uniquement');
      return cache.addAll(STATIC_ASSETS).catch(err => {
        console.error('[ARKYL SW] ❌ Erreur cache addAll:', err);
        // Ne pas échouer l'installation pour quelques assets manquants
        return Promise.resolve();
      });
    })
  );
  self.skipWaiting(); // Prendre le contrôle immédiatement
  console.log('[ARKYL SW] ✅ Installation complète, skipWaiting déclenché');
});

// Activation : supprimer TOUS les anciens caches (arkyl-v1, arkyl-v2, etc.)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames =>
      Promise.all(
        cacheNames
          .filter(name => name !== CACHE_NAME)
          .map(name => {
            console.log('[ARKYL SW] Suppression cache obsolète:', name);
            return caches.delete(name);
          })
      )
    )
  );
  self.clients.claim();
});

// Stratégie fetch
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // 1. Appels API PHP → toujours réseau, jamais de cache
  if (url.pathname.startsWith('/api') || url.pathname.includes('.php') ||
      url.hostname.includes('onrender.com') || url.hostname.includes('countriesnow')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // 2. Fichiers JS/HTML principaux → Network First (on essaie le réseau, fallback cache)
  const isNetworkFirst = NETWORK_FIRST.some(f => url.pathname === f || url.pathname.endsWith(f));
  if (isNetworkFirst) {
    event.respondWith(
      fetch(event.request).then(response => {
        if (response && response.status === 200) {
          // Mettre à jour le cache avec la version fraîche
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => {
        // Hors ligne : retourner version cachée si dispo
        return caches.match(event.request);
      })
    );
    return;
  }

  // 3. Autres assets (images, CSS, icons) → Cache First
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      });
    }).catch(() => {
      if (event.request.destination === 'document') {
        return caches.match('/index.html');
      }
    })
  );
});

// Notifications push
self.addEventListener('push', event => {
  if (!event.data) return;
  const data = event.data.json();
  self.registration.showNotification(data.title || 'ARKYL', {
    body: data.body || 'Nouvelle œuvre disponible !',
    icon: '/logo-512.png',
    badge: '/favicon-192.png',
    data: { url: data.url || '/' }
  });
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
