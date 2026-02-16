<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (empty($data)) $data = $_POST;

    if (empty($data['title'])) throw new Exception("Le titre de l'œuvre est manquant.");

    // Chercheur automatique de Base de Données
    $possiblePaths = [
        __DIR__ . '/artgallery.db',
        '/opt/render/project/src/artgallery.db',
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

    if (!$dbPath) throw new Exception("Base de données introuvable.");

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $artist_id = !empty($data['artist_id']) ? $data['artist_id'] : 1;
    $artist_name = !empty($data['artist_name']) ? $data['artist_name'] : "Artiste Inconnu";

    // LE SECRET EST LÀ : On ajoute TOUS les nouveaux champs (technique, dimensions, etc.)
    $sql = "INSERT INTO artworks (title, price, image_url, artist_id, artist_name, description, category, technique, dimensions) 
            VALUES (:title, :price, :image_url, :artist_id, :artist_name, :description, :category, :technique, :dimensions)";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':title' => $data['title'],
        ':price' => $data['price'] ?? 0,
        ':image_url' => $data['image_url'] ?? '',
        ':artist_id' => $artist_id,
        ':artist_name' => $artist_name,
        ':description' => $data['description'] ?? '',
        ':category' => $data['category'] ?? 'Art',
        ':technique' => $data['technique'] ?? 'Non spécifiée',
        ':dimensions' => $data['dimensions'] ?? 'Non spécifiées'
    ]);

    echo json_encode(['success' => true, 'message' => "L'œuvre a été publiée avec succès !"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
