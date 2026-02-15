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

    if (empty($data['id'])) {
        throw new Exception("ID de l'œuvre manquant.");
    }

    $db = new SQLite3('artgallery.db');
    
    // On prépare la suppression
    $stmt = $db->prepare("DELETE FROM artworks WHERE id = :id");
    $stmt->bindValue(':id', $data['id'], SQLITE3_INTEGER);
    
    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true, 'message' => "L'œuvre a été supprimée du serveur."]);
    } else {
        throw new Exception("Erreur lors de la suppression en base de données.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
