<?php
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

try {
    // Connexion à la base de données artgallery.db
    $db = new SQLite3('artgallery.db');
    $db->busyTimeout(5000);
    
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
    
    // Vérification que l'œuvre existe
    $stmt = $db->prepare("SELECT id, title, price FROM artworks WHERE id = :id");
    $stmt->bindValue(':id', $artwork_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $artwork = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$artwork) {
        echo json_encode([
            'success' => false,
            'message' => 'Œuvre introuvable (ID: ' . $artwork_id . ')'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Créer la table panier si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS cart (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        artwork_id INTEGER NOT NULL,
        quantity INTEGER DEFAULT 1,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (artwork_id) REFERENCES artworks(id)
    )");
    
    // Vérifier si l'œuvre est déjà dans le panier
    $checkStmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND artwork_id = :artwork_id");
    $checkStmt->bindValue(':user_id', $user_id, SQLITE3_TEXT);
    $checkStmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
    $checkResult = $checkStmt->execute();
    $existing = $checkResult->fetchArray(SQLITE3_ASSOC);
    
    if ($existing) {
        // Mettre à jour la quantité
        $newQuantity = $existing['quantity'] + $quantity;
        $updateStmt = $db->prepare("UPDATE cart SET quantity = :quantity WHERE id = :id");
        $updateStmt->bindValue(':quantity', $newQuantity, SQLITE3_INTEGER);
        $updateStmt->bindValue(':id', $existing['id'], SQLITE3_INTEGER);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour dans le panier',
            'action' => 'updated',
            'quantity' => $newQuantity
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Ajouter au panier
        $insertStmt = $db->prepare("INSERT INTO cart (user_id, artwork_id, quantity) VALUES (:user_id, :artwork_id, :quantity)");
        $insertStmt->bindValue(':user_id', $user_id, SQLITE3_TEXT);
        $insertStmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
        $insertStmt->bindValue(':quantity', $quantity, SQLITE3_INTEGER);
        $insertStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" ajoutée au panier',
            'action' => 'added',
            'cart_id' => $db->lastInsertRowID()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $db->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajout au panier',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
