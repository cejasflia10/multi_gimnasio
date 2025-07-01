
self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open('multi-gimnasio').then(function(cache) {
      return cache.addAll([
        // Cliente
        'login_cliente.php',
        'panel_cliente.php',
        'manifest_cliente.json',
        'icono_cliente.png',

        // Profesor
        'login_profesor.php',
        'panel_profesor.php',
        'manifest_profesor.json',
        'icono_profesor.png'
      ]);
    })
  );
});

self.addEventListener('fetch', function(e) {
  e.respondWith(
    caches.match(e.request).then(function(response) {
      return response || fetch(e.request);
    })
  );
});
