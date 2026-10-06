// ClassCheck Service Worker for Push & Windows Status Notifications
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';
    const absoluteTargetUrl = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Find existing ClassCheck tab
            for (const client of clientList) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    return client.focus().then(() => {
                        if ('navigate' in client) {
                            return client.navigate(absoluteTargetUrl);
                        }
                    });
                }
            }
            // If no window is open, open a new one
            if (self.clients.openWindow) {
                return self.clients.openWindow(absoluteTargetUrl);
            }
        })
    );
});

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload = {};
    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Class Reminder', body: event.data.text() };
    }

    const title = payload.title || 'ClassCheck Reminder';
    const options = {
        body: payload.body || 'You have an upcoming class.',
        icon: payload.icon || '/images/logo.png',
        badge: payload.badge || '/images/logo.png',
        tag: payload.tag || 'classcheck-reminder',
        data: payload.data || { url: '/' },
        requireInteraction: payload.requireInteraction ?? true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});
