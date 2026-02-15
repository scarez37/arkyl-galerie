<?php
// ==================== API SUPPRESSION D'ŒUVRE (VERSION CORRIGÉE) ====================
// Fichier: api_supprimer_oeuvre.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== CHERCHER LA BASE DE DONNÉES ====================
$possiblePaths = [
    '/opt/render/project/src/galerie.db',
    __DIR__ . '/galerie.db',
    '/var/data/galerie.db',
    '/tmp/galerie.db',
    getcwd() . '/galerie.db',
    dirname(__FILE__) . '/galerie.db'
];

$dbPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $dbPath = $path;
        break;
    }
}

if (!$dbPath) {
    $dbPath = __DIR__ . '/galerie.db';
}

// Connexion à la base de données
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données',
        'error' => $e->getMessage(),
        'db_path_tried' => $dbPath
    ]);
    exit;
}

// Récupération des données
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de l\'œuvre manquant'
    ]);
    exit;
}

$artwork_id = intval($data['id']);

try {
    // Vérifier que l'œuvre existe
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
    
    // SUPPRESSION DE L'ŒUVRE
    $deleteStmt = $db->prepare("DELETE FROM artworks WHERE id = :id");
    $deleteStmt->execute([':id' => $artwork_id]);
    
    if ($deleteStmt->rowCount() > 0) {
        // Supprimer aussi du panier et des favoris
        $db->exec("DELETE FROM cart WHERE artwork_id = $artwork_id");
        $db->exec("DELETE FROM favorites WHERE artwork_id = $artwork_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Œuvre "' . $artwork['title'] . '" supprimée avec succès',
            'deleted_id' => $artwork_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'La suppression a échoué'
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
