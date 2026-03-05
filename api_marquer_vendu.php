<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $artwork_id = $data['artwork_id'] ?? null;

    if (!$artwork_id) {
        echo json_encode(['success' => false, 'message' => 'artwork_id manquant']);
        exit;
    }

    $db = getDatabase();

    // Marquer l'œuvre comme vendue
    $stmt = $db->prepare("
        UPDATE artworks 
        SET is_sold = TRUE, badge = 'Vendu', status = 'vendue'
        WHERE id = :id
    ");
    $stmt->execute([':id' => intval($artwork_id)]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => "Œuvre #$artwork_id marquée vendue"]);
    } else {
        echo json_encode(['success' => false, 'message' => "Œuvre #$artwork_id introuvable"]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
