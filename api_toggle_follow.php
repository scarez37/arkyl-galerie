<?php
// Fichier : api_toggle_follow.php
require_once __DIR__ . '/db_config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDatabase();
    $data = json_decode(file_get_contents('php://input'), true);

    $user_id = $data['user_id'] ?? null;
    $artist_id = $data['artist_id'] ?? null;

    if (!$user_id || !$artist_id) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }

    // On vérifie si l'abonnement existe déjà
    $stmt = $db->prepare("SELECT * FROM followers WHERE user_id = :u AND artist_id = :a");
    $stmt->execute([':u' => $user_id, ':a' => $artist_id]);
    
    if ($stmt->fetch()) {
        // Il est déjà abonné -> ON LE DÉSABONNE
        $del = $db->prepare("DELETE FROM followers WHERE user_id = :u AND artist_id = :a");
        $del->execute([':u' => $user_id, ':a' => $artist_id]);
        echo json_encode(['success' => true, 'action' => 'unfollowed']);
    } else {
        // Il n'est pas abonné -> ON L'ABONNE
        $ins = $db->prepare("INSERT INTO followers (user_id, artist_id) VALUES (:u, :a)");
        $ins->execute([':u' => $user_id, ':a' => $artist_id]);
        echo json_encode(['success' => true, 'action' => 'followed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
