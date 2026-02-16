<?php
/**
 * API GET FAVORIS
 * Récupère la liste des favoris d'un utilisateur avec les détails des œuvres
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    
    $user_id = $_GET['user_id'] ?? '';
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'User ID manquant'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Créer la table si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        artwork_id INTEGER NOT NULL,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, artwork_id),
        FOREIGN KEY (artwork_id) REFERENCES artworks(id)
    )");
    
    // Récupérer les favoris avec les détails des œuvres
    $stmt = $db->prepare("
        SELECT 
            f.id as favorite_id,
            f.added_at,
            a.*
        FROM favorites f
        INNER JOIN artworks a ON f.artwork_id = a.id
        WHERE f.user_id = :user_id
        ORDER BY f.added_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater chaque œuvre
    $formatted = [];
    foreach ($favorites as $fav) {
        // Gérer les photos
        $photos = [];
        if (!empty($fav['photos'])) {
            $decoded = json_decode($fav['photos'], true);
            if (is_array($decoded)) {
                $photos = $decoded;
            } else {
                $photos = [$fav['photos']];
            }
        } elseif (!empty($fav['image'])) {
            $photos = [$fav['image']];
        } elseif (!empty($fav['image_url'])) {
            $photos = [$fav['image_url']];
        }
        
        // Gérer les dimensions
        $dimensions = null;
        if (!empty($fav['width']) || !empty($fav['height']) || !empty($fav['depth'])) {
            $dimensions = json_encode([
                'width' => !empty($fav['width']) ? floatval($fav['width']) : null,
                'height' => !empty($fav['height']) ? floatval($fav['height']) : null,
                'depth' => !empty($fav['depth']) ? floatval($fav['depth']) : null
            ], JSON_UNESCAPED_UNICODE);
        }
        
        $formatted[] = [
            'id' => intval($fav['id']),
            'title' => $fav['title'] ?? 'Sans titre',
            'price' => !empty($fav['price']) ? floatval($fav['price']) : 0,
            'category' => $fav['category'] ?? null,
            'technique' => $fav['technique'] ?? null,
            'techniqueCustom' => $fav['techniqueCustom'] ?? null,
            'dimensions' => $dimensions,
            'description' => $fav['description'] ?? null,
            'artist' => $fav['artist'] ?? null,
            'artist_name' => $fav['artist_name'] ?? $fav['artist'] ?? null,
            'artist_country' => $fav['artist_country'] ?? null,
            'badge' => $fav['badge'] ?? 'Disponible',
            'image' => !empty($photos) ? $photos[0] : null,
            'image_url' => !empty($photos) ? $photos[0] : null,
            'photos' => $photos,
            'favorite_id' => $fav['favorite_id'],
            'added_at' => $fav['added_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
