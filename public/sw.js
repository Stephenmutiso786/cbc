var CACHE_NAME = 'cbc-school-static-v2';
var STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/pwa.js',
    '/navigation.js',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/offline.html'
];

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) {
        return cache.addAll(STATIC_ASSETS);
    }).then(function () {
        return self.skipWaiting();
    }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
    if (event.request.destination === 'document') {
        event.respondWith(fetch(event.request).catch(function () {
            return caches.match('/offline.html');
        }));
        return;
    }

    event.respondWith(fetch(event.request).catch(function () {
        return caches.match(event.request);
    }));
});
