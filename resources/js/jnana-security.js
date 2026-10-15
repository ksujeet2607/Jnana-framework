/**
 * Jnana CSRF recovery helper.
 *
 * Include this script and csrf_meta() in the shared layout. A 419 response
 * refreshes the token and updates forms/meta state, but never silently replays
 * a state-changing request. Listen for `jnana:csrf-expired` to notify the user.
 */
(function (window, document) {
    'use strict';

    var refreshUrl = '/security/csrf-token';
    var refreshPromise = null;

    function updateToken(data) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', data.token);
            meta.setAttribute('data-lifetime', String(data.expires_in || 7200));
            meta.setAttribute('data-issued-at', String(Math.floor(Date.now() / 1000)));
        }

        document.querySelectorAll('input[name="' + data.field + '"]').forEach(function (input) {
            input.value = data.token;
        });

        window.JnanaSecurity = window.JnanaSecurity || {};
        window.JnanaSecurity.token = data.token;
        window.JnanaSecurity.header = data.header || 'X-CSRF-Token';
        scheduleRefresh();
        return data.token;
    }


    var lastUserActivity = Date.now();
    var refreshTimer = null;

    function markActive() {
        lastUserActivity = Date.now();
    }

    ['pointerdown', 'keydown', 'input', 'touchstart'].forEach(function (eventName) {
        document.addEventListener(eventName, markActive, { passive: true });
    });

    function scheduleRefresh() {
        if (refreshTimer) window.clearTimeout(refreshTimer);
        var meta = document.querySelector('meta[name="csrf-token"]');
        var lifetime = meta ? parseInt(meta.getAttribute('data-lifetime') || '7200', 10) : 7200;
        var issuedAt = meta ? parseInt(meta.getAttribute('data-issued-at') || String(Math.floor(Date.now() / 1000)), 10) : Math.floor(Date.now() / 1000);
        var remainingMs = Math.max(1000, ((issuedAt + lifetime - 60) * 1000) - Date.now());

        refreshTimer = window.setTimeout(function () {
            // Do not keep an abandoned page/session alive. Refresh only if the
            // user has interacted recently; otherwise refresh on next activity.
            if (Date.now() - lastUserActivity < 10 * 60 * 1000) {
                refreshToken().catch(function () {});
            }
        }, remainingMs);
    }

    window.addEventListener('pageshow', function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var lifetime = meta ? parseInt(meta.getAttribute('data-lifetime') || '7200', 10) : 7200;
        var issuedAt = meta ? parseInt(meta.getAttribute('data-issued-at') || '0', 10) : 0;
        if (issuedAt && Date.now() / 1000 >= issuedAt + lifetime - 60
            && Date.now() - lastUserActivity < 10 * 60 * 1000) {
            refreshToken().catch(function () {});
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            var lifetime = meta ? parseInt(meta.getAttribute('data-lifetime') || '7200', 10) : 7200;
            var issuedAt = meta ? parseInt(meta.getAttribute('data-issued-at') || '0', 10) : 0;
            if (issuedAt && Date.now() / 1000 >= issuedAt + lifetime - 60
                && Date.now() - lastUserActivity < 10 * 60 * 1000) {
                refreshToken().catch(function () {});
            }
        }
    });

    function refreshToken() {
        if (refreshPromise) return refreshPromise;

        refreshPromise = window.fetch(refreshUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Unable to refresh CSRF token.');
            return response.json();
        }).then(updateToken).finally(function () {
            refreshPromise = null;
        });

        return refreshPromise;
    }

    window.JnanaSecurity = window.JnanaSecurity || {};
    window.JnanaSecurity.refreshToken = refreshToken;
    window.JnanaSecurity.getToken = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.JnanaSecurity.token || null);
    };
    window.JnanaSecurity.handleResponse = function (response) {
        if (response && response.status === 419) {
            return refreshToken().then(function () {
                document.dispatchEvent(new CustomEvent('jnana:csrf-expired', {
                    detail: { refreshed: true, resubmitRequired: true }
                }));
                return response;
            });
        }
        return Promise.resolve(response);
    };

    // Keep fetch-based AJAX clients in sync. The original request is not replayed.
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            return originalFetch(input, init).then(function (response) {
                return window.JnanaSecurity.handleResponse(response);
            });
        };
    }

    scheduleRefresh();

    // Provide a helper for XMLHttpRequest-based code without patching its behavior.
    document.addEventListener('jnana:csrf-refresh-requested', function () {
        refreshToken().catch(function () {
            document.dispatchEvent(new CustomEvent('jnana:csrf-refresh-failed'));
        });
    });
})(window, document);
