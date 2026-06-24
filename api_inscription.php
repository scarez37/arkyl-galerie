<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // ══════════════════════════════════════════════════════
    // 🔒 VÉRIFICATION DE SESSION GOOGLE — obligatoire
    // ══════════════════════════════════════════════════════
    $google_id = trim($data['google_id'] ?? '');
    if (empty($google_id)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'Connexion requise. Vous devez être connecté avec votre compte Google avant de créer un compte artiste.',
            'redirect' => 'index.php'
        ]);
        exit;
    }

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs obligatoires.");
    }

    // Connexion au nouveau Cerveau PostgreSQL
    require_once __DIR__ . '/db_config.php';
    $db = getDatabase();

    // VÉRIFIER SI L'EMAIL EXISTE DÉJÀ
    $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
    $checkStmt->execute([':email' => $data['email']]);
    
    if ($checkStmt->fetch()) {
        throw new Exception("Cet email est déjà utilisé par un autre artiste.");
    }

    // SÉCURISER LE MOT DE PASSE
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // PRÉPARER LES VALEURS
    $name = trim($data['name']);
    $artist_name = !empty($data['artist_name']) ? trim($data['artist_name']) : $name;
    $email = trim($data['email']);
    $country = !empty($data['country']) ? trim($data['country']) : "Côte d'Ivoire";

    // INSÉRER LE NOUVEL ARTISTE (Syntaxe PostgreSQL)
    $sql = "INSERT INTO artists (name, artist_name, email, password, country, created_at) 
            VALUES (:name, :artist_name, :email, :password, :country, CURRENT_TIMESTAMP)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':artist_name' => $artist_name,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':country' => $country
    ]);

    $newArtistId = $db->lastInsertId();

    echo json_encode([
        'success' => true, 
        'message' => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id' => intval($newArtistId),
        'user_name' => $artist_name,
        'user_email' => $email
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
