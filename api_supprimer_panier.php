<?php
// ==================== API PANIER — SUPPRIMER / METTRE À JOUR ====================
// Gère deux actions :
//   action=supprimer  → retire un article du panier
//   action=quantite   → met à jour la quantité d'un article

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
    $db = getDatabase();

    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    $action     = $data['action']     ?? '';
    $user_id    = $data['user_id']    ?? '';
    $artwork_id = intval($data['artwork_id'] ?? 0);

    if (empty($user_id) || $artwork_id <= 0 || empty($action)) {
        throw new Exception("Paramètres manquants (user_id, artwork_id, action).");
    }

    // ─────────────────────────────────────────────────────────────────
    // ACTION 1 — Supprimer un article du panier
    // ─────────────────────────────────────────────────────────────────
    if ($action === 'supprimer') {
        $stmt = $db->prepare("
            DELETE FROM cart
            WHERE user_id = :user_id AND artwork_id = :artwork_id
        ");
        $stmt->execute([
            ':user_id'    => $user_id,
            ':artwork_id' => $artwork_id,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Article retiré du panier.'
        ]);

    // ─────────────────────────────────────────────────────────────────
    // ACTION 2 — Mettre à jour la quantité
    // ─────────────────────────────────────────────────────────────────
    } elseif ($action === 'quantite') {
        $quantity = intval($data['quantity'] ?? 0);

        if ($quantity < 1 || $quantity > 10) {
            throw new Exception("Quantité invalide (1-10 autorisé).");
        }

        $stmt = $db->prepare("
            UPDATE cart
            SET quantity = :quantity
            WHERE user_id = :user_id AND artwork_id = :artwork_id
        ");
        $stmt->execute([
            ':quantity'   => $quantity,
            ':user_id'    => $user_id,
            ':artwork_id' => $artwork_id,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour.'
        ]);

    } else {
        throw new Exception("Action inconnue : $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
