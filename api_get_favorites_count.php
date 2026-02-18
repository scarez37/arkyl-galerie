<?php
// ==================== API COMPTER FAVORIS ====================
// Fichier: api_get_favorites_count.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 🛠️ CORRECTION : On utilise db_config.php pour PostgreSQL !
require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    
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
