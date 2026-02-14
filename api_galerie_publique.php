<?php
/**
 * API GALERIE PUBLIQUE - VERSION CORRIGÉE
 * Affiche TOUTES les œuvres sans restriction
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // IMPORTANT : Autorise tous les appareils

try {
    // 1. Connexion à la base de données
    $db = new SQLite3('artgallery.db');
    
    // 2. REQUÊTE CORRIGÉE (artist_id au lieu de user_id)
    $sql = "SELECT 
                a.id, 
                a.title, 
                a.price, 
                a.image_url, 
                a.artist_id,
                COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name
            FROM artworks a 
            LEFT JOIN artists u ON a.artist_id = u.id 
            ORDER BY a.id DESC"; // Les plus récentes en haut

    $result = $db->query($sql);

    // Si la requête échoue, on arrête tout
    if (!$result) {
        throw new Exception($db->lastErrorMsg());
    }

    $artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Petite sécurité si l'image est vide
        if (empty($row['image_url'])) {
            $row['image_url'] = 'https://via.placeholder.com/300?text=Image+Indisponible';
        }
        $artworks[] = $row;
    }

    // On envoie le résultat final
    echo json_encode(['success' => true, 'data' => $artworks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur SQL : " . $e->getMessage()]);
}
?>
