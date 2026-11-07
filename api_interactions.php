<?php
/**
 * api_interactions.php — J'aime des œuvres (PostgreSQL, compte connecté obligatoire)
 *   GET  ?action=get_user_likes            → ids des œuvres aimées par l'utilisateur connecté
 *   POST {action:'toggle_like', artwork_id} → aime / n'aime plus
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

$auth    = arkylRequireAuth();
$userId  = (string) $auth['sub'];

try {
    $db = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (($_GET['action'] ?? '') !== 'get_user_likes') {
            arkylJsonError(400, 'Action inconnue.');
        }
        $st = $db->prepare('SELECT artwork_id FROM likes WHERE user_id = :u');
        $st->execute([':u' => $userId]);
        echo json_encode(['success' => true, 'data' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))]);
        exit;
    }

    $data   = arkylJsonBody();
    $action = $data['action'] ?? '';

    if ($action === 'toggle_like') {
        $artworkId = intval($data['artwork_id'] ?? 0);
        if ($artworkId <= 0) {
            arkylJsonError(400, 'artwork_id manquant.');
        }
        $del = $db->prepare('DELETE FROM likes WHERE user_id = :u AND artwork_id = :a');
        $del->execute([':u' => $userId, ':a' => $artworkId]);
        if ($del->rowCount() > 0) {
            echo json_encode(['success' => true, 'status' => 'unliked']);
        } else {
            $db->prepare('INSERT INTO likes (user_id, artwork_id) VALUES (:u, :a) ON CONFLICT DO NOTHING')
               ->execute([':u' => $userId, ':a' => $artworkId]);
            echo json_encode(['success' => true, 'status' => 'liked']);
        }
        exit;
    }

    arkylJsonError(400, 'Action inconnue.');
} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
