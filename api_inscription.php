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

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs obligatoires.");
    }

    require_once __DIR__ . '/db_config.php';
    $db = getDatabase();

    // Vérifier si l'email existe déjà
    $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
    $checkStmt->execute([':email' => trim($data['email'])]);
    if ($checkStmt->fetch()) {
        throw new Exception("Cet email est déjà utilisé par un autre artiste.");
    }

    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $name        = trim($data['name']);
    $artist_name = !empty($data['artist_name']) ? trim($data['artist_name']) : $name;
    $email       = trim($data['email']);
    $country     = !empty($data['country']) ? trim($data['country']) : "Côte d'Ivoire";

    // ✅ RETURNING id — obligatoire en PostgreSQL (lastInsertId() ne fonctionne pas)
    $sql = "INSERT INTO artists (name, artist_name, email, password, country, created_at) 
            VALUES (:name, :artist_name, :email, :password, :country, CURRENT_TIMESTAMP)
            RETURNING id";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':name'        => $name,
        ':artist_name' => $artist_name,
        ':email'       => $email,
        ':password'    => $hashedPassword,
        ':country'     => $country
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $newArtistId = (int) $row['id'];

    echo json_encode([
        'success'    => true,
        'message'    => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id'    => $newArtistId,
        'user_name'  => $artist_name,
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
