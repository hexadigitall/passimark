const CACHE = 'passimark-v2';

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

    // Documents and Inertia payloads: network only. When offline, fail cleanly
    // rather than answering with a shell that references a bundle we no longer have.
    const isDocument = request.mode === 'navigate'
        || request.headers.get('X-Inertia')
        || request.headers.get('Accept')?.includes('text/html');

    if (isDocument) {
        event.respondWith(fetch(request));
        return;
    }

    // Hashed build assets: cache-first is correct because the name is the hash.
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
