<?php
// ==================== API SUPPRESSION D'ŒUVRE ====================
// Fichier: api_supprimer_oeuvre.php
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
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de l\'œuvre manquant'
    ]);
    exit;
}

$artwork_id = intval($data['id']);

// Vérification que l'œuvre existe
try {
    $stmt = $db->prepare("SELECT id, title, artist_id FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode([
            'success' => false,
            'message' => 'Œuvre introuvable (ID: ' . $artwork_id . ')'
        ]);
        exit;
    }
    
    // Suppression de l'œuvre
    $deleteStmt = $db->prepare("DELETE FROM artworks WHERE id = :id");
    $deleteStmt->execute([':id' => $artwork_id]);
    
    // Vérification que la suppression a bien eu lieu
    if ($deleteStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" supprimée avec succès',
            'deleted_id' => $artwork_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'La suppression a échoué (aucune ligne affectée)'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression',
        'error' => $e->getMessage()
    ]);
}
?>
