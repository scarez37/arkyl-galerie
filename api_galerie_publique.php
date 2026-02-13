<?php
/**
 * API GALERIE PUBLIQUE - VERSION "PORTES OUVERTES"
 * Affiche TOUT sans restriction de statut.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // IMPORTANT : Autorise tous les appareils

try {
    $db = new SQLite3('artgallery.db');
    
    // REQUÊTE SIMPLIFIÉE AU MAXIMUM
    // On enlève "WHERE status = 'active'" pour être sûr que tout s'affiche
    $sql = "SELECT 
                a.id, 
                a.title, 
                a.price, 
                a.image_url, 
                a.artist_id,
                -- On récupère le nom, peu importe la table
                COALESCE(u.artist_name, u.name, 'Artiste') as artist_name
            FROM artworks a 
            LEFT JOIN artists u ON a.artist_id = u.id 
            ORDER BY a.id DESC"; // Les plus récentes en haut

    $result = $db->query($sql);

    $artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Petite sécurité si l'image est vide
        if (empty($row['image_url'])) {
            $row['image_url'] = 'https://via.placeholder.com/300?text=Image+Indisponible';
        }
        $artworks[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $artworks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
