<?php
/**
 * API D'AJOUT D'ŒUVRE - ARKYL
 * Reçoit les données du tableau de bord et les enregistre dans la base de données SQLite.
 */

// Autoriser les communications avec ton site
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer la vérification de sécurité du navigateur (Preflight OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. Lire les données envoyées par le Javascript
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception("Aucune donnée reçue ou format incorrect.");
    }

    // 2. Vérifier que l'ID de l'artiste est bien là (c'est crucial !)
    if (empty($data['artist_id'])) {
        throw new Exception("Erreur : l'ID de l'artiste est manquant.");
    }

    // 3. Connexion à la base de données
    $db = new SQLite3('artgallery.db');

    // 4. Préparer l'insertion dans la base de données
    // On s'assure d'utiliser les bons noms de colonnes
    $sql = "INSERT INTO artworks (title, price, image_url, artist_id, description, category) 
            VALUES (:title, :price, :image_url, :artist_id, :description, :category)";
            
    $stmt = $db->prepare($sql);

    // 5. Remplir les "trous" avec les vraies données
    $stmt->bindValue(':title', $data['title'] ?? 'Sans titre', SQLITE3_TEXT);
    $stmt->bindValue(':price', $data['price'] ?? '0', SQLITE3_TEXT);
    $stmt->bindValue(':image_url', $data['image_url'] ?? 'https://via.placeholder.com/300?text=Image+Indisponible', SQLITE3_TEXT);
    $stmt->bindValue(':artist_id', $data['artist_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':category', $data['category'] ?? 'Art', SQLITE3_TEXT);

    // 6. Exécuter l'enregistrement
    $result = $stmt->execute();

    if ($result) {
        // Succès !
        echo json_encode(['success' => true, 'message' => 'L\'œuvre a été publiée avec succès sur le serveur !']);
    } else {
        throw new Exception("Impossible d'enregistrer l'œuvre dans la base de données.");
    }

} catch (Exception $e) {
    // S'il y a la moindre erreur, on prévient le Javascript
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
