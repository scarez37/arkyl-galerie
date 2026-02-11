<?php
/**
 * API GALERIE PUBLIQUE - VERSION CORRIGÉE ARKYL
 * Compatible avec votre base de données 'artgallery.db'
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Indispensable pour que index.html puisse lire
header('Access-Control-Allow-Methods: GET');

try {
    // 1. Connexion à la BONNE base de données (celle qui contient vos données)
    if (!file_exists('artgallery.db')) {
        throw new Exception("La base de données artgallery.db est introuvable.");
    }
    $db = new SQLite3('artgallery.db');
    
    // 2. Requête adaptée à votre structure réelle
    // On utilise COALESCE pour trouver le nom de l'artiste peu importe la table utilisée
    $sql = "SELECT 
                a.id, 
                a.title, 
                a.description, 
                a.price, 
                a.image_url, 
                a.created_at,
                -- On cherche le nom dans la table artists OU users
                COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name
            FROM artworks a 
            LEFT JOIN artists u ON a.user_id = u.id 
            WHERE a.status = 'active' OR a.status IS NULL 
            ORDER BY a.id DESC"; // Les plus récents en premier

    $result = $db->query($sql);

    $artworks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Sécurité pour l'image
        if (empty($row['image_url'])) {
            $row['image_url'] = 'assets/default_art.jpg'; // Image par défaut si vide
        }
        $artworks[] = $row;
    }

    // On renvoie la réponse propre
    echo json_encode([
        'success' => true, 
        'count' => count($artworks),
        'data' => $artworks
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // En cas d'erreur, on renvoie un message clair
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
