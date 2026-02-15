<?php
/**
 * API AJOUTER ŒUVRE
 * Permet aux artistes de publier leurs œuvres dans la galerie
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Lire les données JSON envoyées
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    // Debug : Log des données reçues
    error_log("📥 Données reçues : " . print_r($data, true));
    
    if (!$data) {
        throw new Exception('Aucune donnée reçue ou JSON invalide');
    }
    
    // Validation des champs obligatoires
    $requiredFields = ['title', 'category', 'price', 'image_url', 'artist_id'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Champs manquants : ' . implode(', ', $missingFields));
    }
    
    // Connexion à la base de données artgallery.db
    $db = new SQLite3('artgallery.db');
    $db->busyTimeout(5000);
    
    // Vérifier que l'artist_id existe dans la table artists
    $artistCheck = $db->prepare("SELECT id FROM artists WHERE id = :artist_id");
    $artistCheck->bindValue(':artist_id', $data['artist_id'], SQLITE3_INTEGER);
    $artistResult = $artistCheck->execute();
    
    if (!$artistResult->fetchArray()) {
        throw new Exception('Artist ID ' . $data['artist_id'] . ' introuvable dans la base de données');
    }
    
    // Préparer les données pour l'insertion
    $title = $data['title'];
    $category = $data['category'];
    $price = floatval($data['price']);
    $image_url = $data['image_url'];
    $artist_id = intval($data['artist_id']);
    $description = isset($data['description']) ? $data['description'] : '';
    
    // Gérer les photos multiples (si envoyées)
    $photos = null;
    if (isset($data['photos']) && is_array($data['photos'])) {
        $photos = json_encode($data['photos']);
    } else if (!empty($image_url)) {
        // Si pas de tableau photos, créer un tableau avec l'image principale
        $photos = json_encode([$image_url]);
    }
    
    // Gérer les dimensions (si envoyées)
    $dimensions = null;
    if (isset($data['dimensions']) && is_array($data['dimensions'])) {
        $dimensions = json_encode($data['dimensions']);
    }
    
    // Gérer la technique
    $technique = isset($data['technique']) ? $data['technique'] : null;
    $technique_custom = isset($data['technique_custom']) ? $data['technique_custom'] : null;
    
    // Badge par défaut
    $badge = isset($data['badge']) ? $data['badge'] : 'Disponible';
    
    // Préparer la requête d'insertion
    $sql = "INSERT INTO artworks (
        artist_id,
        title,
        category,
        price,
        image_url,
        description,
        photos,
        dimensions,
        technique,
        technique_custom,
        badge,
        status,
        created_at
    ) VALUES (
        :artist_id,
        :title,
        :category,
        :price,
        :image_url,
        :description,
        :photos,
        :dimensions,
        :technique,
        :technique_custom,
        :badge,
        'active',
        datetime('now')
    )";
    
    $stmt = $db->prepare($sql);
    
    // Bind des valeurs
    $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':category', $category, SQLITE3_TEXT);
    $stmt->bindValue(':price', $price, SQLITE3_FLOAT);
    $stmt->bindValue(':image_url', $image_url, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':photos', $photos, SQLITE3_TEXT);
    $stmt->bindValue(':dimensions', $dimensions, SQLITE3_TEXT);
    $stmt->bindValue(':technique', $technique, SQLITE3_TEXT);
    $stmt->bindValue(':technique_custom', $technique_custom, SQLITE3_TEXT);
    $stmt->bindValue(':badge', $badge, SQLITE3_TEXT);
    
    // Exécuter l'insertion
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de l\'insertion : ' . $db->lastErrorMsg());
    }
    
    // Récupérer l'ID de l'œuvre créée
    $newArtworkId = $db->lastInsertRowID();
    
    // Log de succès
    error_log("✅ Œuvre créée avec ID : " . $newArtworkId);
    
    $db->close();
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Œuvre publiée avec succès !',
        'artwork_id' => $newArtworkId,
        'data' => [
            'id' => $newArtworkId,
            'title' => $title,
            'category' => $category,
            'price' => $price,
            'artist_id' => $artist_id
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log de l'erreur
    error_log("❌ Erreur ajout œuvre : " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'received_data' => isset($data) ? $data : null,
            'error_details' => $e->getMessage()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
