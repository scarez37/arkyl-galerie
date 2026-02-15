<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Gestion du Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // LE SECRET EST ICI : On pointe vers la BONNE base de données
    $db = new SQLite3('artgallery.db');
    
    // On récupère toutes les œuvres, de la plus récente à la plus ancienne
    $result = $db->query("SELECT * FROM artworks ORDER BY id DESC");
    
    $oeuvres = [];
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $oeuvres[] = $row;
        }
    }

    // On envoie les données au Javascript de la page d'accueil
    echo json_encode([
        'success' => true,
        'data' => $oeuvres
    ]);

} catch (Exception $e) {
    // Si ça plante, on renvoie la vraie erreur pour comprendre
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Erreur du serveur : " . $e->getMessage()
    ]);
}
?>
