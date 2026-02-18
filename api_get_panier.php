<?php
/**
 * API GET PANIER
 * Récupère le contenu du panier d'un utilisateur avec les détails des œuvres
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 🔧 UTILISER LA CONFIGURATION CENTRALISÉE
require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    
    $user_id = $_GET['user_id'] ?? '';
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'User ID manquant'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 🛠️ CORRECTION POSTGRESQL : SERIAL, VARCHAR, TIMESTAMP
    $db->exec("CREATE TABLE IF NOT EXISTS cart (
        id SERIAL PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        artwork_id INTEGER NOT NULL,
        quantity INTEGER DEFAULT 1,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
    )");
    
    // Récupérer le panier avec les détails des œuvres
    $stmt = $db->prepare("
        SELECT 
            c.id as cart_id,
            c.quantity,
            c.added_at,
            a.*
        FROM cart c
        INNER JOIN artworks a ON c.artwork_id = a.id
        WHERE c.user_id = :user_id
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    $totalPrice = 0;
    
    foreach ($cartItems as $item) {
        $dimensions = 'Non spécifiées';
        if (!empty($item['dimensions'])) {
            $dimensions = $item['dimensions'];
        }
        
        $photos = [];
        if (!empty($item['photos'])) {
            $decoded = json_decode($item['photos'], true);
            $photos = is_array($decoded) ? $decoded : [$item['photos']];
        } elseif (!empty($item['image_url'])) {
            $photos = [$item['image_url']];
        } elseif (!empty($item['image'])) {
            $photos = [$item['image']];
        }
        
        $price = !empty($item['price']) ? floatval($item['price']) : 0;
        $quantity = intval($item['quantity']);
        $totalPrice += $price * $quantity;
        
        $formatted[] = [
            'id' => intval($item['id']),
            'title' => $item['title'] ?? 'Sans titre',
            'price' => $price,
            'category' => $item['category'] ?? null,
            'technique' => $item['technique'] ?? null,
            'techniqueCustom' => $item['techniqueCustom'] ?? null,
            'dimensions' => $dimensions,
            'description' => $item['description'] ?? null,
            'artist' => $item['artist'] ?? null,
            'artist_name' => $item['artist_name'] ?? $item['artist'] ?? null,
            'artist_country' => $item['artist_country'] ?? null,
            'badge' => $item['badge'] ?? 'Disponible',
            'image_url' => !empty($photos) ? $photos[0] : null,
            'photos' => $photos,
            'cart_id' => $item['cart_id'],
            'quantity' => $quantity,
            'added_at' => $item['added_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted),
        'total_items' => array_sum(array_column($formatted, 'quantity')),
        'total_price' => $totalPrice
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération du panier',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
