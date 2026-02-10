<?php
/**
 * api_galerie_publique.php
 * Récupère TOUTES les œuvres actives pour l'affichage client (index.html)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Important pour que tout le monde puisse voir

try {
    $db = new SQLite3('artgallery.db');
    
    // On récupère l'oeuvre ET le nom de l'artiste grâce à un lien (JOIN) entre les tables
    $sql = "SELECT 
                a.id, 
                a.title, 
                a.description, 
                a.price, 
                a.image_url, 
                u.artist_name 
            FROM artworks a 
            LEFT JOIN artists u ON a.user_id = u.id 
            WHERE a.status = 'active' 
            ORDER BY a.created_at DESC";

    $result = $db->query($sql);

    $artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // On s'assure que si l'artiste n'a pas de nom, on met 'Artiste Inconnu'
        if (empty($row['artist_name'])) {
            $row['artist_name'] = 'Artiste ARKYL';
        }
        $artworks[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $artworks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
