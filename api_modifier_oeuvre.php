<?php
// ==================== API MODIFIER ŒUVRE ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    $data = json_decode(file_get_contents('php://input'), true);

    $artwork_id  = intval($data['artwork_id']  ?? 0);
    $artist_id   = trim($data['artist_id']     ?? '');
    $title       = trim($data['title']         ?? '');
    $category    = trim($data['category']      ?? '');
    $price       = floatval($data['price']     ?? 0);
    $description = trim($data['description']   ?? '');
    $image_url   = $data['image_url']          ?? null;
    $photos      = $data['photos']             ?? [];
    $technique   = trim($data['technique']     ?? '');
    $dimensions  = $data['dimensions']         ?? null;

    if (!$artwork_id || !$artist_id || !$title || !$category) {
        throw new Exception("Champs obligatoires manquants (artwork_id, artist_id, title, category).");
    }

    // Vérifier que l'œuvre appartient bien à cet artiste
    $check = $db->prepare("SELECT id FROM artworks WHERE id = :id AND artist_id = :artist_id");
    $check->execute([':id' => $artwork_id, ':artist_id' => $artist_id]);
    if (!$check->fetch()) {
        throw new Exception("Œuvre introuvable ou accès refusé.");
    }

    // Construire le UPDATE dynamiquement selon si on a une nouvelle image
    $setClause = "title = :title, category = :category, price = :price, description = :description,
                  technique = :technique, dimensions = :dimensions, photos = :photos";
    $params = [
        ':title'       => $title,
        ':category'    => $category,
        ':price'       => $price,
        ':description' => $description,
        ':technique'   => $technique,
        ':dimensions'  => $dimensions ? json_encode($dimensions) : null,
        ':photos'      => !empty($photos) ? json_encode($photos) : null,
        ':id'          => $artwork_id,
        ':artist_id'   => $artist_id,
    ];

    if ($image_url) {
        $setClause .= ", image_url = :image_url";
        $params[':image_url'] = $image_url;
    }

    $stmt = $db->prepare("UPDATE artworks SET $setClause WHERE id = :id AND artist_id = :artist_id");
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Œuvre mise à jour avec succès.',
        'artwork_id' => $artwork_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
