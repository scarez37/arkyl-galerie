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

// GET - Récupérer les œuvres
if ($method === 'GET') {
    if ($action === 'artworks') {
        $category = $_GET['category'] ?? 'all';
        
        try {
            if ($category === 'all') {
                $query = "SELECT a.*, ar.artist_name FROM artworks a 
                         JOIN artists ar ON a.artist_id = ar.id 
                         WHERE a.status = 'active' 
                         ORDER BY a.created_at DESC";
                $result = $db->query($query);
            } else {
                $stmt = $db->prepare("SELECT a.*, ar.artist_name FROM artworks a 
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
}

// POST - Ajouter une œuvre
if ($method === 'POST') {
    if ($action === 'add_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation des données
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
    
    if ($action === 'submit_artist') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
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
}

// PUT - Modifier une œuvre
if ($method === 'PUT') {
    if ($action === 'update_artwork') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
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
}

// DELETE - Supprimer une œuvre
if ($method === 'DELETE') {
    if ($action === 'delete_artwork') {
        $id = $_GET['id'] ?? 0;
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit();
        }
        
        try {
            // Soft delete - on change juste le status
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
