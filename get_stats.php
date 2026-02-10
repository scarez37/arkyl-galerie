<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
try {
    $db = new SQLite3('artgallery.db');
    $artist_id = $_GET['artist_id'] ?? 0;
    
    $sales = $db->querySingle("SELECT COUNT(id) as nb, SUM(artist_revenue) as rev FROM sales WHERE artist_id = $artist_id AND status IN ('completed','shipped')", true);
    $views = $db->querySingle("SELECT COUNT(v.id) FROM artwork_views v JOIN artworks a ON v.artwork_id = a.id WHERE a.user_id = $artist_id");
    
    echo json_encode(['success' => true, 'stats' => [
        'total_sales' => $sales['nb'] ?? 0,
        'revenue' => number_format($sales['rev'] ?? 0, 2),
        'total_views' => $views ?? 0
    ]]);
} catch (Exception $e) { echo json_encode(['success' => false]); }
?>
