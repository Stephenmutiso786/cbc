var CACHE_NAME = 'cbc-school-static-v7';
var STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/pwa.js',
    '/navigation.js?v=2',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/offline.html',
    '/waking.html'
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
        event.respondWith(fetchWithWakeFallback(event.request));
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

function fetchWithWakeFallback(request) {
    // Do not cancel Render's wake request. The branded page is returned while
    // the original request continues, and its retry loads the live response.
    var network = fetch(request);
    var timeout = new Promise(function (resolve) {
        setTimeout(function () { resolve(null); }, 1500);
    });
    return Promise.race([network, timeout]).then(function (response) {
        if (response) return response;
        return caches.match('/waking.html').then(function (fallback) {
            return fallback || caches.match('/offline.html');
        });
    }).catch(function () {
        return caches.match('/waking.html').then(function (response) {
            return response || caches.match('/offline.html');
        });
    });
}
