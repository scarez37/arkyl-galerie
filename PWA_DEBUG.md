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
[HEAD] SW enregistré
[PWA] Système d'installation initialisé
[PWA] beforeinstallprompt déclenché → app installable
[PWA] Bouton installation visible
[ARKYL SW] ✅ Installation complète, skipWaiting déclenché
```

### Logs problématiques ❌
```
[PWA] ⚠️ Service Worker échoué: ...
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
| app.js | ✅ Contient `initPWASystem()` IIFE |

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
   - Doit avoir `arkyl-v3` avec les assets

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

**Le bouton PWA affichera quand même grâce au fallback (ligne 3s dans app.js)**

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

## ✨ Améliorations apportées

✅ **Bouton PWA** (app.js l.12-89)
- Écoute `beforeinstallprompt`
- Détection fallback (manifest + SW)
- Affichage forcé après 3s (bypass Chrome delays)
- Gestion du clic + userChoice

✅ **Service Worker** (service-worker.js)
- Logs améliorés pour débogage
- Erreur cache non-bloquante

✅ **HTML** (index.html)
- Enregistrement SW précoce dans `<head>`
- Listener beforeinstallprompt dès le début

✅ **CSS** (style.css l.2077-2149)
- Style doré ARKYL avec gradient
- Animation pulse au chargement
- Hover effect attractif
- Responsive

---

## 🎯 Prochaines étapes (optionnel)

1. **Notifications Push** → Implémentation quand backend prêt
2. **Offline First** → Certains pages clés en fallback
3. **Screenshots PWA** → Ajouter des images pour app store
4. **Share Target API** → Partager directement vers ARKYL

---

**Questions ?** Regarde les logs console avec `[PWA]` ou `[ARKYL SW]`
