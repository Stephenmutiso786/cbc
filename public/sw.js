var CACHE_NAME = 'cbc-school-static-v5';
var STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/pwa.js',
    '/navigation.js',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/offline.html'
];

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) {
        // Keep one unavailable asset from preventing the worker from
        // installing during a deploy or a cold start.
        return Promise.all(STATIC_ASSETS.map(function (asset) {
            return cache.add(asset).catch(function (error) {
                console.warn('PWA asset was not cached:', asset, error);
            });
        }));
    }).then(function () {
        return self.skipWaiting();
    }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key !== CACHE_NAME;
            }).map(function (key) {
                return caches.delete(key);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
    if (event.request.destination === 'document') {
        event.respondWith(fetch(event.request).catch(function () {
            return caches.match('/offline.html');
        }));
        return;
    }

    event.respondWith(fetch(event.request).then(function (response) {
        if (response.ok) {
            var copy = response.clone();
            caches.open(CACHE_NAME).then(function (cache) {
                cache.put(event.request, copy);
            });
        }
        return response;
    }).catch(function () {
        return caches.match(event.request);
    }));
});
