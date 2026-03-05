<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Utiliser la configuration centralisée
require_once __DIR__ . '/db_config.php';

// 🔍 Log de débogage
error_log("api_galerie_publique.php appelée avec : " . print_r($_GET, true));

try {
    $db = getDatabase();
    
    // Vérifier si on demande UNE œuvre spécifique ou TOUTES
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
        
        // Formater les données
        $formatted = formatArtwork($oeuvre);
        
        echo json_encode([
            'success' => true,
            'data' => $formatted
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // ===== MODE : TOUTES LES ŒUVRES =====

        // ⭐ FIX : admin=1 → aucun filtre sur le statut ni is_sold
        //          (le panneau admin doit voir TOUTES les œuvres)
        $isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';

        $includeSold = isset($_GET['include_sold']) && $_GET['include_sold'] === '1';

        if ($isAdmin) {
            $sql = "SELECT * FROM artworks WHERE 1=1";
        } elseif ($includeSold) {
            // Page artiste ou mes artistes : montrer toutes les œuvres publiées (vendues incluses)
            $sql = "SELECT * FROM artworks WHERE status = 'publiée'";
        } else {
            // Galerie publique : uniquement les œuvres disponibles
            $sql = "SELECT * FROM artworks WHERE status = 'publiée' AND (is_sold IS NULL OR is_sold = FALSE)";
        }

        $params = [];
        
        // 🔐 Filtrer par artist_id si fourni
        if (isset($_GET['artist_id']) && !empty($_GET['artist_id'])) {
            $artistId = $_GET['artist_id']; // Peut être un ID interne, Google ID, ou email

            // ⭐ FIX : Google ID = chaîne numérique > 10 chiffres (ex: 115436233287965666535)
            // intval() déborde sur ces valeurs → comparer en TEXT
            if (is_numeric($artistId)) {
                // Comparer en TEXT pour éviter l'overflow int sur les Google IDs
                $sql .= " AND artist_id::text = :artist_id";
                $params[':artist_id'] = $artistId;
            } else {
                // C'est un email ou nom d'artiste (fallback)
                $sql .= " AND (artist_name = :artist_name OR artist_email = :artist_email)";
                $params[':artist_name'] = $artistId;
                $params[':artist_email'] = $artistId;
            }
        }
        
        $sql .= " ORDER BY id DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $oeuvres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater chaque œuvre
        $formatted = array_map('formatArtwork', $oeuvres);
        
        // Ajouter info de filtrage à la réponse
        $response = [
            'success' => true,
            'data' => $formatted,
            'count' => count($formatted)
        ];
        
        // 🔍 Debug: Ajouter info si filtrage appliqué
        if (isset($_GET['artist_id']) && !empty($_GET['artist_id'])) {
            $response['filtered_by'] = 'artist_id: ' . $_GET['artist_id'];
        }
        if ($isAdmin) {
            $response['mode'] = 'admin (tous statuts)';
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Fonction de formatage des œuvres
function formatArtwork($oeuvre) {
    // Gérer les dimensions
    $dimensions = null;
    if (!empty($oeuvre['width']) || !empty($oeuvre['height']) || !empty($oeuvre['depth'])) {
        $dimensions = json_encode([
            'width' => !empty($oeuvre['width']) ? floatval($oeuvre['width']) : null,
            'height' => !empty($oeuvre['height']) ? floatval($oeuvre['height']) : null,
            'depth' => !empty($oeuvre['depth']) ? floatval($oeuvre['depth']) : null
        ], JSON_UNESCAPED_UNICODE);
    } elseif (!empty($oeuvre['dimensions'])) {
        // Si dimensions existe déjà comme JSON string
        $dimensions = $oeuvre['dimensions'];
    }
    
    // Gérer les photos
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
        'techniqueCustom' => $oeuvre['technique_custom'] ?? null,
        'dimensions' => $dimensions,
        'description' => $oeuvre['description'] ?? null,
        // ⭐ FIX : artist_id retourné en String brut (pas intval → overflow sur Google IDs)
        'artist_id' => !empty($oeuvre['artist_id']) ? (string) $oeuvre['artist_id'] : null,
        'artist' => $oeuvre['artist_name'] ?? null,
        'artist_name' => $oeuvre['artist_name'] ?? null,
        'artist_country' => $oeuvre['artist_country'] ?? null,
        'badge' => $oeuvre['badge'] ?? 'Disponible',
        'status' => $oeuvre['status'] ?? 'active',
        'image' => !empty($photos) ? $photos[0] : null,
        'image_url' => !empty($photos) ? $photos[0] : null,
        'photos' => $photos,
        'created_at' => $oeuvre['created_at'] ?? null
    ];
}
?>
