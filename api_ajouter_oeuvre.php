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
    // 1. Essayer de lire le flux JSON (méthode moderne)
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // 2. Si le JSON est vide, essayer de lire les données POST classiques
    if (empty($data)) {
        $data = $_POST;
    }

    // 3. Vérification de sécurité : si c'est toujours vide, là on s'arrête
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => "Le serveur n'a reçu aucune donnée exploitable."]);
        exit;
    }

    // 4. Connexion à la base
    $db = new SQLite3('artgallery.db');

    // 5. Préparation de la requête
    $sql = "INSERT INTO artworks (title, price, image_url, artist_id, description, category) 
            VALUES (:title, :price, :image_url, :artist_id, :description, :category)";
            
    $stmt = $db->prepare($sql);

    // On utilise l'ID de l'artiste ou on met 1 par défaut pour le test
    $artist_id = isset($data['artist_id']) ? $data['artist_id'] : 1;

    $stmt->bindValue(':title', $data['title'] ?? 'Sans titre', SQLITE3_TEXT);
    $stmt->bindValue(':price', $data['price'] ?? '0', SQLITE3_TEXT);
    $stmt->bindValue(':image_url', $data['image_url'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
    $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':category', $data['category'] ?? 'Art', SQLITE3_TEXT);

    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true, 'message' => "L'œuvre a été publiée avec succès !"]);
    } else {
        throw new Exception("Erreur lors de l'insertion en base de données.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
}
?>
