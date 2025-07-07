// Sun Trading Company - Service Worker
// Version 1.0.0

const CACHE_NAME = 'sun-trading-v1.0.0';
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
  
  // External resources (cached separately)
  'https://cdn.tailwindcss.com',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://code.jquery.com/jquery-3.7.1.min.js',
  
  // Offline page
  OFFLINE_URL
];

// Install event - Cache resources
self.addEventListener('install', (event) => {
  console.log('🚀 Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('📦 Service Worker: Caching files');
        return cache.addAll(CACHE_FILES);
      })
      .then(() => {
        console.log('✅ Service Worker: All files cached successfully');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('❌ Service Worker: Caching failed', error);
      })
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', (event) => {
  console.log('🔄 Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              console.log('🗑️ Service Worker: Deleting old cache', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('✅ Service Worker: Activated successfully');
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
          console.log('📦 Serving from cache:', event.request.url);
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
  console.log('🔄 Background sync:', event.tag);
  
  if (event.tag === 'contact-form-sync') {
    event.waitUntil(syncContactForms());
  }
  
  if (event.tag === 'quote-request-sync') {
    event.waitUntil(syncQuoteRequests());
  }
});

// Push notification handler
self.addEventListener('push', (event) => {
  console.log('🔔 Push notification received');
  
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
  console.log('🔔 Notification clicked:', event.action);
  
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
        console.log('✅ Contact form synced successfully');
      } catch (error) {
        console.error('❌ Failed to sync contact form:', error);
      }
    }
  } catch (error) {
    console.error('❌ Background sync failed:', error);
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
        console.log('✅ Quote request synced successfully');
      } catch (error) {
        console.error('❌ Failed to sync quote request:', error);
      }
    }
  } catch (error) {
    console.error('❌ Quote sync failed:', error);
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
  console.log('Submitting contact form:', formData);
}

async function submitQuoteRequest(quoteData) {
  // Implement actual quote submission logic
  console.log('Submitting quote request:', quoteData);
}
