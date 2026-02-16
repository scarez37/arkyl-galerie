<?php
/**
 * API CONNEXION ARTISTE
 * Permet aux artistes de se reconnecter
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Lire les données JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs.");
    }

    // Chercheur automatique de base de données
    $possiblePaths = [
        __DIR__ . '/artgallery.db',
        '/opt/render/project/src/artgallery.db',
        '/var/www/html/artgallery.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/galerie.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        throw new Exception("Base de données introuvable sur le serveur.");
    }

    // Connexion avec PDO (pour password_verify)
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Chercher l'artiste avec cet email
    $stmt = $db->prepare("SELECT id, name, artist_name, email, password, country FROM artists WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si l'artiste existe
    if (!$artist) {
        throw new Exception("Aucun compte trouvé avec cet email.");
    }

    // Vérifier le mot de passe
    if (!password_verify($data['password'], $artist['password'])) {
        throw new Exception("Mot de passe incorrect.");
    }

    // Log de succès
    error_log("✅ Connexion réussie : ID=" . $artist['id'] . ", Email=" . $artist['email']);

    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie ! Bienvenue 🎨',
        'user_id' => intval($artist['id']),
        'user_name' => $artist['artist_name'] ?? $artist['name'],
        'user_email' => $artist['email']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ Erreur connexion : " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
