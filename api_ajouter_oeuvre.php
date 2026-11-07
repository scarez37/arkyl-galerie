<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/cors_helper.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_config.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (empty($data)) $data = $_POST;

    if (empty($data['title'])) throw new Exception("Le titre de l'œuvre est manquant.");

    $db = getDatabase();

    $authO = arkylRequireAuth();
    if (!arkylIsAdmin($authO) && arkylArtistId($authO) === null) {
        arkylJsonError(403, 'Créez d\'abord votre compte artiste pour publier une œuvre.');
    }
    // L'œuvre est publiée au nom du compte connecté (jamais d'artiste par défaut)
    $artist_id   = !empty($data['artist_id']) ? arkylActorId($data['artist_id'], false) : (arkylArtistId($authO) ?? (string) $authO['sub']);
    $artist_name = !empty($data['artist_name']) ? $data['artist_name'] : "Artiste Inconnu";
    $technique   = !empty($data['technique'])   ? $data['technique']   : 'Non spécifiée';

    // Gérer l'image (accepte 'image_url', 'image' ou 'photo')
    $imageUrlFinal = '';
    if (!empty($data['image_url'])) {
        $imageUrlFinal = $data['image_url'];
    } elseif (!empty($data['image'])) {
        $imageUrlFinal = $data['image'];
    } elseif (!empty($data['photo'])) {
        $imageUrlFinal = $data['photo'];
    }

    // Gérer les dimensions
    $dimBrut    = !empty($data['dimensions']) ? $data['dimensions'] : (!empty($data['dimension']) ? $data['dimension'] : 'Non spécifiées');
    $dimensions = is_array($dimBrut) ? json_encode($dimBrut) : $dimBrut;

    // Gérer les photos multiples
    $photos = !empty($data['photos']) ? (is_array($data['photos']) ? json_encode($data['photos']) : $data['photos']) : '[]';

    $status = !empty($data['status']) ? $data['status'] : 'publiée';

    // ✅ Récupérer country, city et artist_country
    $country        = !empty($data['country'])        ? $data['country']        : '';
    $city           = !empty($data['city'])           ? $data['city']           : '';
    $artist_country = !empty($data['artist_country']) ? $data['artist_country'] : $country;
    $poids          = isset($data['poids'])            ? floatval($data['poids'])  : null;

    $sql = "INSERT INTO artworks 
                (title, price, image_url, artist_id, artist_name, description, category, technique, dimensions, photos, status, country, city, artist_country, poids) 
            VALUES 
                (:title, :price, :image_url, :artist_id, :artist_name, :description, :category, :technique, :dimensions, :photos, :status, :country, :city, :artist_country, :poids)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':title'          => $data['title'],
        ':price'          => $data['price'] ?? 0,
        ':image_url'      => $imageUrlFinal,
        ':artist_id'      => $artist_id,
        ':artist_name'    => $artist_name,
        ':description'    => $data['description'] ?? '',
        ':category'       => $data['category'] ?? 'Art',
        ':technique'      => $technique,
        ':dimensions'     => $dimensions,
        ':photos'         => $photos,
        ':status'         => $status,
        ':country'        => $country,
        ':city'           => $city,
        ':artist_country' => $artist_country,
        ':poids'          => $poids,
    ]);

    $newId = $db->lastInsertId();

    echo json_encode([
        'success'    => true,
        'message'    => "L'œuvre a été publiée avec succès !",
        'id'         => $newId,
        'artwork_id' => $newId
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => arkylSafeError($e)]);
}
?>
