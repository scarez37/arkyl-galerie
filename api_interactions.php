<?php
/**
 * api_interactions.php - Gère les Likes et Commentaires
 */
session_start();
require_once 'config_mysql.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

try {
    if ($action === 'toggle_like') {
        $artwork_id = $data['artwork_id'];

        // Vérifier si le like existe déjà
        $check = $db->prepare("SELECT id FROM likes WHERE user_id = ? AND artwork_id = ?");
        $check->execute([$user_id, $artwork_id]);

        if ($check->fetch()) {
            // Si oui, on l'enlève (Unlike)
            $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ? AND artwork_id = ?");
            $stmt->execute([$user_id, $artwork_id]);
            echo json_encode(['success' => true, 'status' => 'unliked']);
        } else {
            // Si non, on l'ajoute (Like)
            $stmt = $db->prepare("INSERT INTO likes (user_id, artwork_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $artwork_id]);
            echo json_encode(['success' => true, 'status' => 'liked']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
