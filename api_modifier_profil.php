<?php
// ==================== API MODIFIER PROFIL ARTISTE ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

function ensureColumns($db) {
    // ⭐ FIX Bug 1 : Travailler sur la table 'artists' (pas 'users')
    // api_connexion.php insère dans 'artists' → c'est la source de vérité
    $cols = [
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS avatar       TEXT",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS avatar_style VARCHAR(20) DEFAULT 'slices'",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS phone        VARCHAR(50)",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS country      VARCHAR(100)",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS specialty    TEXT",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS bio          TEXT",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS website      VARCHAR(500)",
        "ALTER TABLE artists ADD COLUMN IF NOT EXISTS social       VARCHAR(500)",
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* ignore si colonne existe déjà */ }
    }
}

// ── Helper : désérialiser la specialty stockée en JSON ───────────────────────
function parseSpecialty($raw) {
    if (!$raw) return [];
    // ⭐ FIX Bug 2 : specialty stockée en JSON → toujours retourner un tableau
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    // Fallback : c'est une chaîne simple (ex: "Peinture, Sculpture")
    return array_filter(array_map('trim', explode(',', $raw)));
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

        // ⭐ FIX Bug 1 : SELECT dans 'artists' (plus 'users')
        // ⭐ FIX Bug 3 : Ajouter 'email' dans le SELECT
        if ($artistId) {
            $stmt = $db->prepare("
                SELECT id, name, email, phone, country, specialty, bio,
                       website, social, avatar, avatar_style
                FROM artists
                WHERE id::text = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => (string)$artistId]);
        } else {
            $stmt = $db->prepare("
                SELECT id, name, email, phone, country, specialty, bio,
                       website, social, avatar, avatar_style
                FROM artists
                WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
                   OR LOWER(TRIM(artist_name)) = LOWER(TRIM(:name))
                LIMIT 1
            ");
            $stmt->execute([':name' => $artistName]);
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'artist' => null]);
            exit;
        }

        // Compter les œuvres liées
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM artworks WHERE artist_id::text = :id");
        $countStmt->execute([':id' => (string)$user['id']]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);

        // ⭐ FIX Bug 2 : retourner specialty désérialisée (tableau, pas string JSON)
        $specialtyArray = parseSpecialty($user['specialty']);

        echo json_encode([
            'success' => true,
            'artist'  => [
                'id'            => (string)$user['id'],   // toujours string
                'name'          => $user['name'],
                'email'         => $user['email'] ?? '',  // ⭐ FIX Bug 3
                'avatar'        => $user['avatar'],
                'avatar_style'  => $user['avatar_style'] ?? 'slices',
                'bio'           => $user['bio'],
                'specialty'     => $specialtyArray,       // ⭐ tableau, pas JSON string
                'country'       => $user['country'],
                'phone'         => $user['phone'] ?? '',
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

    // ⭐ FIX Bug 1 : Vérifier/créer dans 'artists' (plus 'users')
    // ⭐ FIX Bug 4 : Comparer artist_id en text pour éviter mismatch int/string
    $check = $db->prepare("SELECT id FROM artists WHERE id::text = :id OR email = :email");
    $check->execute([':id' => (string)$artist_id, ':email' => $email]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        // L'artiste n'existe pas encore — créer l'entrée
        // ⭐ FIX : Utiliser google_id séparé si la colonne id est SERIAL
        try {
            // Essayer d'abord avec id explicite (si la table accepte les IDs externes)
            $insert = $db->prepare("INSERT INTO artists (id, name, email) VALUES (:id, :name, :email) ON CONFLICT (id) DO NOTHING");
            $insert->execute([':id' => $artist_id, ':name' => $name, ':email' => $email]);
        } catch (Exception $insertErr) {
            // Si id est SERIAL, insérer sans id et stocker google_id ailleurs
            $db->prepare("INSERT INTO artists (name, email) VALUES (:name, :email) ON CONFLICT (email) DO NOTHING")
               ->execute([':name' => $name, ':email' => $email]);
        }
        // Re-récupérer l'artiste (peut avoir un id différent si SERIAL)
        $check2 = $db->prepare("SELECT id FROM artists WHERE email = :email LIMIT 1");
        $check2->execute([':email' => $email]);
        $existing = $check2->fetch(PDO::FETCH_ASSOC);
        if ($existing) $artist_id = (string)$existing['id'];
    } else {
        // Utiliser l'id réel de la BDD (pas forcément le Google ID)
        $artist_id = (string)$existing['id'];
    }

    // ⭐ FIX Bug 2 : Stocker specialty en JSON array (pas en string plate)
    $specialtyJson = json_encode(is_array($specialty)
        ? $specialty
        : array_filter(array_map('trim', explode(',', $specialty)))
    );

    $setClause = "name = :name, email = :email, phone = :phone, country = :country,
                  specialty = :specialty, bio = :bio, website = :website, social = :social";
    $params = [
        ':name'      => $name,
        ':email'     => $email,
        ':phone'     => $phone,
        ':country'   => $country,
        ':specialty' => $specialtyJson,
        ':bio'       => $bio,
        ':website'   => $website,
        ':social'    => $social,
        ':id'        => (string)$artist_id,
    ];

    if ($avatar !== null) {
        $setClause .= ", avatar = :avatar";
        $params[':avatar'] = $avatar;
    }
    if ($avatar_style) {
        $setClause .= ", avatar_style = :avatar_style";
        $params[':avatar_style'] = $avatar_style;
    }

    // ⭐ FIX : Comparer id::text ou email pour compatibilité int/string/Google ID
    $stmt = $db->prepare("UPDATE artists SET $setClause WHERE id::text = :id OR (id::text != :id AND email = :email_fb)");
    $params[':email_fb'] = $email;
    $stmt->execute($params);

    // Synchroniser le nom dans les œuvres si changé
    if ($name) {
        $db->prepare("UPDATE artworks SET artist_name = :name WHERE artist_id::text = :id")
           ->execute([':name' => $name, ':id' => (string)$artist_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
