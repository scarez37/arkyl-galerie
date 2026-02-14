<?php
/**
 * API COMPLÈTE - VERSION CORRIGÉE
 * Gestion complète des œuvres avec publication automatique
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = new SQLite3('artgallery.db');
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // ============================================
    // RÉCUPÉRER LES STATISTIQUES ARTISTE
    // ============================================
    if ($method === 'GET' && $action === 'get_artist_stats') {
        $artist_id = $_GET['artist_id'] ?? 0;
        
        if (!$artist_id) {
            echo json_encode(['success' => false, 'message' => 'ID artiste manquant']);
            exit;
        }
        
        // Récupérer les ventes
        $sales = $db->querySingle(
            "SELECT COUNT(id) as nb, SUM(artist_revenue) as rev 
             FROM sales 
             WHERE artist_id = $artist_id AND status IN ('completed','shipped')", 
            true
        );
        
        // Récupérer les vues
        $views = $db->querySingle(
            "SELECT COUNT(v.id) as vues 
             FROM artwork_views v 
             JOIN artworks a ON v.artwork_id = a.id 
             WHERE a.artist_id = $artist_id"
        );
        
        echo json_encode([
            'success' => true, 
            'stats' => [
                'total_sales' => $sales['nb'] ?? 0,
                'revenue' => $sales['rev'] ?? 0,
                'total_views' => $views ?? 0
            ]
        ]);
        exit;
    }

    // ============================================
    // AJOUTER UNE ŒUVRE (CORRIGÉ)
    // ============================================
    if ($method === 'POST' && $action === 'add_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation des données obligatoires
        if (empty($data['title']) || empty($data['price']) || empty($data['artist_id']) || empty($data['image'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes (titre, prix, artiste ou image)']);
            exit;
        }

        $artist_id = $data['artist_id'];
        $title = $data['title'];
        $price = $data['price'];
        $description = $data['description'] ?? '';
        $image_base64 = $data['image'];

        // ============================================
        // SAUVEGARDER L'IMAGE EN FICHIER
        // ============================================
        $image_filename = '';
        
        // Créer le dossier uploads s'il n'existe pas
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // Extraire et sauvegarder l'image
        if (preg_match('/^data:image\/(\w+);base64,/', $image_base64, $type)) {
            // Retirer le préfixe data:image/...;base64,
            $image_base64 = substr($image_base64, strpos($image_base64, ',') + 1);
            $image_data = base64_decode($image_base64);
            
            if ($image_data === false) {
                echo json_encode(['success' => false, 'message' => 'Erreur décodage image']);
                exit;
            }
            
            // Créer un nom de fichier unique
            $image_filename = 'uploads/artwork_' . time() . '_' . uniqid() . '.jpg';
            
            // Sauvegarder l'image
            if (!file_put_contents($image_filename, $image_data)) {
                echo json_encode(['success' => false, 'message' => 'Erreur sauvegarde image']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Format image invalide']);
            exit;
        }

        // ============================================
        // INSÉRER DANS LA BASE DE DONNÉES
        // CORRECTION CRITIQUE : status = 'active' pour publication immédiate
        // ============================================
        $stmt = $db->prepare("
            INSERT INTO artworks (
                artist_id, 
                title, 
                description, 
                price, 
                image_url, 
                status, 
                created_at
            ) VALUES (
                :artist_id, 
                :title, 
                :description, 
                :price, 
                :image_url, 
                'active', 
                datetime('now')
            )
        ");
        
        $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':price', $price, SQLITE3_FLOAT);
        $stmt->bindValue(':image_url', $image_filename, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            $artwork_id = $db->lastInsertRowID();
            echo json_encode([
                'success' => true, 
                'message' => 'Œuvre publiée avec succès !',
                'artwork_id' => $artwork_id,
                'image_url' => $image_filename
            ]);
        } else {
            // Si échec, supprimer l'image uploadée
            if (file_exists($image_filename)) {
                unlink($image_filename);
            }
            echo json_encode(['success' => false, 'message' => 'Erreur insertion base de données']);
        }
        exit;
    }

    // ============================================
    // SUPPRIMER UNE ŒUVRE
    // ============================================
    if ($method === 'POST' && $action === 'delete_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['artwork_id']) || empty($data['artist_id'])) {
            echo json_encode(['success' => false, 'message' => 'ID œuvre ou artiste manquant']);
            exit;
        }

        $artwork_id = $data['artwork_id'];
        $artist_id = $data['artist_id'];

        // Vérifier que l'œuvre appartient bien à cet artiste
        $stmt = $db->prepare("SELECT image_url FROM artworks WHERE id = :id AND artist_id = :artist_id");
        $stmt->bindValue(':id', $artwork_id, SQLITE3_INTEGER);
        $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $artwork = $result->fetchArray(SQLITE3_ASSOC);

        if (!$artwork) {
            echo json_encode(['success' => false, 'message' => 'Œuvre non trouvée ou vous n\'êtes pas autorisé']);
            exit;
        }

        // Supprimer l'image physique
        if (!empty($artwork['image_url']) && file_exists($artwork['image_url'])) {
            unlink($artwork['image_url']);
        }

        // Supprimer de la base de données
        $stmt = $db->prepare("DELETE FROM artworks WHERE id = :id AND artist_id = :artist_id");
        $stmt->bindValue(':id', $artwork_id, SQLITE3_INTEGER);
        $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Œuvre supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
        exit;
    }

    // ============================================
    // MODIFIER UNE ŒUVRE
    // ============================================
    if ($method === 'POST' && $action === 'update_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['artwork_id']) || empty($data['artist_id'])) {
            echo json_encode(['success' => false, 'message' => 'ID œuvre ou artiste manquant']);
            exit;
        }

        $artwork_id = $data['artwork_id'];
        $artist_id = $data['artist_id'];

        // Construire la requête UPDATE dynamiquement
        $updates = [];
        
        if (isset($data['title']) && !empty($data['title'])) {
            $updates[] = "title = '" . SQLite3::escapeString($data['title']) . "'";
        }
        if (isset($data['price']) && !empty($data['price'])) {
            $updates[] = "price = " . floatval($data['price']);
        }
        if (isset($data['description'])) {
            $updates[] = "description = '" . SQLite3::escapeString($data['description']) . "'";
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'Aucune modification fournie']);
            exit;
        }

        $sql = "UPDATE artworks SET " . implode(', ', $updates) . " WHERE id = $artwork_id AND artist_id = $artist_id";
        
        if ($db->exec($sql)) {
            echo json_encode(['success' => true, 'message' => 'Œuvre mise à jour avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
        }
        exit;
    }

    // ============================================
    // ACTION INVALIDE
    // ============================================
    echo json_encode([
        'success' => false, 
        'message' => 'Action invalide ou méthode non autorisée',
        'action_received' => $action,
        'method_received' => $method
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
?>
