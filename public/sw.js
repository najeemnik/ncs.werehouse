/* Nik Smart Count - Warehousing — offline static shell.
 * Only CSS, JS, fonts, icons and brand images are cached. Every PHP response
 * (and therefore every database-backed page) is network-only, so the browser
 * never keeps a copy of the shop's accounting data. */
'use strict';
var VERSION = 'nik-smart-count-v3';
var STATIC_ASSETS = [
  'assets/css/style.css',
  'assets/css/themes.css',
  'assets/css/branding.css',
  'assets/js/main.js',
  'assets/js/currency.js',
  'assets/js/preferences.js',
  'assets/js/branding.js',
  'offline.html',
  'uploads/branding/pwa-icon-192.png',
  'uploads/branding/pwa-icon-512.png',
  'uploads/branding/nik-smart-count-mark.png'
];
var STATIC_RE = /\.(?:css|js|woff2?|ttf|otf|eot|png|jpe?g|webp|gif|svg|ico|webmanifest)$/i;

function scopeUrl(path) {
  return new URL(path, self.registration.scope).toString();
}

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(VERSION)
      .then(function (cache) {
        return Promise.all(STATIC_ASSETS.map(function (path) {
          return cache.add(new Request(scopeUrl(path), { cache: 'reload' })).catch(function () {});
        }));
      })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (key) { return key !== VERSION; }).map(function (key) {
        return caches.delete(key);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') return;
  var url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  var isPage = request.mode === 'navigate' || /\.php$/i.test(url.pathname);
  if (isPage) {
    // Network only: accounting pages must always reflect the live database.
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match(scopeUrl('offline.html'));
      })
    );
    return;
  }

  if (!STATIC_RE.test(url.pathname)) return;
  // Network-first: a new release is visible immediately, while the cached copy
  // still keeps the login screen usable when the connection drops.
  event.respondWith(
    fetch(request).then(function (response) {
      if (response && response.ok && (response.type === 'basic' || response.type === 'default')) {
        var copy = response.clone();
        caches.open(VERSION).then(function (cache) { cache.put(request, copy); }).catch(function () {});
      }
      return response;
    }).catch(function () {
      return caches.match(request).then(function (cached) {
        return cached || Response.error();
      });
    })
  );
});
