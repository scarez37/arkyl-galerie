<?php
// ==================== API NOTIFICATIONS ARTISTE ====================
// Lit et gère les notifications de vente stockées par webhook_stripe.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cross-Origin-Embedder-Policy: unsafe-none');
header('Cross-Origin-Opener-Policy: unsafe-none');
header('Cross-Origin-Resource-Policy: cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();

    // Créer la table si elle n'existe pas encore
    $db->exec("CREATE TABLE IF NOT EXISTS artist_notifications (
        id SERIAL PRIMARY KEY,
        artist_id VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'sale',
        order_id VARCHAR(100),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_notif_artist_id ON artist_notifications(artist_id)");

    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET : récupérer les notifications d'un artiste ──────────────
    if ($method === 'GET') {
        $action    = $_GET['action']    ?? 'get';
        $artist_id = $_GET['artist_id'] ?? '';

        if (empty($artist_id)) {
            echo json_encode(['success' => false, 'message' => 'artist_id manquant']);
            exit;
        }

        if ($action === 'get') {
            $stmt = $db->prepare("
                SELECT id, title, message, type, order_id, is_read, created_at
                FROM artist_notifications
                WHERE artist_id = :artist_id
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([':artist_id' => $artist_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countStmt = $db->prepare("
                SELECT COUNT(*) FROM artist_notifications
                WHERE artist_id = :artist_id AND is_read = FALSE
            ");
            $countStmt->execute([':artist_id' => $artist_id]);
            $unread = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success'      => true,
                'notifications' => $rows,
                'unread_count' => $unread,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── POST : marquer comme lues ────────────────────────────────────
    if ($method === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $artist_id = $input['artist_id'] ?? '';

        if ($action === 'mark_notifications_read' && !empty($artist_id)) {
            $db->prepare("
                UPDATE artist_notifications SET is_read = TRUE
                WHERE artist_id = :artist_id AND is_read = FALSE
            ")->execute([':artist_id' => $artist_id]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'mark_one_read' && !empty($input['notif_id'])) {
            $db->prepare("UPDATE artist_notifications SET is_read = TRUE WHERE id = :id")
               ->execute([':id' => intval($input['notif_id'])]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
