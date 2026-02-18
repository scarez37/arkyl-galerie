<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// On appelle le fichier de configuration central
require_once __DIR__ . '/db_config.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (empty($data)) $data = $_POST;

    if (empty($data['title'])) throw new Exception("Le titre de l'œuvre est manquant.");

    // Connexion à la base de données
    $db = getDatabase();

    $artist_id = !empty($data['artist_id']) ? $data['artist_id'] : 1;
    $artist_name = !empty($data['artist_name']) ? $data['artist_name'] : "Artiste Inconnu";
    $technique = !empty($data['technique']) ? $data['technique'] : 'Non spécifiée';

    // 🛠️ CORRECTION 1 : Gérer l'image (accepte 'image_url', 'image' ou 'photo')
    $imageUrlFinal = '';
    if (!empty($data['image_url'])) {
        $imageUrlFinal = $data['image_url'];
    } elseif (!empty($data['image'])) {
        $imageUrlFinal = $data['image'];
    } elseif (!empty($data['photo'])) {
        $imageUrlFinal = $data['photo'];
    }

    // 🛠️ CORRECTION 2 : Gérer les dimensions (accepte pluriel ou singulier)
    $dimBrut = !empty($data['dimensions']) ? $data['dimensions'] : (!empty($data['dimension']) ? $data['dimension'] : 'Non spécifiées');
    $dimensions = is_array($dimBrut) ? json_encode($dimBrut) : $dimBrut;

    // Gérer les photos multiples
    $photos = !empty($data['photos']) ? (is_array($data['photos']) ? json_encode($data['photos']) : $data['photos']) : '[]';

    // 🛠️ CORRECTION 3 : Sécurité pour forcer le bon statut à la publication
    $status = !empty($data['status']) ? $data['status'] : 'publiée';

    // On ajoute 'status' dans la requête pour être sûr qu'elle s'affiche direct
    $sql = "INSERT INTO artworks (title, price, image_url, artist_id, artist_name, description, category, technique, dimensions, photos, status) 
            VALUES (:title, :price, :image_url, :artist_id, :artist_name, :description, :category, :technique, :dimensions, :photos, :status)";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':title' => $data['title'],
        ':price' => $data['price'] ?? 0,
        ':image_url' => $imageUrlFinal,
        ':artist_id' => $artist_id,
        ':artist_name' => $artist_name,
        ':description' => $data['description'] ?? '',
        ':category' => $data['category'] ?? 'Art',
        ':technique' => $technique,
        ':dimensions' => $dimensions,
        ':photos' => $photos,
        ':status' => $status
    ]);

    echo json_encode(['success' => true, 'message' => "L'œuvre a été publiée avec succès !"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
