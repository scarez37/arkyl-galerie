# 🔴 Problème CORS - Solution

## Diagnostic

Le backend `api_commandes.php` sur Render **n'envoie pas les headers CORS** même s'ils sont définis.

Cela peut venir de :
1. ❌ Une erreur PHP avant `sendCorsHeaders()`
2. ❌ Une exception levée par `db_config.php` ou `notify_helpers.php`
3. ❌ Une sortie parasite (whitespace, BOM) avant `<?php`

## ✅ Solution : Ajouter une protection

### **Étape 1 : Vérifier db_config.php**

Assurez-vous qu'il **n'a RIEN après `?>`** (ni whitespace, ni newlines) :

```bash
tail -c 20 db_config.php | od -c
```

Si vous voyez des caractères après `?>`, **supprimez-les** !

### **Étape 2 : Ajouter un fichier cors_init.php**

Créez un fichier `cors_init.php` :

```php
<?php
// CORS INIT — À inclure EN PREMIER dans api_commandes.php
// Avant TOUT require_once

// Capturer toute sortie parasite
ob_start();

// Envoi immédiat des headers CORS
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
}

// Gérer les OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Gérer les exceptions pour envoyer JSON au lieu du HTML d'erreur
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit();
});
?>
```

### **Étape 3 : Modifier api_commandes.php**

**En haut du fichier**, remplacez :

```php
<?php
// ==================== API COMMANDES — ROUTER ====================

// ═══════════════════════════════════════════════════════════════
// CORS ABSOLUMENT EN PREMIER — ob_start() pour capturer toute
// sortie parasite (notices PHP, BOM, whitespace) qui empêcherait
// les header() de s'exécuter. Le shutdown handler garantit les
// headers CORS même en cas de fatal error dans les require_once.
// ═══════════════════════════════════════════════════════════════
ob_start();
```

**Par :**

```php
<?php
// ==================== API COMMANDES — ROUTER ====================

// ✅ CHARGER LE FIX CORS EN PREMIER
require_once __DIR__ . '/cors_init.php';

// ═══════════════════════════════════════════════════════════════
// CORS ABSOLUMENT EN PREMIER — ob_start() pour capturer toute
// sortie parasite (notices PHP, BOM, whitespace) qui empêcherait
// les header() de s'exécuter. Le shutdown handler garantit les
// headers CORS même en cas de fatal error dans les require_once.
// ═══════════════════════════════════════════════════════════════
```

### **Étape 4 : Vérifier que db_config.php ne lance pas d'erreur**

Testez directement :

```bash
curl -v 'https://arkyl-galerie-nvwn.onrender.com/api_commandes.php?action=list&admin=1'
```

Vous devriez voir :
```
< HTTP/1.1 200 OK
< Access-Control-Allow-Origin: *
< Content-Type: application/json
```

---

## 🚀 Alternative : Utiliser un service CORS public

Si vous ne pouvez pas modifier le PHP sur Render, utilisez **`https://cors-anywhere.herokuapp.com/`** :

Remplacez dans `app.js` :

```javascript
// AVANT:
const API_BASE = 'https://arkyl-galerie-nvwn.onrender.com';

// APRÈS:
const API_BASE = 'https://cors-anywhere.herokuapp.com/https://arkyl-galerie-nvwn.onrender.com';
```

⚠️ **Attention** : Cela ralentit les requêtes et dépend d'un service tiers.

---

## 📝 Résumé

| Option | Avantages | Inconvénients |
|--------|-----------|--------------|
| **Corriger PHP** | ✅ Permanent, rapide, sûr | ❌ Doit modifier le backend |
| **Proxy local** | ✅ Pas de dépendances externes | ❌ Doit être sur arkyl.site |
| **cors-anywhere** | ✅ Aucune modif backend | ❌ Lent, service tiers, limité |

**Je recommande : Corriger le PHP directement ! 🎯**
