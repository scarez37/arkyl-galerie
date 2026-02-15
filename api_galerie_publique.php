<?php
/**
 * API GALERIE PUBLIQUE - VERSION COMPLÈTE CORRIGÉE
 * Affiche TOUTES les œuvres avec TOUTES leurs informations
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
    $db->busyTimeout(5000); // Timeout de 5 secondes
    
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
                    a.photos,
                    a.dimensions,
                    a.technique,
                    a.technique_custom,
                    a.badge,
                    a.created_at,
                    COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name,
                    u.country as artist_country,
                    u.profile_image as artist_avatar
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
        
        // Décoder les photos JSON
        $photos = [];
        if (!empty($row['photos'])) {
            $decoded = json_decode($row['photos'], true);
            if (is_array($decoded)) {
                $photos = $decoded;
            }
        }
        // Fallback sur image_url si pas de photos
        if (empty($photos) && !empty($row['image_url'])) {
            $photos = [$row['image_url']];
        }
        
        // Décoder les dimensions JSON
        $dimensions = null;
        if (!empty($row['dimensions'])) {
            $decoded = json_decode($row['dimensions'], true);
            if (is_array($decoded)) {
                $dimensions = $decoded;
            }
        }
        
        // Construire l'objet œuvre complet
        $artwork = [
            'id' => intval($row['id']),
            'title' => $row['title'] ?? 'Sans titre',
            'artist_name' => $row['artist_name'] ?? 'Artiste inconnu',
            'artist_country' => $row['artist_country'] ?? null,
            'artist_avatar' => $row['artist_avatar'] ?? null,
            'category' => $row['category'] ?? 'Non spécifiée',
            'price' => intval($row['price'] ?? 0),
            'image_url' => $row['image_url'] ?? 'https://via.placeholder.com/300?text=Image+Indisponible',
            'photos' => $photos,
            'description' => $row['description'] ?? 'Aucune description disponible.',
            'dimensions' => $dimensions,
            'technique' => $row['technique'] ?? null,
            'techniqueCustom' => $row['technique_custom'] ?? null,
            'badge' => $row['badge'] ?? 'Disponible',
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
                    a.photos,
                    a.dimensions,
                    a.technique,
                    a.technique_custom,
                    a.badge,
                    a.created_at,
                    COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name,
                    u.country as artist_country,
                    u.profile_image as artist_avatar
                FROM artworks a 
                LEFT JOIN artists u ON a.artist_id = u.id 
                ORDER BY a.id DESC";

        $result = $db->query($sql);

        if (!$result) {
            throw new Exception($db->lastErrorMsg());
        }

        $artworks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Décoder les photos JSON
            $photos = [];
            if (!empty($row['photos'])) {
                $decoded = json_decode($row['photos'], true);
                if (is_array($decoded)) {
                    $photos = $decoded;
                }
            }
            // Fallback sur image_url si pas de photos
            if (empty($photos) && !empty($row['image_url'])) {
                $photos = [$row['image_url']];
            }
            
            // Décoder les dimensions JSON
            $dimensions = null;
            if (!empty($row['dimensions'])) {
                $decoded = json_decode($row['dimensions'], true);
                if (is_array($decoded)) {
                    $dimensions = $decoded;
                }
            }
            
            // Construire l'objet œuvre
            $artworks[] = [
                'id' => intval($row['id']),
                'title' => $row['title'] ?? 'Sans titre',
                'artist_name' => $row['artist_name'] ?? 'Artiste inconnu',
                'artist_country' => $row['artist_country'] ?? null,
                'artist_avatar' => $row['artist_avatar'] ?? null,
                'category' => $row['category'] ?? 'Non spécifiée',
                'price' => intval($row['price'] ?? 0),
                'image_url' => !empty($photos) ? $photos[0] : 'https://via.placeholder.com/300?text=Image+Indisponible',
                'photos' => $photos,
                'description' => $row['description'] ?? 'Aucune description disponible.',
                'dimensions' => $dimensions,
                'technique' => $row['technique'] ?? null,
                'techniqueCustom' => $row['technique_custom'] ?? null,
                'badge' => $row['badge'] ?? 'Disponible',
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
