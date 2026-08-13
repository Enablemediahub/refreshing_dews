// Service Worker for Refreshing Dews PWA
const CACHE_NAME = 'refreshing-dews-v1';

// Assets to cache on install
const urlsToCache = [
  '/refreshing_dews/',
  '/refreshing_dews/index.php',
  '/refreshing_dews/blog.php',
  '/refreshing_dews/audio.php',
  '/refreshing_dews/about.php',
  '/refreshing_dews/contact.php',
  '/refreshing_dews/manifest.json',
  '/refreshing_dews/assets/css/style.css',
  '/refreshing_dews/assets/js/main.js',
  '/refreshing_dews/assets/logo/refreshing-dews-logo.png',
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
  // Skip cross-origin requests like analytics, etc.
  if (!event.request.url.startsWith(self.location.origin) && 
      !event.request.url.includes('fonts.googleapis.com') &&
      !event.request.url.includes('cdnjs.cloudflare.com') &&
      !event.request.url.includes('unpkg.com')) {
    return;
  }
  
  // Skip POST requests
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Handle different strategies based on URL patterns
  const url = new URL(event.request.url);
  
  // API-like requests - network first
  if (url.pathname.includes('/admin/') || 
      url.pathname.includes('/includes/') ||
      url.pathname.includes('blog-post.php') ||
      url.pathname.includes('audio-player.php')) {
    
    event.respondWith(
      networkFirst(event.request)
    );
  } 
  // Static assets - cache first
  else if (url.pathname.includes('/assets/') || 
           url.pathname.includes('/uploads/') ||
           url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?)$/)) {
    
    event.respondWith(
      cacheFirst(event.request)
    );
  } 
  // HTML pages - stale while revalidate
  else {
    event.respondWith(
      staleWhileRevalidate(event.request)
    );
  }
});

// Cache First Strategy
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
    // Return a fallback for images
    if (request.url.match(/\.(png|jpg|jpeg|gif|svg)$/)) {
      return caches.match('/refreshing_dews/assets/images/default-post.jpg');
    }
    return new Response('Network error', { status: 408 });
  }
}

// Network First Strategy
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

// Stale While Revalidate Strategy
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
  
  // Return cached response immediately if available, otherwise wait for network
  return cachedResponse || networkPromise;
}

// Background Sync for offline actions (optional)
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync', event);
  if (event.tag === 'sync-posts') {
    event.waitUntil(syncPosts());
  }
});

// Push notifications (optional)
self.addEventListener('push', event => {
  console.log('Service Worker: Push notification received', event);
  
  const options = {
    body: event.data.text(),
    icon: '/refreshing_dews/assets/logo/refreshing-dews-logo.png',
    badge: '/refreshing_dews/assets/logo/refreshing-dews-logo.png',
    vibrate: [200, 100, 200],
    data: {
      url: '/refreshing_dews/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification('Refreshing Dews', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification clicked', event);
  
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});

// Handle offline fallback page
self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match('/refreshing_dews/offline.html');
        })
    );
  }
});

// Cache version management
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

console.log('Service Worker: Registered successfully');
