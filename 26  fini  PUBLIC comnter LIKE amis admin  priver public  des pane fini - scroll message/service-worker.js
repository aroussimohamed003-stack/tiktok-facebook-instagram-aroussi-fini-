// Service Worker for Mohamed Aroussi website
const CACHE_NAME = 'mohamed-aroussi-cache-v1';
const urlsToCache = [
  '/',
  'indexmo.php',
  'css/theme.css',
  'js/theme.js',
  'images/favicon.png',
  'images/kk-01.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'notification.mp3'
];

// Install event - cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event - serve from cache if available
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }

        return fetch(event.request).then(
          response => {
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                if (!event.request.url.includes('get_notifications.php') &&
                  !event.request.url.includes('get_profile.php') &&
                  !event.request.url.includes('search_')) {
                  cache.put(event.request, responseToCache);
                }
              });

            return response;
          }
        ).catch(() => caches.match('indexmo.php'));
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// PUSH NOTIFICATION EVENT
self.addEventListener('push', function (event) {
  console.log('[Service Worker] Push Received.');
  console.log(`[Service Worker] Push had this data: "${event.data.text()}"`);

  let data = { title: 'Aroussi', body: 'لديك إشعار جديد', icon: 'images/kk-01.png', url: 'indexmo.php' };

  try {
    if (event.data) {
      const pushData = event.data.json();
      data = { ...data, ...pushData };
    }
  } catch (e) {
    data.body = event.data.text();
  }

  const title = data.title;
  const options = {
    body: data.body,
    icon: data.icon,
    badge: 'images/kk-01.png',
    data: { url: data.url }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// NOTIFICATION CLICK EVENT
self.addEventListener('notificationclick', function (event) {
  console.log('[Service Worker] Notification click Received.');
  event.notification.close();

  const urlToOpen = event.notification.data.url || 'indexmo.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
      for (let i = 0; i < windowClients.length; i++) {
        const client = windowClients[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
