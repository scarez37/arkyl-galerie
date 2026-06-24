<?php
require_once __DIR__ . '/cors_helper.php';

/**
 * API AJOUTER AU PANIER
 * Ajoute une œuvre au panier d'un client
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

try {
    // Connexion à la base de données via db_config.php
    $db = getDatabase();
    
    // Récupération des données POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validation des données
    if (!isset($data['artwork_id']) || empty($data['artwork_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de l\'œuvre manquant'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $artwork_id = intval($data['artwork_id']);
    $user_id = $data['user_id'] ?? 'guest_' . uniqid();
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    
    // 🛠️ SÉCURITÉ : On vérifie (et on crée) la table au cas où
    $db->exec("CREATE TABLE IF NOT EXISTS cart (
        id SERIAL PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        artwork_id INTEGER NOT NULL,
        quantity INTEGER DEFAULT 1,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
    )");
    
    // Vérification que l'œuvre existe
    $stmt = $db->prepare("SELECT id, title FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode([
            'success' => false,
            'message' => 'Œuvre introuvable'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Vérifier si l'œuvre est déjà dans le panier
    $checkStmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND artwork_id = :artwork_id");
    $checkStmt->execute([
        ':user_id' => $user_id,
        ':artwork_id' => $artwork_id
    ]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Mettre à jour la quantité
        $newQuantity = $existing['quantity'] + $quantity;
        $updateStmt = $db->prepare("UPDATE cart SET quantity = :quantity WHERE id = :id");
        $updateStmt->execute([
            ':quantity' => $newQuantity,
            ':id' => $existing['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour dans le panier',
            'action' => 'updated',
            'quantity' => $newQuantity
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Ajouter au panier
        $insertStmt = $db->prepare("INSERT INTO cart (user_id, artwork_id, quantity) VALUES (:user_id, :artwork_id, :quantity)");
        $insertStmt->execute([
            ':user_id' => $user_id,
            ':artwork_id' => $artwork_id,
            ':quantity' => $quantity
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" ajoutée au panier',
            'action' => 'added'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajout au panier',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
