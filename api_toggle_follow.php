<?php
// api_toggle_follow.php — s'abonner / se désabonner d'un artiste (compte connecté obligatoire)
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

try {
    $data      = arkylJsonBody();
    $user_id   = arkylActorId($data['user_id'] ?? '', false);   // doit être le compte connecté
    $artist_id = trim((string) ($data['artist_id'] ?? ''));

    if ($artist_id === '') {
        arkylJsonError(400, 'Paramètres manquants.');
    }
    $db = getDatabase();

    $del = $db->prepare('DELETE FROM followers WHERE user_id = :u AND artist_id = :a');
    $del->execute([':u' => $user_id, ':a' => $artist_id]);
    if ($del->rowCount() > 0) {
        echo json_encode(['success' => true, 'action' => 'unfollowed']);
    } else {
        $db->prepare('INSERT INTO followers (user_id, artist_id) VALUES (:u, :a) ON CONFLICT DO NOTHING')
           ->execute([':u' => $user_id, ':a' => $artist_id]);
        echo json_encode(['success' => true, 'action' => 'followed']);
    }
} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
