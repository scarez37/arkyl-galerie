<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

try {
    // 🆕 Utiliser la fonction de config au lieu de chercher manuellement
    $dbPath = getDatabasePath();
    
    if (!file_exists($dbPath)) {
        throw new Exception("Base de données introuvable : $dbPath");
    }

    // Connexion avec PDO
    $db = getDatabase(); // Utilise la fonction de db_config.php

    // 🆕 Vérifier si on demande UNE œuvre spécifique ou TOUTES
    if (isset($_GET['artwork_id']) && !empty($_GET['artwork_id'])) {
        // ===== MODE : UNE SEULE ŒUVRE =====
        $artworkId = intval($_GET['artwork_id']);
        
        $stmt = $db->prepare("SELECT * FROM artworks WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $artworkId]);
        $oeuvre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oeuvre) {
            echo json_encode([
                'success' => false,
                'message' => "Œuvre #$artworkId introuvable",
                'debug' => [
                    'db_path' => $dbPath,
                    'db_exists' => file_exists($dbPath),
                    'db_size' => filesize($dbPath)
                ]
            ]);
            exit;
        }
        
        // 🛡️ Formater les données pour JavaScript
        $formatted = formatArtwork($oeuvre);
        
        echo json_encode([
            'success' => true,
            'data' => $formatted,
            'debug' => [
                'db_path' => $dbPath,
                'raw_columns' => array_keys($oeuvre)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // ===== MODE : TOUTES LES ŒUVRES =====
        $stmt = $db->query("SELECT * FROM artworks WHERE status = 'active' ORDER BY id DESC");
        $oeuvres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater chaque œuvre
        $formatted = array_map('formatArtwork', $oeuvres);
        
        echo json_encode([
            'success' => true,
            'data' => $formatted,
            'count' => count($formatted),
            'debug' => [
                'db_path' => $dbPath
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur : " . $e->getMessage(),
        'debug' => getDebugInfo()
    ], JSON_UNESCAPED_UNICODE);
}

// 🔧 FONCTION DE FORMATAGE
function formatArtwork($oeuvre) {
    // Gérer les dimensions (width, height, depth)
    $dimensions = null;
    if (!empty($oeuvre['width']) || !empty($oeuvre['height']) || !empty($oeuvre['depth'])) {
        $dimensions = json_encode([
            'width' => !empty($oeuvre['width']) ? floatval($oeuvre['width']) : null,
            'height' => !empty($oeuvre['height']) ? floatval($oeuvre['height']) : null,
            'depth' => !empty($oeuvre['depth']) ? floatval($oeuvre['depth']) : null
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Gérer les photos (peut être un JSON array ou une seule photo)
    $photos = [];
    if (!empty($oeuvre['photos'])) {
        $decoded = json_decode($oeuvre['photos'], true);
        if (is_array($decoded)) {
            $photos = $decoded;
        } else {
            $photos = [$oeuvre['photos']];
        }
    } elseif (!empty($oeuvre['image'])) {
        $photos = [$oeuvre['image']];
    } elseif (!empty($oeuvre['image_url'])) {
        $photos = [$oeuvre['image_url']];
    }
    
    // Construire l'objet formaté
    return [
        'id' => intval($oeuvre['id'] ?? 0),
        'title' => $oeuvre['title'] ?? 'Sans titre',
        'price' => !empty($oeuvre['price']) ? floatval($oeuvre['price']) : 0,
        'category' => $oeuvre['category'] ?? null,
        'technique' => $oeuvre['technique'] ?? null,
        'techniqueCustom' => $oeuvre['techniqueCustom'] ?? null,
        'dimensions' => $dimensions, // STRING JSON format
        'description' => $oeuvre['description'] ?? null,
        'artist' => $oeuvre['artist'] ?? null,
        'artist_name' => $oeuvre['artist_name'] ?? $oeuvre['artist'] ?? null,
        'artist_country' => $oeuvre['artist_country'] ?? null,
        'badge' => $oeuvre['badge'] ?? 'Disponible',
        'image' => !empty($photos) ? $photos[0] : null,
        'image_url' => !empty($photos) ? $photos[0] : null,
        'photos' => $photos, // ARRAY format
        'created_at' => $oeuvre['created_at'] ?? null
    ];
}
?>
