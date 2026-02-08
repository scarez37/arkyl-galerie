<?php
require_once 'config_mysql.php';
header('Content-Type: application/json');

try {
    $db = getDB();
    // On récupère les oeuvres ET le nom de l'artiste qui l'a postée
    $stmt = $db->query("
        SELECT artworks.*, artists.artist_name 
        FROM artworks 
        JOIN artists ON artworks.user_id = artists.id 
        ORDER BY artworks.created_at DESC
    ");
    $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $artworks]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
