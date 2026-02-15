<?php
// ==================== API AJOUTER AUX FAVORIS ====================
// Fichier: api_ajouter_favoris.php
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
    
    // Créer la table favoris si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        artwork_id INTEGER NOT NULL,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, artwork_id),
        FOREIGN KEY (artwork_id) REFERENCES artworks(id)
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
            'is_favorite' => true,
            'favorite_id' => $db->lastInsertId()
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la gestion des favoris',
        'error' => $e->getMessage()
    ]);
}
?>
