// Service Worker لتفعيل تطبيق الـ PWA
const CACHE_NAME = 'ethraa-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // السماح للموقع بالعمل كالمعتاد
});