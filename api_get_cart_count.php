<?php
// api_get_cart_count.php — nombre d'articles du panier (PostgreSQL)
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

try {
    $user_id = arkylActorId($_GET['user_id'] ?? '');   // visiteur anonyme OU compte connecté vérifié
    $db = getDatabase();
    $st = $db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = :u');
    $st->execute([':u' => $user_id]);
    echo json_encode(['success' => true, 'count' => (int) $st->fetchColumn()]);
} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
