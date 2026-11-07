<?php
require_once __DIR__ . '/auth_helper.php';
// ==================== API AJOUTER AUX FAVORIS ====================
// Fichier: api_ajouter_favoris.php

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

// Connexion à la base de données PostgreSQL
try {
    $db = getDatabase(); 
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données',
        'error' => arkylSafeError($e)
    ]);
    exit;
}

// Récupération des données POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validation des données
if (!isset($data['artwork_id']) || empty($data['artwork_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de l\'œuvre manquant'
    ]);
    exit;
}

$artwork_id = intval($data['artwork_id']);
$user_id = arkylActorId($data['user_id'] ?? '');

// Vérification que l'œuvre existe
try {
    $stmt = $db->prepare("SELECT id, title FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode([
            'success' => false,
            'message' => 'Œuvre introuvable (ID: ' . $artwork_id . ')'
        ]);
        exit;
    }
    
    // 🛠️ CORRECTION : Syntaxe PostgreSQL avec SERIAL et TIMESTAMP
    $db->exec("CREATE TABLE IF NOT EXISTS favorites (
        id SERIAL PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        artwork_id INTEGER NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, artwork_id),
        FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
    )");
    
    // Vérifier si l'œuvre est déjà dans les favoris
    $checkStmt = $db->prepare("SELECT id FROM favorites WHERE user_id = :user_id AND artwork_id = :artwork_id");
    $checkStmt->execute([
        ':user_id' => $user_id,
        ':artwork_id' => $artwork_id
    ]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Retirer des favoris (toggle)
        $deleteStmt = $db->prepare("DELETE FROM favorites WHERE id = :id");
        $deleteStmt->execute([':id' => $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre retirée des favoris',
            'action' => 'removed',
            'is_favorite' => false
        ]);
    } else {
        // Ajouter aux favoris
        $insertStmt = $db->prepare("INSERT INTO favorites (user_id, artwork_id) VALUES (:user_id, :artwork_id)");
        $insertStmt->execute([
            ':user_id' => $user_id,
            ':artwork_id' => $artwork_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" ajoutée aux favoris',
            'action' => 'added',
            'is_favorite' => true
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la gestion des favoris',
        'error' => arkylSafeError($e)
    ]);
}
?>
