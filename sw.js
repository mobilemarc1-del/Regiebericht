// Service Worker für Regiebericht PWA
const CACHE_NAME = 'regiebericht-v1';
const ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './icon-192.png',
  './icon-512.png',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js'
];

// Installation - Cache befüllen
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache geöffnet');
        return cache.addAll(ASSETS);
      })
      .then(function() {
        return self.skipWaiting();
      })
  );
});

// Aktivierung - Alte Caches löschen
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Alter Cache gelöscht:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// Fetch - Cache-First Strategie
self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // Cache Hit - aus Cache liefern
        if (response) {
          return response;
        }
        
        // Nicht im Cache - vom Netzwerk holen
        return fetch(event.request).then(function(response) {
          // Keine gültige Response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          
          // Response klonen und cachen
          var responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then(function(cache) {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        });
      })
      .catch(function() {
        // Offline und nicht im Cache
        console.log('Offline - Resource nicht verfügbar');
      })
  );
});
