<?php
/**
 * API ARKYL - Récupération de toutes les œuvres pour la galerie publique
 */
require_once 'config_mysql.php';
header('Content-Type: application/json');

try {
    $db = getDB();
    
    // On récupère les oeuvres avec le nom de l'artiste associé
    $sql = "SELECT artworks.*, artists.artist_name 
            FROM artworks 
            JOIN artists ON artworks.user_id = artists.id 
            ORDER BY artworks.created_at DESC";
            
    $stmt = $db->query($sql);
    $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $artworks
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur : ' . $e->getMessage()
    ]);
}
?>
