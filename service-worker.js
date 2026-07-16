const CACHE_NAME = 'arkyl-v1';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/style.css',
  '/app.js',
  '/auth.js',
  '/logo-512.png',
  '/favicon-192.png',
  '/favicon-32.png',
  '/favicon.ico',
  '/galerie_publique.html',
  '/connexion.html',
  '/inscription.html',
  '/artist_dashboard.html'
];

// Installation : mise en cache des ressources statiques
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[ARKYL SW] Mise en cache des ressources statiques');
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

// Activation : nettoyage des anciens caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames =>
      Promise.all(
        cacheNames
          .filter(name => name !== CACHE_NAME)
          .map(name => {
            console.log('[ARKYL SW] Suppression ancien cache:', name);
            return caches.delete(name);
          })
      )
    )
  );
  self.clients.claim();
});

// Stratégie : Network First pour les API, Cache First pour les assets
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Les appels API → toujours réseau (pas de cache)
  if (url.pathname.startsWith('/api') || url.pathname.includes('.php')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Assets statiques → Cache First
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // On met en cache les nouvelles ressources valides
        if (response && response.status === 200 && response.type === 'basic') {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
        }
        return response;
      });
    }).catch(() => {
      // Hors ligne : retourner la page d'accueil en cache
      if (event.request.destination === 'document') {
        return caches.match('/index.html');
      }
    })
  );
});

// Notifications push (pour plus tard)
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
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});
