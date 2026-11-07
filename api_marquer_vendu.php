<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json; charset=utf-8');
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

    // Autorisé : administrateur, artiste propriétaire de l'œuvre, ou acheteur ayant une commande contenant cette œuvre
    // (le webhook Stripe marque déjà l'œuvre ; ceci n'est qu'un filet de sécurité côté navigateur).
    $authV = arkylRequireAuth();
    if (!arkylIsAdmin($authV)) {
        $own = $db->prepare('SELECT artist_id FROM artworks WHERE id = :id');
        $own->execute([':id' => intval($artwork_id)]);
        $owner = $own->fetchColumn();
        $allowed = ($owner !== false && arkylOwnsId($authV, $owner));
        if (!$allowed) {
            $bought = $db->prepare('SELECT o.user_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.artwork_id = :id');
            $bought->execute([':id' => intval($artwork_id)]);
            foreach ($bought->fetchAll(PDO::FETCH_COLUMN) as $buyer) {
                if (arkylOwnsId($authV, $buyer)) { $allowed = true; break; }
            }
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
            exit;
        }
    }

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
    echo json_encode(['success' => false, 'message' => arkylSafeError($e)]);
}
?>
