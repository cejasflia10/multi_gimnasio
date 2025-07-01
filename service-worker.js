self.addEventListener('install', function(e) {
  console.log('SW instalado');
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  console.log('SW activado');
  e.waitUntil(clients.claim());
});

// No guardar en cache
self.addEventListener('fetch', function(event) {
  event.respondWith(fetch(event.request));
});
