const STATIC_CACHE = 'shelter-static-v1.2.6';
const RUNTIME_CACHE = 'shelter-runtime-v1.2.6';
const IMAGE_CACHE = 'shelter-images-v1.2.7';
const DATA_CACHE = 'shelter-data-v1.2.7';
const MAP_TILES_CACHE = 'shelter-maptiles-v1.2.7';
const CORE_ASSETS = [
  '/',
  'index.php',
  'assets/css/style.css',
  'assets/img/logo.png',
  'manifest.json'
];
const JSON_DATA_FILES = [
  'assets/data/shelters.json',
  'assets/data/hotlines.json',
  'assets/data/disasters.json',
  'assets/data/mylocation.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    Promise.all([
      // Cache core static assets
      caches.open(STATIC_CACHE).then(cache => {
        return Promise.allSettled(
          CORE_ASSETS.map(asset => 
            cache.add(asset).catch(err => {
              return null;
            })
          )
        );
      }),
      // Cache JSON data files for offline fallback
      caches.open(DATA_CACHE).then(cache => {
        return Promise.allSettled(
          JSON_DATA_FILES.map(file => 
            cache.add(file).catch(err => {
              console.warn(`Failed to cache ${file}:`, err);
              return null;
            })
          )
        );
      })
    ])
    .then(() => self.skipWaiting())
    .catch(err => {
      console.error('Service Worker install failed:', err);
      self.skipWaiting();
    })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => {
      if (![STATIC_CACHE, RUNTIME_CACHE, IMAGE_CACHE, DATA_CACHE, MAP_TILES_CACHE].includes(k)) return caches.delete(k);
    }))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;
  const isApi = isSameOrigin && url.pathname.startsWith('/app/api.php');
  const isStatic = isSameOrigin && CORE_ASSETS.some(p => url.pathname.endsWith(p));
  const isJsonData = isSameOrigin && url.pathname.startsWith('/assets/data/') && url.pathname.endsWith('.json');
  const isDocument = request.mode === 'navigate';

  // Handle JSON data files - serve from cache when offline
  if (isJsonData) {
    event.respondWith(
      caches.open(DATA_CACHE).then(cache => {
        return fetch(request.clone())
          .then(response => {
            // If online and got response, update cache
            if (response.status === 200) {
              const responseClone = response.clone();
              cache.put(request, responseClone);
            }
            return response;
          })
          .catch(() => {
            // Offline - serve from cache
            return cache.match(request).then(cached => {
              if (cached) {
                return cached;
              }
              // Return empty array/object if not cached
              const emptyData = url.pathname.includes('shelters.json') ? [] :
                               url.pathname.includes('hotlines.json') ? [] :
                               url.pathname.includes('disasters.json') ? [] :
                               url.pathname.includes('mylocation.json') ? {} : null;
              return new Response(JSON.stringify(emptyData), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
              });
            });
          });
      })
    );
    return;
  }

  if (isApi) {
    event.respondWith(
      fetch(request.clone())
        .then(response => {
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(RUNTIME_CACHE).then(cache => {
              cache.put(request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          return caches.match(request).then(cached => {
            if (cached) return cached;
            // Try to serve JSON fallback data if API fails
            return serveJsonFallback(url);
          });
        })
    );
    return;
  }

  if (isDocument) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(STATIC_CACHE).then(cache => {
              cache.put(request, responseClone);
            });
          }
          return response;
        }).catch(() => {
          return new Response('<!DOCTYPE html><html><head><title>Offline</title></head><body><h1>You are offline</h1><p>Please check your internet connection.</p></body></html>', {
            headers: { 'Content-Type': 'text/html' }
          });
        });
      })
    );
    return;
  }

  if (isStatic) {
    event.respondWith(
      caches.match(request, { ignoreSearch: true }).then(cached => {
        if (cached) return cached;
        return fetch(request).then(resp => {
          const copy = resp.clone();
          caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
          return resp;
        });
      })
    );
    return;
  }

  // Handle images (both same-origin and cross-origin from API subdomain)
  const isImage = request.destination === 'image' || 
                  url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i) ||
                  (url.hostname.includes('evacuationshelter.online') && url.pathname.includes('/assets/img/shelters/'));
  
  if (isImage) {
    event.respondWith(
      caches.open(IMAGE_CACHE).then(cache => {
        return cache.match(request).then(cached => {
          if (cached) return cached;
          // Fetch image normally - images loaded via <img> tags don't have CORS restrictions
          return fetch(request).then(response => {
            if (response.status === 200) {
              const responseClone = response.clone();
              cache.put(request, responseClone);
            }
            return response;
          }).catch(() => {
            // Return a placeholder image if fetch fails
            return new Response(
              '<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="200" height="200" fill="#f0f0f0"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" font-family="Arial" font-size="14" fill="#999">Image not available offline</text></svg>',
              { headers: { 'Content-Type': 'image/svg+xml' } }
            );
          });
        });
      })
    );
    return;
  }

  if (isSameOrigin) {
    event.respondWith(
      fetch(request).catch(() => {
        return new Response('', { status: 404, statusText: 'Not Found' });
      })
    );
  }
});

// Helper function to serve JSON fallback data when API is offline
function serveJsonFallback(url) {
  const searchParams = new URLSearchParams(url.search);
  let jsonFile = null;
  
  // Map API endpoints to JSON files
  if (searchParams.has('getAllShelters')) {
    jsonFile = 'assets/data/shelters.json';
  } else if (searchParams.has('getEmergencyHotlines')) {
    jsonFile = 'assets/data/hotlines.json';
  } else if (searchParams.has('getDisasters')) {
    jsonFile = 'assets/data/disasters.json';
  } else if (searchParams.has('getCurrentLocation')) {
    jsonFile = 'assets/data/mylocation.json';
  }
  
  if (jsonFile) {
    return caches.open(DATA_CACHE).then(cache => {
      return cache.match(new Request(jsonFile)).then(cached => {
        if (cached) {
          return cached.json().then(data => {
            // Format response to match API response structure
            return new Response(JSON.stringify({
              status: 'success',
              message: 'Data fetched from offline cache',
              data: data
            }), {
              status: 200,
              headers: { 'Content-Type': 'application/json' }
            });
          });
        }
        // Return empty response if JSON file not cached
        const emptyData = jsonFile.includes('shelters.json') ? [] :
                         jsonFile.includes('hotlines.json') ? [] :
                         jsonFile.includes('disasters.json') ? [] :
                         jsonFile.includes('mylocation.json') ? {} : null;
        return new Response(JSON.stringify({
          status: 'success',
          message: 'Data fetched from offline cache',
          data: emptyData
        }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' }
        });
      });
    });
  }
  
  // Default error response
  return new Response(JSON.stringify({ 
    status: 'error', 
    message: 'Offline - No cached data available',
    data: null 
  }), {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': 'application/json' }
  });
}
