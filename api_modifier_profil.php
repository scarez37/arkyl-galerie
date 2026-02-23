<?php
// ==================== API MODIFIER PROFIL ARTISTE ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

function ensureColumns($db) {
    // Ajouter les colonnes manquantes sans planter si elles existent déjà
    $cols = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_style VARCHAR(20) DEFAULT 'slices'"
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* ignore */ }
    }
}

// ── GET : lecture publique du profil artiste ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = getDatabase();
        ensureColumns($db);

        $artistName = trim($_GET['artist_name'] ?? '');
        $artistId   = trim($_GET['artist_id']   ?? '');

        if (!$artistName && !$artistId) {
            echo json_encode(['success' => false, 'error' => 'Paramètre artist_name ou artist_id requis']);
            exit;
        }

        if ($artistId) {
            $stmt = $db->prepare("SELECT id, name, phone, country, specialty, bio, website, social, avatar, avatar_style FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $artistId]);
        } else {
            $stmt = $db->prepare("SELECT id, name, phone, country, specialty, bio, website, social, avatar, avatar_style FROM users WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
            $stmt->execute([':name' => $artistName]);
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'artist' => null]);
            exit;
        }

        // Compter les oeuvres
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM artworks WHERE artist_id = :id");
        $countStmt->execute([':id' => $user['id']]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'artist'  => [
                'id'            => $user['id'],
                'name'          => $user['name'],
                'avatar'        => $user['avatar'],
                'avatar_style'  => $user['avatar_style'] ?? 'slices',
                'bio'           => $user['bio'],
                'specialty'     => $user['specialty'],
                'country'       => $user['country'],
                'website'       => $user['website'],
                'social'        => $user['social'],
                'artworks_count'=> (int)($countRow['total'] ?? 0),
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── POST : mise à jour du profil ─────────────────────────────────────────────
try {
    $db = getDatabase();
    ensureColumns($db);

    $data = json_decode(file_get_contents('php://input'), true);

    $artist_id    = trim($data['artist_id']    ?? '');
    $name         = trim($data['name']         ?? '');
    $email        = trim($data['email']        ?? '');
    $phone        = trim($data['phone']        ?? '');
    $country      = trim($data['country']      ?? '');
    $specialty    = $data['specialty']         ?? [];
    $bio          = trim($data['bio']          ?? '');
    $website      = trim($data['website']      ?? '');
    $social       = trim($data['social']       ?? '');
    $avatar       = $data['avatar']            ?? null;
    $avatar_style = $data['avatar_style']      ?? null;

    if (!$artist_id || !$name || !$email) {
        throw new Exception("Champs obligatoires manquants (artist_id, name, email).");
    }

    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute([':id' => $artist_id]);
    if (!$check->fetch()) {
        $insert = $db->prepare("INSERT INTO users (id, name, email) VALUES (:id, :name, :email)");
        $insert->execute([':id' => $artist_id, ':name' => $name, ':email' => $email]);
    }

    $setClause = "name = :name, phone = :phone, country = :country,
                  specialty = :specialty, bio = :bio, website = :website, social = :social";
    $params = [
        ':name'     => $name,
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
    if ($avatar_style) {
        $setClause .= ", avatar_style = :avatar_style";
        $params[':avatar_style'] = $avatar_style;
    }

    $stmt = $db->prepare("UPDATE users SET $setClause WHERE id = :id");
    $stmt->execute($params);

    if ($name) {
        $db->prepare("UPDATE artworks SET artist_name = :name WHERE artist_id = :id")
           ->execute([':name' => $name, ':id' => $artist_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
