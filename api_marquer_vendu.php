<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$artworkId = intval($data['artwork_id'] ?? 0);

if (!$artworkId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'artwork_id manquant ou invalide']);
    exit;
}

try {
    $db = getDatabase();

    // Vérifier que l'œuvre existe
    $check = $db->prepare("SELECT id, title FROM artworks WHERE id = ?");
    $check->execute([$artworkId]);
    $oeuvre = $check->fetch(PDO::FETCH_ASSOC);

    if (!$oeuvre) {
        echo json_encode(['success' => false, 'message' => "Œuvre #$artworkId introuvable"]);
        exit;
    }

    // Marquer comme vendue
    $stmt = $db->prepare("UPDATE artworks SET is_sold = TRUE, sold_at = NOW() WHERE id = ?");
    $stmt->execute([$artworkId]);

    error_log("✅ Œuvre #{$artworkId} ({$oeuvre['title']}) marquée comme vendue");

    echo json_encode([
        'success' => true,
        'artwork_id' => $artworkId,
        'title' => $oeuvre['title'],
        'message' => "Œuvre #{$artworkId} marquée comme vendue"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ api_marquer_vendu.php - Erreur : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
