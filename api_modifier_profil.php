<?php
/**
 * api_artiste_profil.php
 * Retourne le profil public d'un artiste depuis la table users
 * 
 * GET ?artist_name=NomArtiste  → profil par nom
 * GET ?artist_id=123           → profil par ID
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();

    $artistName = trim($_GET['artist_name'] ?? '');
    $artistId   = trim($_GET['artist_id']   ?? '');

    if (!$artistName && !$artistId) {
        echo json_encode(['success' => false, 'error' => 'Paramètre artist_name ou artist_id requis']);
        exit;
    }

    if ($artistId) {
        $stmt = $db->prepare("SELECT id, name, phone, country, specialty, bio, website, social, avatar FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $artistId]);
    } else {
        $stmt = $db->prepare("SELECT id, name, phone, country, specialty, bio, website, social, avatar FROM users WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
        $stmt->execute([':name' => $artistName]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'artist' => null]);
        exit;
    }

    // Compter les œuvres de cet artiste
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM artworks WHERE artist_id = :id");
    $countStmt->execute([':id' => $user['id']]);
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'artist'  => [
            'id'            => $user['id'],
            'name'          => $user['name'],
            'avatar'        => $user['avatar'],
            'bio'           => $user['bio'],
            'specialty'     => $user['specialty'],
            'country'       => $user['country'],
            'website'       => $user['website'],
            'social'        => $user['social'],
            'artworks_count'=> (int)($countRow['total'] ?? 0),
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
