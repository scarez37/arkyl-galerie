<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. On cherche la base de données là où elle existe (galerie.db ou artgallery.db)
    $possiblePaths = [
        '/opt/render/project/src/galerie.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/artgallery.db',
        __DIR__ . '/artgallery.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        throw new Exception("Base de données introuvable.");
    }

    // 2. Connexion avec PDO (la méthode préférée de ton serveur)
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Récupération des œuvres
    $stmt = $db->query("SELECT * FROM artworks ORDER BY id DESC");
    $oeuvres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $oeuvres
    ]);

} catch (Exception $e) {
    // Si ça plante, on renvoie une erreur JSON propre, pas de HTML !
    echo json_encode([
        'success' => false,
        'message' => "Erreur : " . $e->getMessage()
    ]);
}
?>
