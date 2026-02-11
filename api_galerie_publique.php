<?php
/**
 * API GALERIE PUBLIQUE - VERSION FINALISÉE
 * Compatible avec la structure : artworks(artist_id) -> artists(id)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Permet à index.html de lire les données

try {
    // 1. Connexion à la base SQLite
    if (!file_exists('artgallery.db')) {
        throw new Exception("La base de données artgallery.db est introuvable.");
    }
    $db = new SQLite3('artgallery.db');
    
    // 2. La Requête SQL CORRIGÉE
    // On remplace 'user_id' par 'artist_id' pour correspondre à votre base
    $sql = "SELECT 
                a.id, 
                a.title, 
                a.category,
                a.price, 
                a.image_url, 
                a.description,
                a.artist_id,
                -- On récupère le nom de l'artiste depuis la table 'artists'
                COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name
            FROM artworks a 
            LEFT JOIN artists u ON a.artist_id = u.id 
            WHERE a.status = 'active' OR a.status IS NULL 
            ORDER BY a.id DESC";

    $result = $db->query($sql);

    if (!$result) {
        throw new Exception($db->lastErrorMsg());
    }

    $artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Sécurité image : si vide, on met une image par défaut
        if (empty($row['image_url'])) {
            $row['image_url'] = 'https://via.placeholder.com/400x300?text=Oeuvre+Arkyl'; 
        }
        
        // Nettoyage des données pour éviter les bugs d'affichage
        $row['price'] = $row['price'] . ' €'; 
        
        $artworks[] = $row;
    }

    // Réponse finale
    echo json_encode(['success' => true, 'count' => count($artworks), 'data' => $artworks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur SQL : " . $e->getMessage()]);
}
?>
