<?php
// ==================== API COMPTER FAVORIS ====================
// Fichier: api_get_favorites_count.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $db = new PDO('sqlite:/opt/render/project/src/galerie.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
