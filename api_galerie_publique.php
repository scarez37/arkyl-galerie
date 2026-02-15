<?php
/**
 * API GALERIE PUBLIQUE - VERSION TEMPORAIRE
 * Compatible avec la structure actuelle (sans les colonnes photos, dimensions, etc.)
 * À REMPLACER après avoir exécuté update_database.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 1. Connexion à la base de données
    $db = new SQLite3('artgallery.db');
    $db->busyTimeout(5000);
    
    // 2. Vérifier si on demande une œuvre spécifique
    $artwork_id = isset($_GET['artwork_id']) ? intval($_GET['artwork_id']) : null;
    
    if ($artwork_id) {
        // ===== RÉCUPÉRER UNE ŒUVRE SPÉCIFIQUE =====
        $sql = "SELECT 
                    a.id, 
                    a.title, 
                    a.price, 
                    a.image_url, 
                    a.artist_id,
                    a.category,
                    a.description,
                    a.status,
                    a.created_at,
                    COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name,
                    u.email as artist_email
                FROM artworks a 
                LEFT JOIN artists u ON a.artist_id = u.id 
                WHERE a.id = :artwork_id
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception($db->lastErrorMsg());
        }
        
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$row) {
            echo json_encode([
                'success' => false, 
                'message' => 'Œuvre non trouvée'
            ]);
            exit();
        }
        
        // Construire l'objet œuvre avec valeurs par défaut pour colonnes manquantes
        $artwork = [
            'id' => intval($row['id']),
            'title' => $row['title'] ?? 'Sans titre',
            'artist_name' => $row['artist_name'] ?? 'Artiste inconnu',
            'artist_country' => null, // Pas de colonne country dans artists actuellement
            'artist_avatar' => null,  // Pas de colonne profile_image actuellement
            'category' => $row['category'] ?? 'Non spécifiée',
            'price' => intval($row['price'] ?? 0),
            'image_url' => $row['image_url'] ?? 'https://via.placeholder.com/300?text=Image+Indisponible',
            'photos' => [$row['image_url']], // Pour l'instant, une seule photo
            'description' => $row['description'] ?? 'Aucune description disponible.',
            'dimensions' => null, // Pas encore dans la BDD
            'technique' => null,  // Pas encore dans la BDD
            'techniqueCustom' => null, // Pas encore dans la BDD
            'badge' => ($row['status'] === 'active') ? 'Disponible' : 'Non disponible',
            'created_at' => $row['created_at'] ?? null
        ];
        
        echo json_encode([
            'success' => true, 
            'data' => $artwork
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // ===== RÉCUPÉRER TOUTES LES ŒUVRES =====
        $sql = "SELECT 
                    a.id, 
                    a.title, 
                    a.price, 
                    a.image_url, 
                    a.artist_id,
                    a.category,
                    a.description,
                    a.status,
                    a.created_at,
                    COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name,
                    u.email as artist_email
                FROM artworks a 
                LEFT JOIN artists u ON a.artist_id = u.id 
                WHERE a.status = 'active'
                ORDER BY a.id DESC";

        $result = $db->query($sql);

        if (!$result) {
            throw new Exception($db->lastErrorMsg());
        }

        $artworks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Construire l'objet œuvre avec valeurs par défaut
            $artworks[] = [
                'id' => intval($row['id']),
                'title' => $row['title'] ?? 'Sans titre',
                'artist_name' => $row['artist_name'] ?? 'Artiste inconnu',
                'artist_country' => null,
                'artist_avatar' => null,
                'category' => $row['category'] ?? 'Non spécifiée',
                'price' => intval($row['price'] ?? 0),
                'image_url' => $row['image_url'] ?? 'https://via.placeholder.com/300?text=Image+Indisponible',
                'photos' => [$row['image_url']], // Une seule photo pour l'instant
                'description' => $row['description'] ?? 'Aucune description disponible.',
                'dimensions' => null,
                'technique' => null,
                'techniqueCustom' => null,
                'badge' => 'Disponible',
                'created_at' => $row['created_at'] ?? null
            ];
        }

        echo json_encode([
            'success' => true, 
            'data' => $artworks,
            'count' => count($artworks)
        ], JSON_UNESCAPED_UNICODE);
    }

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
