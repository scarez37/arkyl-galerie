<?php
/**
 * API INSCRIPTION ARTISTE
 * Permet aux nouveaux artistes de s'inscrire
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

    // Validation des champs obligatoires
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs obligatoires.");
    }

    // Connexion à la base de données (SQLite3 pour cohérence)
    $db = new SQLite3('artgallery.db');
    $db->busyTimeout(5000);

    // Vérifier si l'email existe déjà
    $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
    $checkStmt->bindValue(':email', $data['email'], SQLITE3_TEXT);
    $checkResult = $checkStmt->execute();
    
    if ($checkResult->fetchArray()) {
        throw new Exception("Cet email est déjà utilisé par un autre artiste.");
    }

    // Sécuriser le mot de passe
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // Préparer les valeurs
    $name = $data['name'];
    $artist_name = !empty($data['artist_name']) ? $data['artist_name'] : $data['name'];
    $email = $data['email'];
    $country = !empty($data['country']) ? $data['country'] : "Côte d'Ivoire";

    // Insérer le nouvel artiste
    $sql = "INSERT INTO artists (name, artist_name, email, password, country, created_at) 
            VALUES (:name, :artist_name, :email, :password, :country, datetime('now'))";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':artist_name', $artist_name, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
    $stmt->bindValue(':country', $country, SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de la création du compte : " . $db->lastErrorMsg());
    }

    // Récupérer l'ID qui vient d'être créé
    $newArtistId = $db->lastInsertRowID();

    $db->close();

    // Réponse de succès avec l'ID
    echo json_encode([
        'success' => true, 
        'message' => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id' => $newArtistId,
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
