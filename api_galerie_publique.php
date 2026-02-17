<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 1. On appelle ton fichier magique qui gère la connexion
require_once __DIR__ . '/db_config.php';

try {
    // 2. On se connecte direct
    $db = getDatabase();

    // 3. On récupère les œuvres
    $stmt = $db->query("SELECT * FROM artworks ORDER BY id DESC");
    $oeuvres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $oeuvres
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur : " . $e->getMessage()
    ]);
}
?>
