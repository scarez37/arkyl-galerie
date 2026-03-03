<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_config.php';

function getAllPosts($db) {
    $stmt = $db->query("SELECT * FROM artist_posts ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['comments'] = json_decode($row['comments'] ?? '[]', true) ?? [];
        $row['likes']    = intval($row['likes'] ?? 0);
    }
    return $rows;
}

try {
    $db = getDatabase();

    // Créer la table si elle n'existe pas encore
    $db->exec("
        CREATE TABLE IF NOT EXISTS artist_posts (
            id            VARCHAR(64)  PRIMARY KEY,
            artist_id     TEXT         NOT NULL,
            artist_name   TEXT         NOT NULL,
            artist_avatar TEXT         DEFAULT '',
            media_url     TEXT         NOT NULL,
            media_type    VARCHAR(10)  DEFAULT 'image',
            caption       TEXT         DEFAULT '',
            likes         INTEGER      DEFAULT 0,
            comments      TEXT         DEFAULT '[]',
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'get';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // GET — récupérer tous les posts
    if ($method === 'GET' || $action === 'get') {
        echo json_encode(['success' => true, 'posts' => getAllPosts($db)]);
        exit;
    }

    // POST add — publier un nouveau post
    if ($method === 'POST' && $action === 'add') {
        $id = $body['id'] ?? ('post_' . time() . '_' . bin2hex(random_bytes(4)));
        $stmt = $db->prepare("
            INSERT INTO artist_posts
                (id, artist_id, artist_name, artist_avatar, media_url, media_type, caption, likes, comments, created_at)
            VALUES
                (:id, :artist_id, :artist_name, :artist_avatar, :media_url, :media_type, :caption, 0, '[]', :created_at)
            ON CONFLICT(id) DO NOTHING
        ");
        $stmt->execute([
            ':id'            => $id,
            ':artist_id'     => $body['artist_id']     ?? '',
            ':artist_name'   => $body['artist_name']   ?? 'Artiste',
            ':artist_avatar' => $body['artist_avatar']  ?? '',
            ':media_url'     => $body['media_url']     ?? '',
            ':media_type'    => $body['media_type']    ?? 'image',
            ':caption'       => $body['caption']       ?? '',
            ':created_at'    => $body['created_at']    ?? date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true, 'posts' => getAllPosts($db)]);
        exit;
    }

    // POST like — incrémenter/décrémenter les likes
    if ($method === 'POST' && $action === 'like') {
        $id    = $body['id']    ?? '';
        $delta = intval($body['delta'] ?? 1); // +1 like, -1 unlike
        if (!$id) { echo json_encode(['success' => false, 'error' => 'ID manquant']); exit; }
        $stmt = $db->prepare("UPDATE artist_posts SET likes = MAX(0, likes + :delta) WHERE id = :id");
        $stmt->execute([':delta' => $delta, ':id' => $id]);
        echo json_encode(['success' => true, 'posts' => getAllPosts($db)]);
        exit;
    }

    // POST comment — ajouter un commentaire
    if ($method === 'POST' && $action === 'comment') {
        $id     = $body['id']     ?? '';
        $author = $body['author'] ?? 'Visiteur';
        $text   = trim($body['text'] ?? '');
        if (!$id || !$text) { echo json_encode(['success' => false, 'error' => 'ID ou texte manquant']); exit; }
        $stmt = $db->prepare("SELECT comments FROM artist_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Post introuvable']); exit; }
        $comments   = json_decode($row['comments'] ?? '[]', true) ?? [];
        $comments[] = ['author' => $author, 'text' => $text, 'at' => date('Y-m-d H:i:s')];
        $stmt = $db->prepare("UPDATE artist_posts SET comments = :comments WHERE id = :id");
        $stmt->execute([':comments' => json_encode($comments, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        echo json_encode(['success' => true, 'posts' => getAllPosts($db)]);
        exit;
    }

    // POST delete — supprimer (propriétaire uniquement)
    if ($method === 'POST' && $action === 'delete') {
        $id        = $body['id']        ?? '';
        $artist_id = $body['artist_id'] ?? '';
        if (!$id) { echo json_encode(['success' => false, 'error' => 'ID manquant']); exit; }
        $stmt = $db->prepare("SELECT artist_id FROM artist_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['artist_id'] !== $artist_id) {
            echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
        }
        $stmt = $db->prepare("DELETE FROM artist_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'posts' => getAllPosts($db)]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue : ' . $action]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
