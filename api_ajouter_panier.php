<?php
// ==================== API AJOUTER AU PANIER ====================
// Fichier: api_ajouter_panier.php
// Serveur: Render
// Base de données: SQLite

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:/opt/render/project/src/galerie.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données',
        'error' => $e->getMessage()
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
$user_id = $data['user_id'] ?? 'guest_' . session_id(); // ID utilisateur ou session
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;

// Vérification que l'œuvre existe
try {
    $stmt = $db->prepare("SELECT id, title, price FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode([
            'success' => false,
            'message' => 'Œuvre introuvable (ID: ' . $artwork_id . ')'
        ]);
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
        ]);
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
            'action' => 'added',
            'cart_id' => $db->lastInsertId()
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajout au panier',
        'error' => $e->getMessage()
    ]);
}
?>
