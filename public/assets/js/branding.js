/* Nik Smart Count - Warehousing — brand motion, PWA installation and the
 * post-login welcome overlay. This file never stores business data in the
 * browser: only a one-time UI preference is kept in sessionStorage. */
(function () {
  'use strict';
  var script = document.currentScript;
  var base = (script && script.getAttribute('data-base')) || '/';

  /* ---------------------------------------------------------------------
   * 1. Welcome overlay shown once, immediately after a successful sign-in.
   * ------------------------------------------------------------------- */
  function initWelcome() {
    var overlay = document.getElementById('nikWelcome');
    if (!overlay) return;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var life = reduced ? 900 : 2650;
    overlay.hidden = false;
    document.documentElement.setAttribute('data-nik-welcome', '1');
    window.setTimeout(function () {
      overlay.classList.add('is-leaving');
      window.setTimeout(function () {
        overlay.hidden = true;
        overlay.remove();
        document.documentElement.removeAttribute('data-nik-welcome');
      }, reduced ? 0 : 480);
    }, life);
  }

  /* ---------------------------------------------------------------------
   * 2. Login wordmark hide / unhide control (the logo keeps its motion).
   * ------------------------------------------------------------------- */
  function initWordmark() {
    var brand = document.querySelector('.auth-brand');
    var toggle = document.querySelector('[data-nik-wordmark-toggle]');
    if (!brand || !toggle) return;
    var saved = null;
    try { saved = sessionStorage.getItem('nikxor_wordmark'); } catch (e) {}
    if (saved === 'hidden') brand.setAttribute('data-wordmark', 'hidden');
    syncIcon();
    toggle.addEventListener('click', function () {
      var hidden = brand.getAttribute('data-wordmark') === 'hidden';
      if (hidden) brand.removeAttribute('data-wordmark');
      else brand.setAttribute('data-wordmark', 'hidden');
      try { sessionStorage.setItem('nikxor_wordmark', hidden ? 'shown' : 'hidden'); } catch (e) {}
      syncIcon();
    });
    function syncIcon() {
      var hidden = brand.getAttribute('data-wordmark') === 'hidden';
      toggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
      var icon = toggle.querySelector('i');
      if (icon) icon.className = hidden ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
      toggle.title = hidden ? (toggle.dataset.showText || 'Show name') : (toggle.dataset.hideText || 'Hide name');
    }
  }

  /* ---------------------------------------------------------------------
   * 3. Progressive Web App installation.
   *    - Service worker caches static assets only; PHP pages and every
   *      database-backed response stay network-only, so no business data is
   *      written to browser storage.
   * ------------------------------------------------------------------- */
  var installEvent = null;
  function isStandalone() {
    return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
      window.navigator.standalone === true;
  }
  function showInstallUI() {
    if (isStandalone()) return;
    Array.prototype.forEach.call(document.querySelectorAll('[data-nik-install]'), function (btn) {
      btn.hidden = false;
      btn.classList.add('nik-install-btn');
    });
    var banner = document.querySelector('[data-nik-install-banner]');
    if (banner) {
      var dismissed = false;
      try { dismissed = sessionStorage.getItem('nikxor_install_dismissed') === '1'; } catch (e) {}
      if (!dismissed) banner.classList.add('is-visible');
    }
  }
  function initInstall() {
    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      installEvent = event;
      showInstallUI();
    });
    window.addEventListener('appinstalled', function () {
      installEvent = null;
      Array.prototype.forEach.call(document.querySelectorAll('[data-nik-install]'), function (btn) { btn.hidden = true; });
      var banner = document.querySelector('[data-nik-install-banner]');
      if (banner) banner.classList.remove('is-visible');
    });
    document.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-nik-install]');
      var dismiss = event.target.closest('[data-nik-install-dismiss]');
      if (dismiss) {
        var b = document.querySelector('[data-nik-install-banner]');
        if (b) b.classList.remove('is-visible');
        try { sessionStorage.setItem('nikxor_install_dismissed', '1'); } catch (e) {}
        return;
      }
      if (!btn) return;
      event.preventDefault();
      if (!installEvent) {
        var msg = btn.getAttribute('data-install-hint') || 'Use the browser menu and choose "Install app".';
        if (window.nikToast) window.nikToast(msg); else window.alert(msg);
        return;
      }
      installEvent.prompt();
      installEvent.userChoice.then(function () { installEvent = null; }).catch(function () {});
    });
    if (isStandalone()) showInstallUI();
  }
  function initServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
    navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {});
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  }
  ready(function () {
    initWelcome();
    initWordmark();
    initInstall();
    initServiceWorker();
  });
})();
