const CACHE = 'passimark-v3';

// Only ever cache immutable, content-hashed build output. Documents (HTML) and
// data responses are NEVER cached: an HTML shell pins a specific bundle hash, so
// serving a cached shell re-pins a stale bundle and produces runtime errors that
// match no code on disk. Hashed assets are safe to keep because their filename
// changes whenever their bytes do.
const precache = ['/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(precache))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // The PWA manifest is safe to serve from cache (name is stable, contents are tiny).
    if (url.pathname === '/manifest.json') {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
        return;
    }

    // Only ever touch content-hashed build output. Everything else — documents,
    // Inertia payloads, dev-server paths (/assets, /@vite), data responses — goes
    // straight to the network. Caching a dev bundle would pin a chunk graph that
    // 404s the moment a real build replaces it, blanking the app on the next
    // navigation. Hashed /build/ filenames are immutable, so cache-first is correct.
    if (!url.pathname.startsWith('/build/')) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok && response.type === 'basic') {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));
            }
            return response;
        }))
    );
});
