<?php
/**
 * API PRINCIPALE ARKYL - VERSION FINALE
 * Gestion complète avec support base64 pour images
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier DB
if (!file_exists('artgallery.db')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Base de données non initialisée. Exécutez init_db.php']);
    exit();
}

try {
    $db = new SQLite3('artgallery.db');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur connexion DB: ' . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ==================== GET - Récupérer les œuvres ====================
if ($method === 'GET') {
    if ($action === 'artworks') {
        $category = $_GET['category'] ?? 'all';
        
        try {
            if ($category === 'all') {
                $query = "SELECT a.*, ar.artist_name 
                         FROM artworks a 
                         LEFT JOIN artists ar ON a.artist_id = ar.id 
                         WHERE a.status = 'active' 
                         ORDER BY a.created_at DESC";
                $result = $db->query($query);
            } else {
                $stmt = $db->prepare("SELECT a.*, ar.artist_name 
                                     FROM artworks a 
                                     LEFT JOIN artists ar ON a.artist_id = ar.id 
                                     WHERE a.category = :category AND a.status = 'active' 
                                     ORDER BY a.created_at DESC");
                $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                $result = $stmt->execute();
            }
            
            $artworks = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $artworks[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $artworks, 'count' => count($artworks)], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    if ($action === 'artist_artworks') {
        $artist_id = $_GET['artist_id'] ?? 0;
        
        try {
            $stmt = $db->prepare("SELECT * FROM artworks WHERE artist_id = :artist_id ORDER BY created_at DESC");
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

    // Récupérer les statistiques artiste
    if ($action === 'get_artist_stats') {
        $artist_id = $_GET['artist_id'] ?? 0;
        
        try {
            // Nombre total d'œuvres
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM artworks WHERE artist_id = :artist_id AND status = 'active'");
            $stmt->bindValue(':artist_id', $artist_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $artworks_count = $result->fetchArray(SQLITE3_ASSOC)['count'];

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_sales' => 0,
                    'revenue' => 0,
                    'total_views' => 0,
                    'total_artworks' => $artworks_count
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ==================== POST - Ajouter une œuvre ====================
if ($method === 'POST') {
    if ($action === 'add_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
        if (empty($data['title']) || empty($data['price']) || empty($data['artist_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données manquantes (titre, prix, artiste)']);
            exit();
        }

        // Gestion base64 → fichier
        $image_url = $data['image_url'] ?? '';
        
        // Si c'est du base64, convertir en fichier
        if (preg_match('/^data:image\/(\w+);base64,/', $image_url)) {
            // Créer le dossier uploads
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // Extraire et décoder
            $image_base64 = substr($image_url, strpos($image_url, ',') + 1);
            $image_data = base64_decode($image_base64);
            
            if ($image_data === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Erreur décodage image']);
                exit();
            }
            
            // Sauvegarder
            $image_filename = 'uploads/artwork_' . time() . '_' . uniqid() . '.jpg';
            if (!file_put_contents($image_filename, $image_data)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur sauvegarde image']);
                exit();
            }
            
            // Remplacer l'URL
            $image_url = $image_filename;
        }
        
        try {
            // IMPORTANT : status = 'active' pour publication immédiate
            $stmt = $db->prepare("
                INSERT INTO artworks (artist_id, title, category, price, image_url, description, status, created_at) 
                VALUES (:artist_id, :title, :category, :price, :image_url, :description, 'active', datetime('now'))
            ");
            
            $stmt->bindValue(':artist_id', $data['artist_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':title', $data['title'], SQLITE3_TEXT);
            $stmt->bindValue(':category', $data['category'] ?? 'autre', SQLITE3_TEXT);
            $stmt->bindValue(':price', $data['price'], SQLITE3_FLOAT);
            $stmt->bindValue(':image_url', $image_url, SQLITE3_TEXT);
            $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Œuvre ajoutée avec succès',
                    'artwork_id' => $db->lastInsertRowID()
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Échec de l\'insertion');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
    
    if ($action === 'submit_artist') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['email']) || empty($data['artist_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            exit();
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO artists (name, email, artist_name, portfolio_url, message, status, created_at) 
                VALUES (:name, :email, :artist_name, :portfolio_url, :message, 'pending', datetime('now'))
            ");
            
            $stmt->bindValue(':name', $data['name'], SQLITE3_TEXT);
            $stmt->bindValue(':email', $data['email'], SQLITE3_TEXT);
            $stmt->bindValue(':artist_name', $data['artist_name'], SQLITE3_TEXT);
            $stmt->bindValue(':portfolio_url', $data['portfolio_url'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':message', $data['message'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Candidature envoyée avec succès',
                    'artist_id' => $db->lastInsertRowID()
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Échec de l\'insertion');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ==================== PUT - Modifier une œuvre ====================
if ($method === 'PUT') {
    if ($action === 'update_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id']) || empty($data['title']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
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
            $stmt->bindValue(':category', $data['category'] ?? 'autre', SQLITE3_TEXT);
            $stmt->bindValue(':price', $data['price'], SQLITE3_FLOAT);
            $stmt->bindValue(':image_url', $data['image_url'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':description', $data['description'] ?? '', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Œuvre modifiée avec succès'], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Échec de la modification');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// ==================== DELETE - Supprimer une œuvre ====================
if ($method === 'DELETE') {
    if ($action === 'delete_artwork') {
        $id = $_GET['id'] ?? 0;
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit();
        }
        
        try {
            // Soft delete
            $stmt = $db->prepare("UPDATE artworks SET status = 'deleted' WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Œuvre supprimée avec succès'], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('Échec de la suppression');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

$db->close();
?>
