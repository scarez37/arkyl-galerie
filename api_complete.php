<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier que la base de données existe
if (!file_exists('artgallery.db')) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Base de donnees non initialisee. Executez init_db.php'
    ]);
    exit();
}

try {
    $db = new SQLite3('artgallery.db');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de connexion a la base de donnees: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Fonction helper pour obtenir l'IP du client
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

// ========================================
// ENDPOINTS GET
// ========================================

if ($method === 'GET') {
    
    // Récupérer les œuvres
    if ($action === 'artworks') {
        $category = $_GET['category'] ?? 'all';
        
        try {
            if ($category === 'all') {
                $query = "SELECT a.*, ar.artist_name,
                         (SELECT COUNT(*) FROM artwork_views WHERE artwork_id = a.id) as views,
                         (SELECT COUNT(*) FROM artwork_likes WHERE artwork_id = a.id) as likes,
                         (SELECT COUNT(*) FROM sales WHERE artwork_id = a.id AND status = 'completed') as sales
                         FROM artworks a 
                         JOIN artists ar ON a.artist_id = ar.id 
                         WHERE a.status = 'active' 
                         ORDER BY a.created_at DESC";
                $result = $db->query($query);
            } else {
                $stmt = $db->prepare("SELECT a.*, ar.artist_name,
                                     (SELECT COUNT(*) FROM artwork_views WHERE artwork_id = a.id) as views,
                                     (SELECT COUNT(*) FROM artwork_likes WHERE artwork_id = a.id) as likes,
                                     (SELECT COUNT(*) FROM sales WHERE artwork_id = a.id AND status = 'completed') as sales
                                     FROM artworks a 
                                     JOIN artists ar ON a.artist_id = ar.id 
                                     WHERE a.category = :category AND a.status = 'active' 
                                     ORDER BY a.created_at DESC");
                $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                $result = $stmt->execute();
            }
            
            $artworks = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $artworks[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $artworks], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Récupérer les œuvres d'un artiste
    if ($action === 'artist_artworks') {
        $artist_id = $_GET['artist_id'] ?? 0;
        
        try {
            $stmt = $db->prepare("SELECT a.*,
                                 (SELECT COUNT(*) FROM artwork_views WHERE artwork_id = a.id) as views,
                                 (SELECT COUNT(*) FROM artwork_likes WHERE artwork_id = a.id) as likes,
                                 (SELECT COUNT(*) FROM sales WHERE artwork_id = a.id AND status = 'completed') as sales
                                 FROM artworks a
                                 WHERE a.artist_id = :artist_id 
                                 ORDER BY a.created_at DESC");
            $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            $artworks = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $artworks[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $artworks], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // ===== NOUVEAUX ENDPOINTS STATISTIQUES =====
    
    // Statistiques globales du dashboard
    if ($action === 'dashboard_stats') {
        try {
            // Stats totales
            $stats = $db->querySingle("SELECT * FROM stats_overview", true);
            
            // Stats du mois en cours
            $currentMonth = date('Y-m');
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            // Vues du mois
            $viewsThisMonth = $db->querySingle(
                "SELECT COUNT(*) FROM artwork_views 
                 WHERE strftime('%Y-%m', viewed_at) = '$currentMonth'"
            );
            $viewsLastMonth = $db->querySingle(
                "SELECT COUNT(*) FROM artwork_views 
                 WHERE strftime('%Y-%m', viewed_at) = '$lastMonth'"
            );
            
            // Ventes du mois
            $salesThisMonth = $db->querySingle(
                "SELECT COUNT(*) FROM sales 
                 WHERE status = 'completed' AND strftime('%Y-%m', sold_at) = '$currentMonth'"
            );
            $salesLastMonth = $db->querySingle(
                "SELECT COUNT(*) FROM sales 
                 WHERE status = 'completed' AND strftime('%Y-%m', sold_at) = '$lastMonth'"
            );
            
            // Revenus du mois
            $revenueThisMonth = $db->querySingle(
                "SELECT COALESCE(SUM(sale_price), 0) FROM sales 
                 WHERE status = 'completed' AND strftime('%Y-%m', sold_at) = '$currentMonth'"
            );
            $revenueLastMonth = $db->querySingle(
                "SELECT COALESCE(SUM(sale_price), 0) FROM sales 
                 WHERE status = 'completed' AND strftime('%Y-%m', sold_at) = '$lastMonth'"
            );
            
            // Calculer les pourcentages de variation
            $viewsChange = $viewsLastMonth > 0 ? 
                round((($viewsThisMonth - $viewsLastMonth) / $viewsLastMonth) * 100, 1) : 0;
            $salesChange = $salesLastMonth > 0 ? 
                round((($salesThisMonth - $salesLastMonth) / $salesLastMonth) * 100, 1) : 0;
            $revenueChange = $revenueLastMonth > 0 ? 
                round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;
            
            // Taux de conversion
            $conversionRate = $stats['total_views'] > 0 ? 
                round(($stats['total_sales'] / $stats['total_views']) * 100, 2) : 0;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_views' => $stats['total_views'],
                    'total_likes' => $stats['total_likes'],
                    'total_sales' => $stats['total_sales'],
                    'total_revenue' => $stats['total_revenue'],
                    'total_comments' => $stats['total_comments'],
                    'total_shares' => $stats['total_shares'],
                    'conversion_rate' => $conversionRate,
                    'views_change' => $viewsChange,
                    'sales_change' => $salesChange,
                    'revenue_change' => $revenueChange,
                    'views_this_month' => $viewsThisMonth,
                    'sales_this_month' => $salesThisMonth,
                    'revenue_this_month' => $revenueThisMonth
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Statistiques des 7 derniers jours (pour le graphique)
    if ($action === 'weekly_stats') {
        try {
            $stats = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayName = date('D', strtotime("-$i days"));
                
                $sales = $db->querySingle(
                    "SELECT COUNT(*) FROM sales 
                     WHERE status = 'completed' AND DATE(sold_at) = '$date'"
                );
                
                $views = $db->querySingle(
                    "SELECT COUNT(*) FROM artwork_views 
                     WHERE DATE(viewed_at) = '$date'"
                );
                
                $stats[] = [
                    'day' => $dayName,
                    'sales' => $sales,
                    'views' => $views,
                    'date' => $date
                ];
            }
            
            echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Top produits (œuvres les plus populaires)
    if ($action === 'top_products') {
        try {
            $limit = $_GET['limit'] ?? 5;
            
            $query = "SELECT a.id, a.title, a.image_url, a.price,
                     (SELECT COUNT(*) FROM artwork_views WHERE artwork_id = a.id) as views,
                     (SELECT COUNT(*) FROM artwork_likes WHERE artwork_id = a.id) as likes,
                     (SELECT COUNT(*) FROM sales WHERE artwork_id = a.id AND status = 'completed') as sales
                     FROM artworks a
                     WHERE a.status = 'active'
                     ORDER BY views DESC, likes DESC
                     LIMIT $limit";
            
            $result = $db->query($query);
            $products = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $products[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $products], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Statistiques d'un artiste spécifique
    if ($action === 'artist_stats') {
        $artist_id = $_GET['artist_id'] ?? 0;
        
        try {
            $stats = $db->querySingle("
                SELECT 
                    (SELECT COUNT(*) FROM artworks WHERE artist_id = $artist_id) as total_artworks,
                    (SELECT SUM(v.views) FROM (
                        SELECT COUNT(*) as views FROM artwork_views av
                        JOIN artworks a ON av.artwork_id = a.id
                        WHERE a.artist_id = $artist_id
                    ) v) as total_views,
                    (SELECT COUNT(*) FROM sales WHERE artist_id = $artist_id AND status = 'completed') as total_sales,
                    (SELECT COALESCE(SUM(artist_revenue), 0) FROM sales WHERE artist_id = $artist_id AND status = 'completed') as total_revenue
            ", true);
            
            echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ========================================
// ENDPOINTS POST
// ========================================

if ($method === 'POST') {
    
    // Ajouter une œuvre
    if ($action === 'add_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title']) || empty($data['category']) || empty($data['price']) || empty($data['image_url'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO artworks (artist_id, title, category, price, image_url, description) 
                VALUES (:artist_id, :title, :category, :price, :image_url, :description)
            ");
            
            $stmt->bindValue(':artist_id', $data['artist_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':title', $data['title'], SQLITE3_TEXT);
            $stmt->bindValue(':category', $data['category'], SQLITE3_TEXT);
            $stmt->bindValue(':price', $data['price'], SQLITE3_FLOAT);
            $stmt->bindValue(':image_url', $data['image_url'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Oeuvre ajoutee avec succes',
                    'artwork_id' => $db->lastInsertRowID()
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Echec de l\'insertion');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Soumettre une candidature artiste
    if ($action === 'submit_artist') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['email']) || empty($data['artist_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO artists (name, email, artist_name, portfolio_url, message) 
                VALUES (:name, :email, :artist_name, :portfolio_url, :message)
            ");
            
            $stmt->bindValue(':name', $data['name'], SQLITE3_TEXT);
            $stmt->bindValue(':email', $data['email'], SQLITE3_TEXT);
            $stmt->bindValue(':artist_name', $data['artist_name'], SQLITE3_TEXT);
            $stmt->bindValue(':portfolio_url', $data['portfolio_url'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':message', $data['message'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Candidature envoyee avec succes',
                    'artist_id' => $db->lastInsertRowID()
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Echec de l\'insertion');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // ===== NOUVEAUX ENDPOINTS =====
    
    // Enregistrer une vue
    if ($action === 'track_view') {
        $data = json_decode(file_get_contents('php://input'), true);
        $artwork_id = $data['artwork_id'] ?? 0;
        
        if ($artwork_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit();
        }
        
        try {
            $ip = getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO artwork_views (artwork_id, viewer_ip, user_agent) 
                                 VALUES (:artwork_id, :ip, :user_agent)");
            $stmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $stmt->bindValue(':user_agent', $user_agent, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Vue enregistree']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Liker/Unliker une œuvre
    if ($action === 'toggle_like') {
        $data = json_decode(file_get_contents('php://input'), true);
        $artwork_id = $data['artwork_id'] ?? 0;
        
        if ($artwork_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit();
        }
        
        try {
            $ip = getClientIP();
            
            // Vérifier si déjà liké
            $stmt = $db->prepare("SELECT id FROM artwork_likes WHERE artwork_id = :artwork_id AND user_ip = :ip");
            $stmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existing = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($existing) {
                // Supprimer le like
                $stmt = $db->prepare("DELETE FROM artwork_likes WHERE artwork_id = :artwork_id AND user_ip = :ip");
                $stmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'action' => 'unliked']);
            } else {
                // Ajouter le like
                $stmt = $db->prepare("INSERT INTO artwork_likes (artwork_id, user_ip) VALUES (:artwork_id, :ip)");
                $stmt->bindValue(':artwork_id', $artwork_id, SQLITE3_INTEGER);
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'action' => 'liked']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Enregistrer une vente
    if ($action === 'record_sale') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['artwork_id']) || empty($data['sale_price'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            // Récupérer l'artiste de l'œuvre
            $stmt = $db->prepare("SELECT artist_id FROM artworks WHERE id = :id");
            $stmt->bindValue(':id', $data['artwork_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $artwork = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$artwork) {
                throw new Exception('Oeuvre introuvable');
            }
            
            $sale_price = $data['sale_price'];
            $commission_rate = $data['commission_rate'] ?? 15.00;
            $commission = $sale_price * ($commission_rate / 100);
            $artist_revenue = $sale_price - $commission;
            
            $stmt = $db->prepare("
                INSERT INTO sales (artwork_id, artist_id, buyer_name, buyer_email, buyer_phone,
                                  sale_price, commission_rate, commission_amount, artist_revenue, 
                                  status, payment_method, transaction_id) 
                VALUES (:artwork_id, :artist_id, :buyer_name, :buyer_email, :buyer_phone,
                       :sale_price, :commission_rate, :commission_amount, :artist_revenue,
                       :status, :payment_method, :transaction_id)
            ");
            
            $stmt->bindValue(':artwork_id', $data['artwork_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':artist_id', $artwork['artist_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':buyer_name', $data['buyer_name'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':buyer_email', $data['buyer_email'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':buyer_phone', $data['buyer_phone'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':sale_price', $sale_price, SQLITE3_FLOAT);
            $stmt->bindValue(':commission_rate', $commission_rate, SQLITE3_FLOAT);
            $stmt->bindValue(':commission_amount', $commission, SQLITE3_FLOAT);
            $stmt->bindValue(':artist_revenue', $artist_revenue, SQLITE3_FLOAT);
            $stmt->bindValue(':status', $data['status'] ?? 'pending', SQLITE3_TEXT);
            $stmt->bindValue(':payment_method', $data['payment_method'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':transaction_id', $data['transaction_id'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Vente enregistree avec succes',
                    'sale_id' => $db->lastInsertRowID(),
                    'commission' => $commission,
                    'artist_revenue' => $artist_revenue
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Ajouter un commentaire
    if ($action === 'add_comment') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['artwork_id']) || empty($data['comment'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO artwork_comments (artwork_id, user_name, user_email, comment) 
                VALUES (:artwork_id, :user_name, :user_email, :comment)
            ");
            
            $stmt->bindValue(':artwork_id', $data['artwork_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':user_name', $data['user_name'] ?? 'Anonyme', SQLITE3_TEXT);
            $stmt->bindValue(':user_email', $data['user_email'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':comment', $data['comment'], SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Commentaire ajoute avec succes',
                    'comment_id' => $db->lastInsertRowID()
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Enregistrer un partage
    if ($action === 'track_share') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['artwork_id']) || empty($data['platform'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO artwork_shares (artwork_id, platform) VALUES (:artwork_id, :platform)");
            $stmt->bindValue(':artwork_id', $data['artwork_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':platform', $data['platform'], SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Partage enregistre']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ========================================
// ENDPOINTS PUT
// ========================================

if ($method === 'PUT') {
    if ($action === 'update_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id']) || empty($data['title']) || empty($data['category']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE artworks 
                SET title = :title, category = :category, price = :price, 
                    image_url = :image_url, description = :description
                WHERE id = :id
            ");
            
            $stmt->bindValue(':id', $data['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':title', $data['title'], SQLITE3_TEXT);
            $stmt->bindValue(':category', $data['category'], SQLITE3_TEXT);
            $stmt->bindValue(':price', $data['price'], SQLITE3_FLOAT);
            $stmt->bindValue(':image_url', $data['image_url'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Oeuvre modifiee avec succes'], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Echec de la modification');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    // Mettre à jour le statut d'une vente
    if ($action === 'update_sale_status') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['sale_id']) || empty($data['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Donnees manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("UPDATE sales SET status = :status WHERE id = :id");
            $stmt->bindValue(':id', $data['sale_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':status', $data['status'], SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Statut mis a jour'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ========================================
// ENDPOINTS DELETE
// ========================================

if ($method === 'DELETE') {
    if ($action === 'delete_artwork') {
        $id = $_GET['id'] ?? 0;
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("UPDATE artworks SET status = 'deleted' WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Oeuvre supprimee avec succes'], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Echec de la suppression');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

$db->close();
?>
