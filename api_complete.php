<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$db = new SQLite3('artgallery.db');
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $action === 'get_artist_stats') {
    $artist_id = $_GET['artist_id'] ?? 0;
    // Récupérer les ventes
    $sales = $db->querySingle("SELECT COUNT(id) as nb, SUM(artist_revenue) as rev FROM sales WHERE artist_id = $artist_id AND status IN ('completed','shipped')", true);
    // Récupérer les vues
    $views = $db->querySingle("SELECT COUNT(v.id) as vues FROM artwork_views v JOIN artworks a ON v.artwork_id = a.id WHERE a.user_id = $artist_id");
    
    echo json_encode(['success' => true, 'stats' => [
        'total_sales_count' => $sales['nb'] ?? 0,
        'total_revenue' => $sales['rev'] ?? 0,
        'total_views' => $views ?? 0
    ]]);
}

if ($method === 'POST' && $action === 'add_artwork') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO artworks (title, description, price, image_url, user_id) VALUES (:title, :desc, :price, :img, :uid)");
    $stmt->bindValue(':title', $data['title']);
    $stmt->bindValue(':desc', $data['description']);
    $stmt->bindValue(':price', $data['price']);
    $stmt->bindValue(':img', $data['image']);
    $stmt->bindValue(':uid', $data['artist_id']);
    $stmt->execute();
    echo json_encode(['success' => true]);
}
?>
