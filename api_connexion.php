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

    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs.");
    }

    require_once __DIR__ . '/db_config.php';
    $db = getDatabase();

    $stmt = $db->prepare("SELECT * FROM artists WHERE email = :email");
    $stmt->execute([':email' => trim($data['email'])]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artist) {
        throw new Exception("Aucun compte trouvé avec cet email.");
    }

    if (!password_verify($data['password'], $artist['password'])) {
        throw new Exception("Mot de passe incorrect.");
    }

    $artistId = (int) $artist['id'];

    echo json_encode([
        'success'    => true,
        'message'    => 'Connexion réussie !',
        'user_id'    => $artistId,
        'artist_id'  => $artistId,
        'id'         => $artistId,
        'user_name'  => !empty($artist['artist_name']) ? $artist['artist_name'] : $artist['name'],
        'user_email' => $artist['email'],
        'avatar'     => $artist['avatar'] ?? '',
        'country'    => $artist['country'] ?? "Côte d'Ivoire"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
