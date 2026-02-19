<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'get';
    $body = json_decode(file_get_contents('php://input'), true);

    // RÉCUPÉRER LES NEWS
    if ($method === 'GET' || ($method === 'POST' && $action === 'get')) {
        $stmt = $db->query("SELECT * FROM news ORDER BY id ASC");
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'news' => $news]);
        exit;
    }

    // AJOUTER UNE NEWS
    if ($method === 'POST' && $action === 'add') {
        $stmt = $db->prepare("INSERT INTO news (icon, gradient, text, is_image) VALUES (:icon, :gradient, :text, :is_image)");
        $stmt->execute([
            ':icon'     => $body['icon'] ?? '📢',
            ':gradient' => $body['gradient'] ?? 'gradient-1',
            ':text'     => $body['text'] ?? '',
            ':is_image' => isset($body['isImage']) ? ($body['isImage'] ? 1 : 0) : 0
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    // SUPPRIMER UNE NEWS
    if ($method === 'POST' && $action === 'delete') {
        $stmt = $db->prepare("DELETE FROM news WHERE id = :id");
        $stmt->execute([':id' => intval($body['id'])]);
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
