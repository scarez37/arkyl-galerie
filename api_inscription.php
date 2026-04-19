<?php
// 1. Configuration des en-têtes de sécurité (CORS)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://arkyl.site');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion de la requête de pré-vérification (Preflight) du navigateur
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 2. Récupération et décodage des données JSON envoyées par le site
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Vérification des champs obligatoires
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs obligatoires.");
    }

    // 3. Connexion à la base de données
    require_once __DIR__ . '/db_config.php';
    $db = getDatabase();

    // Vérifier si l'email existe déjà dans la table artists
    $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
    $checkStmt->execute([':email' => trim($data['email'])]);
    if ($checkStmt->fetch()) {
        throw new Exception("Cet email est déjà utilisé par un autre artiste.");
    }

    // 4. Préparation des données pour l'insertion
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $name           = trim($data['name']);
    $artist_name    = !empty($data['artist_name']) ? trim($data['artist_name']) : $name;
    $email          = trim($data['email']);
    $country        = !empty($data['country']) ? trim($data['country']) : "Côte d'Ivoire";

    // 5. Insertion dans la table "artists" (PostgreSQL utilise RETURNING id)
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

    // Récupération de l'ID généré par Supabase
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $newArtistId = (int) $row['id'];

    // 6. Réponse de succès
    echo json_encode([
        'success'    => true,
        'message'    => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id'    => $newArtistId,
        'user_name'  => $artist_name,
        'user_email' => $email
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // En cas d'erreur, renvoyer un code 400 et le message d'erreur
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
