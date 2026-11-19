# 🚀 Guide de Débogage PWA - ARKYL

## 📱 Tests rapides

### Android Chrome
1. Ouvre https://arkyl.site sur Chrome Android
2. Attends 3-5 secondes
3. **Le bouton "Installer l'app" doit apparaître** à côté du bouton "Rafraîchir"
4. Clique → popup d'installation native → "Installer"
5. Vérifie que l'icône ARKYL apparaît sur ton écran d'accueil

### iPhone (iOS Safari)
1. Ouvre https://arkyl.site sur Safari iOS
2. Le bouton ne s'affichera **pas** (limitation iOS)
3. Alternative manuelle : Partage → "Sur l'écran d'accueil"
4. L'app s'ajoute quand même

---

## 🔍 Diagnostic Console

Ouvre la **console du navigateur** (F12) et cherche ces logs :

### Logs OK ✅
```
✅ Service Worker enregistré
📱 PWA: Bouton installation prêt          (uniquement dans le navigateur, jamais dans l'app installée)
```

### Logs problématiques ❌
```
⚠️ Service Worker échoué: ...
[ARKYL SW] ❌ Erreur cache addAll: ...
```

---

## 📋 Checklist avant de tester

| Élément | Vérification |
|--------|-------------|
| manifest.json | ✅ Accessible à `arkyl.site/manifest.json` |
| service-worker.js | ✅ Accessible à `arkyl.site/service-worker.js` |
| Domaine HTTPS | ✅ arkyl.site doit être en HTTPS (pas HTTP) |
| Meta tags PWA | ✅ Présent dans `<head>` (manifest, apple-mobile-web-app-*) |
| index.html | ✅ Contient le bouton `#pwaInstallBtn` |
| app.js | ✅ Contient le bloc « PWA INSTALL BUTTON » (`installPWA`, `isRunningAsInstalledApp`) |

---

## 🛠️ Déboguer le Service Worker

### Dans Chrome DevTools :
1. **Application** → **Service Workers**
   - Doit montrer `/service-worker.js` = **active**
   - Si "waiting", clique "Update" ou force refresh

2. **Application** → **Manifest**
   - Tous les champs doivent être **verts** ✅
   - Icons doivent être trouvés

3. **Application** → **Cache Storage**
   - Doit avoir `arkyl-v4` avec les assets

---

## 🚨 Problèmes courants & Solutions

### ❌ Le bouton n'apparaît pas du tout

**Cause probables :**
1. Site pas en HTTPS → ✅ Vérifier que arkyl.site est HTTPS
2. Service Worker pas actif → ✅ Voir onglet "Service Workers" DevTools
3. Manifest invalide → ✅ DevTools → Application → Manifest (doit être tous verts)

**Solutions :**
```javascript
// Dans console du navigateur :
navigator.serviceWorker.getRegistrations().then(regs => {
  console.log('SW enregistrés:', regs.length);
  regs.forEach(r => console.log(r.scope));
});
```

### ❌ beforeinstallprompt ne se déclenche pas

C'est normal ! Chrome/Edge requiert :
- ✅ HTTPS
- ✅ manifest.json valide
- ✅ Engagement utilisateur
- ✅ 5+ secondes sur le site (parfois)

Le bouton n'apparaît que si Chrome déclenche `beforeinstallprompt` (il n'y a plus d'affichage forcé).
Il est **toujours masqué dans l'application installée** (`display-mode: standalone`, `navigator.standalone`).

### ❌ Erreur "Cannot read property 'prompt' of null"

L'utilisateur a cliqué le bouton avant que beforeinstallprompt arrive. Pas grave, il suffit de réessayer après refresh.

### ❌ L'app s'installe mais pas d'icône

**iOS :** Non supporté par Apple, mais l'app est quand même installée (Web Clip)
**Android :** Vérifier que les icons sont présentes et .gitignore n'exclut pas `*.png`

---

## 📊 Analyse rapide sur mobile

1. Ouvre arkyl.site → F12 (DevTools mobile)
2. Console → copie-colle ceci :

```javascript
console.log('=== ARKYL PWA DIAGNOSTIC ===');
console.log('Https:', window.location.protocol === 'https:');
console.log('Manifest:', !!document.querySelector('link[rel="manifest"]'));
console.log('SW support:', 'serviceWorker' in navigator);
console.log('PWA Button:', !!document.getElementById('pwaInstallBtn'));
navigator.serviceWorker.getRegistrations().then(regs => {
  console.log('SW actifs:', regs.length);
  if(regs.length) console.log('Scope:', regs[0].scope);
});
```

---

## ✨ Comportement actuel

✅ **Bouton PWA** (app.js, bloc « PWA INSTALL BUTTON » + style.css `#pwaInstallBtn`)
- Écoute `beforeinstallprompt`, gère le clic + `userChoice`
- Masqué dans l'app installée (JS + règle CSS `@media (display-mode: standalone)`)

✅ **Service Worker** (service-worker.js, cache `arkyl-v4`)
- Pages, `app.js`, `auth.js`, `api-client.js`, `style.css` : réseau d'abord, cache si hors ligne
- Ne touche jamais aux POST, aux appels `.php` / `/api_*` ni aux autres sites (Google, Stripe…)
- Les anciens caches sont supprimés à l'activation

✅ **Bouton « retour » du téléphone** (app.js, `updateNavigationHistory` / `popstate`)
- Revient à la page précédente de l'app au lieu de la fermer ; depuis l'accueil il quitte l'app normalement

✅ **Manifest** : `id`, couleurs sombres (plus d'écran blanc au lancement), icônes PNG 192 / 512 / maskable

## 🎯 Prochaines étapes (optionnel)

1. **Notifications Push** → Implémentation quand backend prêt
2. **Offline First** → Certains pages clés en fallback
3. **Screenshots PWA** → Ajouter des images pour app store
4. **Share Target API** → Partager directement vers ARKYL

---

**Questions ?** Regarde les logs console avec `[PWA]` ou `[ARKYL SW]`
