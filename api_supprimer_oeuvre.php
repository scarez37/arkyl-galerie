<?php
// ==================== API SUPPRESSION D'ŒUVRE (VERSION POSTGRESQL) ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Connexion à la NOUVELLE base de données PostgreSQL
require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'œuvre manquant']);
        exit;
    }

    $artwork_id = intval($data['id']);

    // 2. Vérifier que l'œuvre existe
    $stmt = $db->prepare("SELECT id, title FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode(['success' => false, 'message' => 'Œuvre introuvable (ID: ' . $artwork_id . ')']);
        exit;
    }
    
    // 3. SUPPRESSION DE L'ŒUVRE
    $deleteStmt = $db->prepare("DELETE FROM artworks WHERE id = :id");
    $deleteStmt->execute([':id' => $artwork_id]);
    
    if ($deleteStmt->rowCount() > 0) {
        // Optionnel : Nettoyer le panier et les favoris en coulisses
        try {
            $db->prepare("DELETE FROM cart WHERE artwork_id = :id")->execute([':id' => $artwork_id]);
            $db->prepare("DELETE FROM favorites WHERE artwork_id = :id")->execute([':id' => $artwork_id]);
        } catch (Exception $e) {
            // On ignore silencieusement si ces tables n'existent pas encore
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" supprimée avec succès',
            'deleted_id' => $artwork_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer l\'œuvre.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
?>
