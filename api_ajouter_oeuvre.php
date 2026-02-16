<?php
/**
 * API AJOUTER ŒUVRE - VERSION ULTRA-ROBUSTE
 * Cherche la BDD, ajoute les colonnes manquantes, ne plante jamais
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. LIRE LES DONNÉES (JSON ou POST)
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (empty($data)) {
        $data = $_POST;
    }
    
    error_log("📥 Données reçues : " . print_r($data, true));

    // 2. VALIDATION DES CHAMPS OBLIGATOIRES
    if (empty($data['title'])) {
        throw new Exception("Le titre de l'œuvre est manquant.");
    }
    
    if (empty($data['image_url'])) {
        throw new Exception("L'image de l'œuvre est manquante.");
    }

    // 3. CHERCHEUR AUTOMATIQUE DE BASE DE DONNÉES
    $possiblePaths = [
        __DIR__ . '/artgallery.db',
        '/opt/render/project/src/artgallery.db',
        '/var/www/html/artgallery.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/galerie.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        throw new Exception("Base de données introuvable. Chemins testés : " . implode(', ', $possiblePaths));
    }

    error_log("✅ Base trouvée : $dbPath");

    // 4. CONNEXION AVEC PDO
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 5. AUTO-RÉPARATION : AJOUTER LES COLONNES MANQUANTES
    $columnsToAdd = [
        "ALTER TABLE artworks ADD COLUMN artist_id INTEGER DEFAULT 1",
        "ALTER TABLE artworks ADD COLUMN photos TEXT",
        "ALTER TABLE artworks ADD COLUMN dimensions TEXT",
        "ALTER TABLE artworks ADD COLUMN technique TEXT",
        "ALTER TABLE artworks ADD COLUMN technique_custom TEXT",
        "ALTER TABLE artworks ADD COLUMN badge TEXT DEFAULT 'Disponible'",
        "ALTER TABLE artworks ADD COLUMN status TEXT DEFAULT 'active'"
    ];
    
    foreach ($columnsToAdd as $sql) {
        try {
            $db->exec($sql);
            error_log("✅ Colonne ajoutée");
        } catch (Exception $e) {
            // Colonne existe déjà, on continue
        }
    }

    // 6. PRÉPARER LES VALEURS
    $title = trim($data['title']);
    $category = !empty($data['category']) ? trim($data['category']) : 'Art';
    $price = !empty($data['price']) ? floatval($data['price']) : 0;
    $image_url = $data['image_url'];
    $description = !empty($data['description']) ? trim($data['description']) : '';
    
    // Artist ID avec fallback
    $artist_id = 1; // Valeur par défaut
    if (!empty($data['artist_id'])) {
        $artist_id = intval($data['artist_id']);
    } else if (!empty($_SESSION['user_id'])) {
        $artist_id = intval($_SESSION['user_id']);
    }
    // Note: localStorage n'existe que côté client, pas en PHP
    
    $artist_name = !empty($data['artist_name']) ? trim($data['artist_name']) : 'Artiste ARKYL';
    
    // Photos multiples (si envoyées)
    $photos = null;
    if (!empty($data['photos']) && is_array($data['photos'])) {
        $photos = json_encode($data['photos']);
    } else {
        $photos = json_encode([$image_url]);
    }
    
    // Dimensions (si envoyées)
    $dimensions = null;
    if (!empty($data['dimensions']) && is_array($data['dimensions'])) {
        $dimensions = json_encode($data['dimensions']);
    }
    
    // Technique
    $technique = !empty($data['technique']) ? $data['technique'] : null;
    $technique_custom = !empty($data['technique_custom']) ? $data['technique_custom'] : null;
    
    // Badge
    $badge = !empty($data['badge']) ? $data['badge'] : 'Disponible';

    // 7. INSÉRER L'ŒUVRE
    $sql = "INSERT INTO artworks (
        title, 
        category, 
        price, 
        image_url, 
        description,
        artist_id,
        photos,
        dimensions,
        technique,
        technique_custom,
        badge,
        status,
        created_at
    ) VALUES (
        :title,
        :category,
        :price,
        :image_url,
        :description,
        :artist_id,
        :photos,
        :dimensions,
        :technique,
        :technique_custom,
        :badge,
        'active',
        datetime('now')
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':category' => $category,
        ':price' => $price,
        ':image_url' => $image_url,
        ':description' => $description,
        ':artist_id' => $artist_id,
        ':photos' => $photos,
        ':dimensions' => $dimensions,
        ':technique' => $technique,
        ':technique_custom' => $technique_custom,
        ':badge' => $badge
    ]);

    $newId = $db->lastInsertId();
    
    error_log("✅ Œuvre créée avec ID : $newId");

    // 8. RÉPONSE DE SUCCÈS
    echo json_encode([
        'success' => true, 
        'message' => 'Œuvre publiée avec succès !',
        'artwork_id' => intval($newId),
        'artist_id' => $artist_id,
        'db_path' => $dbPath
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ Erreur publication : " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'received_data' => isset($data) ? array_keys($data) : []
    ], JSON_UNESCAPED_UNICODE);
}
?>
