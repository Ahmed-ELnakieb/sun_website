// Sun Trading Company - Service Worker
// Version 1.2.0 - Fully offline with local packages and images

const CACHE_NAME = 'sun-trading-v1.2.0';
const OFFLINE_URL = '/offline.html';

// Files to cache for offline functionality
const CACHE_FILES = [
  '/',
  '/index.html',
  '/styles.css',
  '/script.js',
  '/translations.js',
  '/manifest.json',

  // Images
  '/images/logo.png',
  '/images/background1.png',
  '/images/background2.png',
  '/images/hero-background.jpg',
  '/images/background2.jpg',
  '/images/background3.jpg',

  // Product images
  '/images/products/wheat.png',
  '/images/products/zora.png',
  '/images/products/lobia.png',
  '/images/products/fasolia.png',
  '/images/products/ads.png',
  '/images/products/homos.png',
  '/images/products/rice.png',
  '/images/products/fol.png',

  // Administration images
  '/images/administration/company_managment.png',
  '/images/administration/Administration4.png',
  '/images/administration/general_manager.png',
  '/images/administration/our_values.png',
  '/images/administration/work_team.png',

  // Fonts
  '/fonts/Almarai-Regular.ttf',
  '/fonts/Almarai-Bold.ttf',
  '/fonts/ltr/Sora-Regular.ttf',
  '/fonts/ltr/Sora-Bold.ttf',

  // Local packages (faster loading)
  '/packages/tailwind/tailwind.min.css',
  '/packages/fontawesome/all.min.css',
  '/packages/bootstrap/bootstrap.min.css',
  '/packages/bootstrap/bootstrap.bundle.min.js',
  '/packages/jquery/jquery-3.7.1.min.js',

  // FontAwesome webfonts
  '/packages/fontawesome/webfonts/fa-brands-400.woff2',
  '/packages/fontawesome/webfonts/fa-brands-400.ttf',
  '/packages/fontawesome/webfonts/fa-regular-400.woff2',
  '/packages/fontawesome/webfonts/fa-regular-400.ttf',
  '/packages/fontawesome/webfonts/fa-solid-900.woff2',
  '/packages/fontawesome/webfonts/fa-solid-900.ttf',
  '/packages/fontawesome/webfonts/fa-v4compatibility.woff2',
  '/packages/fontawesome/webfonts/fa-v4compatibility.ttf',

  // FontAwesome webfonts (CSS expected path)
  '/packages/webfonts/fa-brands-400.woff2',
  '/packages/webfonts/fa-brands-400.ttf',
  '/packages/webfonts/fa-regular-400.woff2',
  '/packages/webfonts/fa-regular-400.ttf',
  '/packages/webfonts/fa-solid-900.woff2',
  '/packages/webfonts/fa-solid-900.ttf',
  '/packages/webfonts/fa-v4compatibility.woff2',
  '/packages/webfonts/fa-v4compatibility.ttf',

  // Offline page
  OFFLINE_URL
];

// Install event - Cache resources
self.addEventListener('install', (event) => {
  // Service Worker: Installing

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        // Service Worker: Caching files
        return cache.addAll(CACHE_FILES);
      })
      .then(() => {
        // Service Worker: All files cached successfully
        return self.skipWaiting();
      })
      .catch((error) => {
        // Service Worker: Caching failed
      })
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', (event) => {
  // Service Worker: Activating

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              // Service Worker: Deleting old cache
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        // Service Worker: Activated successfully
        return self.clients.claim();
      })
  );
});

// Fetch event - Serve cached content when offline
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip chrome-extension and other non-http requests
  if (!event.request.url.startsWith('http')) return;

  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        // Return cached version if available
        if (cachedResponse) {
          // Serving from cache
          return cachedResponse;
        }

        // Try to fetch from network
        return fetch(event.request)
          .then((response) => {
            // Don't cache non-successful responses
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone the response for caching
            const responseToCache = response.clone();

            // Cache the new response
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch(() => {
            // If both cache and network fail, show offline page for navigation requests
            if (event.request.destination === 'document') {
              return caches.match(OFFLINE_URL);
            }

            // For other requests, return a generic offline response
            return new Response('Offline - Content not available', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          });
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
  // Background sync

  if (event.tag === 'contact-form-sync') {
    event.waitUntil(syncContactForms());
  }

  if (event.tag === 'quote-request-sync') {
    event.waitUntil(syncQuoteRequests());
  }
});

// Push notification handler
self.addEventListener('push', (event) => {
  // Push notification received

  const options = {
    body: event.data ? event.data.text() : 'New update from Sun Trading Company',
    icon: '/images/pwa/icon-192x192.png',
    badge: '/images/pwa/badge-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'View Details',
        icon: '/images/pwa/action-explore.png'
      },
      {
        action: 'close',
        title: 'Close',
        icon: '/images/pwa/action-close.png'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('Sun Trading Company', options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  // Notification clicked

  event.notification.close();

  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});

// Helper functions for background sync
async function syncContactForms() {
  try {
    const db = await openDB();
    const forms = await getOfflineContactForms(db);

    for (const form of forms) {
      try {
        await submitContactForm(form.data);
        await deleteOfflineContactForm(db, form.id);
        // Contact form synced successfully
      } catch (error) {
        // Failed to sync contact form
      }
    }
  } catch (error) {
    // Background sync failed
  }
}

async function syncQuoteRequests() {
  try {
    const db = await openDB();
    const quotes = await getOfflineQuoteRequests(db);

    for (const quote of quotes) {
      try {
        await submitQuoteRequest(quote.data);
        await deleteOfflineQuoteRequest(db, quote.id);
        // Quote request synced successfully
      } catch (error) {
        // Failed to sync quote request
      }
    }
  } catch (error) {
    // Quote sync failed
  }
}

// IndexedDB helpers (simplified)
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('SunTradingDB', 1);
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('contactForms')) {
        db.createObjectStore('contactForms', { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains('quoteRequests')) {
        db.createObjectStore('quoteRequests', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

async function getOfflineContactForms(db) {
  const transaction = db.transaction(['contactForms'], 'readonly');
  const store = transaction.objectStore('contactForms');
  return new Promise((resolve, reject) => {
    const request = store.getAll();
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

async function getOfflineQuoteRequests(db) {
  const transaction = db.transaction(['quoteRequests'], 'readonly');
  const store = transaction.objectStore('quoteRequests');
  return new Promise((resolve, reject) => {
    const request = store.getAll();
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

// Placeholder functions for actual form submission
async function submitContactForm(formData) {
  // Implement actual form submission logic
  // Submitting contact form
}

async function submitQuoteRequest(quoteData) {
  // Implement actual quote submission logic
  // Submitting quote request
}
