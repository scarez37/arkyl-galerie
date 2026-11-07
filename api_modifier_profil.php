<?php
require_once __DIR__ . '/auth_helper.php';
// ==================== API MODIFIER PROFIL ARTISTE ====================
ob_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); ob_end_clean(); exit; }

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
                       website, social, avatar, avatar_style, created_at
                FROM artists
                WHERE id::text = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => (string)$artistId]);
        } else {
            $stmt = $db->prepare("
                SELECT id, name, email, phone, country, specialty, bio,
                       website, social, avatar, avatar_style, created_at
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

        // Statistiques de ventes
        $salesStmt = $db->prepare("
            SELECT COUNT(*) as total_ventes, COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenus
            FROM order_items oi
            JOIN artworks a ON a.id::text = oi.artwork_id::text
            WHERE a.artist_id::text = :id
        ");
        $salesStmt->execute([':id' => (string)$user['id']]);
        $salesRow = $salesStmt->fetch(PDO::FETCH_ASSOC);

        // E-mail et téléphone = données privées : visibles uniquement par l'artiste lui-même ou l'administrateur
        $authG = arkylAuth();
        $mine  = arkylIsAdmin($authG) || arkylOwnsId($authG, $user['id']);

        // ⭐ FIX Bug 2 : retourner specialty désérialisée (tableau, pas string JSON)
        $specialtyArray = parseSpecialty($user['specialty']);

        echo json_encode([
            'success' => true,
            'artist'  => [
                'id'            => (string)$user['id'],   // toujours string
                'name'          => $user['name'],
                'email'         => $mine ? ($user['email'] ?? '') : '',
                'avatar'        => $user['avatar'],
                'avatar_style'  => $user['avatar_style'] ?? 'slices',
                'bio'           => $user['bio'],
                'specialty'     => $specialtyArray,       // ⭐ tableau, pas JSON string
                'country'       => $user['country'],
                'phone'         => $mine ? ($user['phone'] ?? '') : '',
                'website'       => $user['website'],
                'social'        => $user['social'],
                'artworks_count'=> (int)($countRow['total'] ?? 0),
                'total_ventes'  => (int)($salesRow['total_ventes'] ?? 0),
                'total_revenus' => (float)($salesRow['total_revenus'] ?? 0),
                'created_at'    => $user['created_at'] ?? null,
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => arkylSafeError($e)]);
    }
    exit;
}

// ── POST : mise à jour du profil (propriétaire ou administrateur uniquement) ───
try {
    $auth = arkylRequireAuth();
    $db   = getDatabase();
    ensureColumns($db);

    $data = arkylJsonBody();

    $artist_id    = trim((string) ($data['artist_id'] ?? ''));
    $name         = trim((string) ($data['name']      ?? ''));
    $email        = trim((string) ($data['email']     ?? ''));
    $phone        = trim((string) ($data['phone']     ?? ''));
    $country      = trim((string) ($data['country']   ?? ''));
    $specialty    = $data['specialty']                ?? [];
    $bio          = trim((string) ($data['bio']       ?? ''));
    $website      = trim((string) ($data['website']   ?? ''));
    $social       = trim((string) ($data['social']    ?? ''));
    $avatar       = $data['avatar']                   ?? null;
    $avatar_style = $data['avatar_style']             ?? null;

    if (!$artist_id || !$name || !$email) {
        throw new Exception("Champs obligatoires manquants (artist_id, name, email).");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Adresse e-mail invalide.");
    }

    // ── Retrouver LA ligne artiste du compte connecté ─────────────────
    $isAdm = arkylIsAdmin($auth);
    $q = $db->prepare('SELECT id FROM artists WHERE id::text = :id LIMIT 1');
    $q->execute([':id' => $artist_id]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (!$isAdm && !arkylOwnsId($auth, $existing['id'])) {
            throw new Exception("Accès refusé : ce profil n'est pas le vôtre.");
        }
    } elseif (!$isAdm) {
        // L'identifiant fourni (ex. identifiant Google) n'est pas une ligne artists :
        // on cherche le compte artiste lié au compte connecté, sinon on le crée
        // avec l'e-mail VÉRIFIÉ du compte (jamais celui saisi dans le navigateur).
        if (!arkylOwnsId($auth, $artist_id)) {
            throw new Exception("Accès refusé : ce profil n'est pas le vôtre.");
        }
        $gid = (($auth['prov'] ?? '') === 'google') ? (string) $auth['sub'] : null;
        $q = $db->prepare('SELECT id FROM artists WHERE (google_id IS NOT NULL AND google_id = :g) OR LOWER(email) = :e LIMIT 1');
        $q->execute([':g' => (string) $gid, ':e' => mb_strtolower((string) ($auth['email'] ?? ''))]);
        $existing = $q->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $ins = $db->prepare('INSERT INTO artists (name, email, google_id) VALUES (:n, :e, :g) RETURNING id');
            $ins->execute([':n' => $name, ':e' => mb_strtolower((string) $auth['email']), ':g' => $gid]);
            $existing = ['id' => $ins->fetchColumn()];
        }
    } else {
        throw new Exception('Artiste introuvable.');
    }
    $artist_id = (string) $existing['id'];

    // ⭐ specialty stockée en JSON (tableau)
    $specialtyJson = json_encode(is_array($specialty)
        ? $specialty
        : array_filter(array_map('trim', explode(',', (string) $specialty)))
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
        ':id'        => $artist_id,
    ];
    if ($avatar !== null) {
        $setClause .= ", avatar = :avatar";
        $params[':avatar'] = $avatar;
    }
    if ($avatar_style) {
        $setClause .= ", avatar_style = :avatar_style";
        $params[':avatar_style'] = $avatar_style;
    }

    try {
        $db->prepare("UPDATE artists SET $setClause WHERE id::text = :id")->execute($params);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            throw new Exception('Cette adresse e-mail est déjà utilisée par un autre compte.');
        }
        throw $e;
    }

    // Synchroniser le nom dans les œuvres
    $db->prepare("UPDATE artworks SET artist_name = :name WHERE artist_id::text = :id")
       ->execute([':name' => $name, ':id' => $artist_id]);

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);

} catch (Throwable $e) {
    http_response_code($e instanceof PDOException ? 500 : 400);
    echo json_encode(['success' => false, 'message' => arkylSafeError($e)]);
}
