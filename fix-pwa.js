// Script pour forcer l'activation du Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
        .then(reg => {
            console.log('[PWA] ✅ Service Worker enregistré:', reg.scope);
            // Forcer la mise à jour
            reg.update();
        })
        .catch(err => console.error('[PWA] ❌ Erreur:', err));
}
