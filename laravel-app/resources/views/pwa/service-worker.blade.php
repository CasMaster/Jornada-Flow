const CACHE_NAME = @json($cacheName);
const CACHE_PREFIX = 'brand-shell-';
const OFFLINE_URL = @json($offlineUrl);
const CORE_ASSETS = @json(array_values(array_unique([...$assets, $offlineUrl])));

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(CORE_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (url.origin === self.location.origin && url.pathname.includes('/assets/')) {
        event.respondWith(
            caches.match(request).then(cached => cached || fetch(request).then(response => {
                if (response.ok) caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
                return response;
            }))
        );
    }
});
