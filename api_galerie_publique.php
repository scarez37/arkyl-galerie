<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Tout le monde peut lire

try {
    $db = new SQLite3('artgallery.db');
    
    // On sélectionne TOUTES les œuvres, peu importe l'artiste
    // On utilise LEFT JOIN pour récupérer le nom de l'artiste associé
    $sql = "SELECT 
                artworks.id, 
                artworks.title, 
                artworks.price, 
                artworks.image_url, 
                artworks.user_id,
                artists.artist_name 
            FROM artworks 
            LEFT JOIN artists ON artworks.user_id = artists.id 
            ORDER BY artworks.id DESC"; // Les plus récentes en premier

    $result = $db->query($sql);

    $all_artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Si l'artiste n'a pas de nom défini, on met un nom par défaut
        if (empty($row['artist_name'])) {
            $row['artist_name'] = 'Artiste ARKYL';
        }
        $all_artworks[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $all_artworks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
