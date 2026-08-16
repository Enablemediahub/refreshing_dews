// Service Worker for Painlesslyf PWA
const CACHE_NAME = 'painlesslyf-v1';

// Assets to cache on install
const urlsToCache = [
  '/painlesslyf/',
  '/painlesslyf/index.php',
  '/painlesslyf/blog.php',
  '/painlesslyf/audio.php',
  '/painlesslyf/about.php',
  '/painlesslyf/contact.php',
  '/painlesslyf/manifest.json',
  '/painlesslyf/assets/css/style.css',
  '/painlesslyf/assets/js/main.js',
  '/painlesslyf/assets/logo/painlesslyf-logo.png',
  'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
  'https://unpkg.com/aos@2.3.1/dist/aos.css',
  'https://unpkg.com/aos@2.3.1/dist/aos.js'
];

// Install event - cache core assets
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching files');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('Service Worker: Installation complete');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Installation failed', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('Service Worker: Clearing old cache', cache);
            return caches.delete(cache);
          }
        })
      );
    })
    .then(() => {
      console.log('Service Worker: Activation complete');
      return self.clients.claim();
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  if (!event.request.url.startsWith(self.location.origin) && 
      !event.request.url.includes('fonts.googleapis.com') &&
      !event.request.url.includes('cdnjs.cloudflare.com') &&
      !event.request.url.includes('unpkg.com')) {
    return;
  }
  
  if (event.request.method !== 'GET') {
    return;
  }
  
  const url = new URL(event.request.url);
  
  if (url.pathname.includes('/admin/') || 
      url.pathname.includes('/includes/') ||
      url.pathname.includes('blog-post') ||
      url.pathname.includes('audio-player.php')) {
    
    event.respondWith(
      networkFirst(event.request)
    );
  } 
  else if (url.pathname.includes('/assets/') || 
           url.pathname.includes('/uploads/') ||
           url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?)$/)) {
    
    event.respondWith(
      cacheFirst(event.request)
    );
  } 
  else {
    event.respondWith(
      staleWhileRevalidate(event.request)
    );
  }
});

async function cacheFirst(request) {
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.log('Cache First: Network request failed', request.url, error);
    if (request.url.match(/\.(png|jpg|jpeg|gif|svg)$/)) {
      return caches.match('/painlesslyf/assets/images/default-post.jpg');
    }
    return new Response('Network error', { status: 408 });
  }
}

async function networkFirst(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.log('Network First: Failed to fetch, falling back to cache', request.url, error);
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    return new Response('You are offline', { 
      status: 503,
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

async function staleWhileRevalidate(request) {
  const cachedResponse = await caches.match(request);
  
  const networkPromise = fetch(request)
    .then(networkResponse => {
      if (networkResponse && networkResponse.status === 200) {
        const cache = caches.open(CACHE_NAME);
        cache.then(c => c.put(request, networkResponse.clone()));
      }
      return networkResponse;
    })
    .catch(error => {
      console.log('Stale While Revalidate: Network request failed', request.url, error);
    });
  
  return cachedResponse || networkPromise;
}

self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync', event);
  if (event.tag === 'sync-posts') {
    event.waitUntil(syncPosts());
  }
});

self.addEventListener('push', event => {
  console.log('Service Worker: Push notification received', event);
  
  const options = {
    body: event.data.text(),
    icon: '/painlesslyf/assets/logo/painlesslyf-logo.png',
    badge: '/painlesslyf/assets/logo/painlesslyf-logo.png',
    vibrate: [200, 100, 200],
    data: {
      url: '/painlesslyf/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification('Painlesslyf', options)
  );
});

self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification clicked', event);
  
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});

self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match('/painlesslyf/offline.html');
        })
    );
  }
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

console.log('Service Worker: Registered successfully');
