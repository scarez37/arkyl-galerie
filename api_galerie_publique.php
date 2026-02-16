<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. On cherche la base de données là où elle existe
    $possiblePaths = [
        '/opt/render/project/src/galerie.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/artgallery.db',
        __DIR__ . '/artgallery.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        throw new Exception("Base de données introuvable.");
    }

    // 2. Connexion avec PDO
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🆕 3. Vérifier si on demande UNE œuvre spécifique ou TOUTES
    if (isset($_GET['artwork_id']) && !empty($_GET['artwork_id'])) {
        // ===== MODE : UNE SEULE ŒUVRE =====
        $artworkId = intval($_GET['artwork_id']);
        
        $stmt = $db->prepare("SELECT * FROM artworks WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $artworkId]);
        $oeuvre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oeuvre) {
            echo json_encode([
                'success' => false,
                'message' => "Œuvre #$artworkId introuvable"
            ]);
            exit;
        }
        
        // 🛡️ Formater les données pour JavaScript
        $formatted = formatArtwork($oeuvre);
        
        echo json_encode([
            'success' => true,
            'data' => $formatted
        ]);
        
    } else {
        // ===== MODE : TOUTES LES ŒUVRES =====
        $stmt = $db->query("SELECT * FROM artworks ORDER BY id DESC");
        $oeuvres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater chaque œuvre
        $formatted = array_map('formatArtwork', $oeuvres);
        
        echo json_encode([
            'success' => true,
            'data' => $formatted
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur : " . $e->getMessage()
    ]);
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
