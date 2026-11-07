/**
 * api-client.js — connexion sécurisée entre le navigateur et l'API ARKYL
 *
 * • Conserve le jeton de session délivré par le SERVEUR après connexion.
 * • Ajoute automatiquement ce jeton (en-tête Authorization) à tous les appels
 *   vers les fichiers *.php DU MÊME SITE — jamais vers un site tiers
 *   (Stripe, Google, etc. ne reçoivent donc jamais le jeton).
 * • Si le serveur répond « 401 » avec un jeton, la session a expiré :
 *   le jeton est supprimé et l'évènement « arkyl-session-expired » est émis.
 *
 * À charger AVANT les autres scripts de la page.
 */
(function () {
    'use strict';

    var TOKEN_KEY   = 'arkyl_session_token';
    var EXPIRES_KEY = 'arkyl_session_expires';

    function read(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function write(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* navigation privée : ignoré */ }
    }
    function remove(key) {
        try { localStorage.removeItem(key); } catch (e) { /* ignoré */ }
    }

    var ArkylAPI = {
        getToken: function () {
            var token = read(TOKEN_KEY);
            if (!token) return null;
            var exp = parseInt(read(EXPIRES_KEY) || '0', 10);
            if (exp && Date.now() > exp) {
                remove(TOKEN_KEY);
                remove(EXPIRES_KEY);
                return null;
            }
            return token;
        },
        setToken: function (token, expiresInSeconds) {
            if (!token) return;
            write(TOKEN_KEY, token);
            write(EXPIRES_KEY, String(Date.now() + (expiresInSeconds || 2592000) * 1000));
        },
        clearToken: function () {
            remove(TOKEN_KEY);
            remove(EXPIRES_KEY);
        }
    };
    window.ArkylAPI = ArkylAPI;

    /** Cet appel vise-t-il l'API du même site (fichier .php) ? */
    function isOwnApi(input) {
        try {
            var raw = typeof input === 'string' ? input : (input && input.url);
            if (!raw) return false;
            var url = new URL(raw, window.location.href);
            return url.origin === window.location.origin && /\.php$/i.test(url.pathname);
        } catch (e) {
            return false;
        }
    }

    var nativeFetch = window.fetch ? window.fetch.bind(window) : null;
    if (!nativeFetch) return;

    var expiredNotified = false;

    window.fetch = function (input, init) {
        var hadToken = false;
        try {
            if (isOwnApi(input)) {
                var token = ArkylAPI.getToken();
                if (token) {
                    hadToken = true;
                    init = init ? Object.assign({}, init) : {};
                    var headers = new Headers(init.headers || (typeof Request !== 'undefined' && input instanceof Request ? input.headers : undefined));
                    if (!headers.has('Authorization')) headers.set('Authorization', 'Bearer ' + token);
                    // En-tête de secours : certains serveurs Apache suppriment « Authorization »
                    if (!headers.has('X-Arkyl-Token')) headers.set('X-Arkyl-Token', token);
                    init.headers = headers;
                }
            }
        } catch (e) { /* en cas de doute, la requête part sans modification */ }

        return nativeFetch(input, init).then(function (response) {
            // 401 : jeton expiré, OU session ouverte avant la mise à jour de sécurité (aucun jeton) → reconnexion nécessaire
            if (response && response.status === 401 && (hadToken || read('arkyl_current_user'))) {
                ArkylAPI.clearToken();
                if (!expiredNotified) {
                    expiredNotified = true;
                    try { window.dispatchEvent(new CustomEvent('arkyl-session-expired')); } catch (e) { /* ignoré */ }
                }
            }
            return response;
        });
    };

    window.addEventListener('arkyl-session-expired', function () {
        if (typeof window.showToast === 'function') {
            window.showToast('⚠️ Votre session a expiré. Reconnectez-vous.');
        }
    });
})();
