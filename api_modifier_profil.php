<?php
// ==================== API MODIFIER PROFIL ARTISTE ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();
    $data = json_decode(file_get_contents('php://input'), true);

    $artist_id = trim($data['artist_id'] ?? '');
    $name      = trim($data['name']      ?? '');
    $email     = trim($data['email']     ?? '');
    $phone     = trim($data['phone']     ?? '');
    $country   = trim($data['country']   ?? '');
    $specialty = $data['specialty']      ?? [];
    $bio       = trim($data['bio']       ?? '');
    $website   = trim($data['website']   ?? '');
    $social    = trim($data['social']    ?? '');
    $avatar    = $data['avatar']         ?? null;

    if (!$artist_id || !$name || !$email) {
        throw new Exception("Champs obligatoires manquants (artist_id, name, email).");
    }

    // Vérifier que l'artiste existe
    $check = $db->prepare("SELECT id FROM artists WHERE id = :id");
    $check->execute([':id' => $artist_id]);
    if (!$check->fetch()) {
        // Insérer si premier profil
        $insert = $db->prepare("INSERT INTO artists (id, name, email, phone, country, specialty, bio, website, social, avatar, created_at)
                                VALUES (:id, :name, :email, :phone, :country, :specialty, :bio, :website, :social, :avatar, NOW())");
        $insert->execute([
            ':id'       => $artist_id,
            ':name'     => $name,
            ':email'    => $email,
            ':phone'    => $phone,
            ':country'  => $country,
            ':specialty'=> json_encode(is_array($specialty) ? $specialty : [$specialty]),
            ':bio'      => $bio,
            ':website'  => $website,
            ':social'   => $social,
            ':avatar'   => $avatar,
        ]);
        echo json_encode(['success' => true, 'message' => 'Profil créé avec succès.']);
        exit;
    }

    // UPDATE profil existant
    $setClause = "name = :name, email = :email, phone = :phone, country = :country,
                  specialty = :specialty, bio = :bio, website = :website, social = :social, updated_at = NOW()";
    $params = [
        ':name'     => $name,
        ':email'    => $email,
        ':phone'    => $phone,
        ':country'  => $country,
        ':specialty'=> json_encode(is_array($specialty) ? $specialty : [$specialty]),
        ':bio'      => $bio,
        ':website'  => $website,
        ':social'   => $social,
        ':id'       => $artist_id,
    ];

    if ($avatar) {
        $setClause .= ", avatar = :avatar";
        $params[':avatar'] = $avatar;
    }

    $stmt = $db->prepare("UPDATE artists SET $setClause WHERE id = :id");
    $stmt->execute($params);

    // Mettre à jour aussi le nom sur les œuvres de cet artiste
    if ($name) {
        $db->prepare("UPDATE artworks SET artist_name = :name WHERE artist_id = :id")
           ->execute([':name' => $name, ':id' => $artist_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
