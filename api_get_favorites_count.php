<?php
// ==================== API COMPTER FAVORIS ====================
// Fichier: api_get_favorites_count.php
// Utilise la configuration centralisée de la base de données

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase(); // Utilise la fonction de db_config.php
    
    $user_id = $_GET['user_id'] ?? '';
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'User ID manquant']);
        exit;
    }
    
    // Compter les favoris
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM favorites WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => intval($result['count'])
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur',
        'error' => $e->getMessage()
    ]);
}
?>
