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

function getAllNews($db) {
    $stmt = $db->query("SELECT * FROM news ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $db = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'get';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // RÉCUPÉRER LES NEWS
    if ($method === 'GET' || $action === 'get') {
        echo json_encode(['success' => true, 'news' => getAllNews($db)]);
        exit;
    }

    // AJOUTER UNE NEWS
    if ($method === 'POST' && $action === 'add') {
        $stmt = $db->prepare(
            "INSERT INTO news (icon, gradient, text, is_image) VALUES (:icon, :gradient, :text, :is_image)"
        );
        $stmt->execute([
            ':icon'     => $body['icon']     ?? '📢',
            ':gradient' => $body['gradient'] ?? 'gradient-1',
            ':text'     => $body['text']     ?? '',
            ':is_image' => !empty($body['isImage']) ? 1 : 0,
        ]);
        echo json_encode(['success' => true, 'news' => getAllNews($db)]);
        exit;
    }

    // MODIFIER UNE NEWS
    if ($method === 'POST' && $action === 'update') {
        $id = intval($body['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
            exit;
        }
        $stmt = $db->prepare(
            "UPDATE news SET icon = :icon, gradient = :gradient, text = :text, is_image = :is_image WHERE id = :id"
        );
        $stmt->execute([
            ':icon'     => $body['icon']     ?? '📢',
            ':gradient' => $body['gradient'] ?? 'gradient-1',
            ':text'     => $body['text']     ?? '',
            ':is_image' => !empty($body['isImage']) ? 1 : 0,
            ':id'       => $id,
        ]);
        echo json_encode(['success' => true, 'news' => getAllNews($db)]);
        exit;
    }

    // SUPPRIMER UNE NEWS
    if ($method === 'POST' && $action === 'delete') {
        $id = intval($body['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
            exit;
        }
        $stmt = $db->prepare("DELETE FROM news WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'news' => getAllNews($db)]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue: ' . $action]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
