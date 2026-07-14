# 👁️ Système de Compteur de Vues

## Fonctionnement

Quand un utilisateur visualise une œuvre dans la galerie publique :

1. **Frontend (app.js)** : 
   - Appelle `recordArtworkView(artworkId)` 
   - Enregistre dans localStorage pour éviter les doublons (1 vue par jour max par personne)
   - Envoie une requête POST à l'API

2. **Backend (api_galerie_publique.php)** :
   - Reçoit `action=record_view&artwork_id=XXX`
   - Incrémente le compteur dans la base de données
   - Retourne `{success: true, view_count: N}`

3. **Affichage** :
   - Les œuvres affichent `${a.view_count || 0} vue(s)`
   - Le compteur s'actualise en temps réel

---

## À faire : Modifier l'API

### **Étape 1 : Ajouter une colonne à la base de données**

```sql
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS last_viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
```

### **Étape 2 : Ajouter le handler dans api_galerie_publique.php**

Ajouter cette fonction :

```php
// Dans handleApiPost() ou en haut de la section POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    
    // ✅ ACTION: record_view
    if ($action === 'record_view') {
        header('Content-Type: application/json');
        $artwork_id = $input['artwork_id'] ?? null;
        
        if (!$artwork_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'artwork_id manquant']);
            exit();
        }
        
        try {
            $db = getDatabase();
            
            // Incrémenter le compteur de vues
            $stmt = $db->prepare("UPDATE artworks SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?");
            $stmt->execute([$artwork_id]);
            
            // Retourner le nouveau compteur
            $getStmt = $db->prepare("SELECT view_count FROM artworks WHERE id = ?");
            $getStmt->execute([$artwork_id]);
            $result = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'artwork_id' => $artwork_id,
                'view_count' => $result['view_count'] ?? 0,
                'message' => 'Vue enregistrée'
            ]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}
```

### **Étape 3 : Retourner view_count dans la réponse GET**

Dans la requête GET `?artwork_id=XXX`, s'assurer que `view_count` est inclus :

```php
// Dans la requête SELECT
SELECT id, title, price, artist_name, photo, photos, 
       view_count, last_viewed_at,  // ← AJOUTER CES COLONNES
       ...
FROM artworks 
WHERE id = ?
```

---

## 🚀 Résumé

| Étape | Fait? | Détails |
|-------|-------|---------|
| Frontend envoie les vues | ✅ | recordArtworkView() implémenté |
| Backend enregistre | ⏳ | À implémenter dans api_galerie_publique.php |
| Affichage mise à jour | ✅ | ${a.view_count} affiche le bon nombre |
| Déduplication | ✅ | 1 vue par personne par jour (localStorage) |

---

## 📱 Déduplication

Pour éviter que la même personne soit comptée plusieurs fois :

- **Frontend** : localStorage `viewedArtworks` stocke `artwork_id_YYYY-MM-DD: true`
- **Backend** : (optionnel) pourrait vérifier les IP ou user_id si compte utilisateur

**Note** : Si vous voulez compter chaque consultation, supprimez juste le check localStorage.

---

## ✅ Après implémentation

Une fois le backend modifié, testez :

```bash
# 1. Ouvrir une œuvre dans la galerie
# 2. Vérifier la console : "✅ Vue enregistrée pour œuvre 123"
# 3. Rafraîchir → Le compteur devrait augmenter
# 4. Ouvrir à nouveau la MÊME œuvre → Pas d'incrémentation (même jour)
# 5. Vérifier en admin → Le compteur affiché devrait être correct
```

---

## 🔍 Dépannage

**Les vues ne s'incrémentent pas :**
- Vérifier que `action=record_view` est bien géré par l'API
- Vérifier que la colonne `view_count` existe en BDD
- Vérifier la console browser pour les erreurs

**Le compteur réinitialise :**
- Vérifier que la BDD est persistente
- Vérifier qu'il n'y a pas de reset en déploiement

**Le même utilisateur est compté plusieurs fois :**
- Le système localStorage ne compte que 1 fois par jour
- C'est intentionnel pour éviter les clics répétés
