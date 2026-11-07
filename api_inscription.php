<?php
// ==================== INSCRIPTION ARTISTE (liée à un compte Google vérifié) ====================
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    arkylJsonError(405, 'Méthode non autorisée.');
}

try {
    // ── Preuve Google : jeton de session délivré par le serveur après vérification Google ──
    $auth = arkylAuth();
    if (!$auth || ($auth['prov'] ?? '') !== 'google') {
        arkylJsonError(401, 'Vous devez être connecté avec votre compte Google avant de créer un compte artiste.', ['redirect' => 'index.php']);
    }
    $google_id = (string) $auth['sub'];
    $email     = mb_strtolower((string) $auth['email']);   // e-mail vérifié par Google

    $data        = arkylJsonBody();
    $name        = trim((string) ($data['name'] ?? ''));
    $artist_name = trim((string) ($data['artist_name'] ?? $name));
    $password    = (string) ($data['password'] ?? '');
    $country     = trim((string) ($data['country'] ?? "Côte d'Ivoire"));

    if ($name === '' || $password === '') {
        arkylJsonError(400, 'Veuillez remplir tous les champs obligatoires.');
    }
    if (strlen($password) < 8) {
        arkylJsonError(400, 'Le mot de passe doit contenir au moins 8 caractères.');
    }
    if (mb_strlen($name) > 255 || mb_strlen($artist_name) > 255) {
        arkylJsonError(400, 'Nom trop long.');
    }

    $db = getDatabase();

    // Table + colonnes (idempotent ; la source de vérité est supabase/schema.sql)
    $db->exec("
        CREATE TABLE IF NOT EXISTS artists (
            id            SERIAL PRIMARY KEY,
            name          VARCHAR(255),
            artist_name   VARCHAR(255),
            email         VARCHAR(255) UNIQUE,
            password_hash VARCHAR(255),
            google_id     VARCHAR(255),
            avatar        TEXT,
            bio           TEXT,
            specialty     TEXT,
            country       VARCHAR(100) DEFAULT 'Côte d''Ivoire',
            website       VARCHAR(255),
            role          VARCHAR(50)  DEFAULT 'artist',
            created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
        )
    ");
    arkylEnsureColumn($db, 'artists', 'password_hash', 'VARCHAR(255)');
    arkylEnsureColumn($db, 'artists', 'google_id', 'VARCHAR(255)');

    $check = $db->prepare('SELECT id FROM artists WHERE LOWER(email) = :e OR google_id = :g LIMIT 1');
    $check->execute([':e' => $email, ':g' => $google_id]);
    if ($check->fetch()) {
        arkylJsonError(400, 'Un compte artiste existe déjà pour ce compte Google. Connectez-vous.');
    }

    $stmt = $db->prepare("
        INSERT INTO artists (name, artist_name, email, password_hash, google_id, country, role)
        VALUES (:name, :artist_name, :email, :hash, :gid, :country, 'artist')
        RETURNING id
    ");
    $stmt->execute([
        ':name'        => $name,
        ':artist_name' => $artist_name,
        ':email'       => $email,
        ':hash'        => password_hash($password, PASSWORD_DEFAULT),
        ':gid'         => $google_id,
        ':country'     => $country,
    ]);
    $newId = (string) $stmt->fetchColumn();

    $claims = arkylClaimsWithArtist($auth, ['id' => $newId, 'name' => $name, 'artist_name' => $artist_name]);

    echo json_encode([
        'success'     => true,
        'message'     => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id'     => $newId,
        'user_name'   => $name,
        'artist_name' => $artist_name,
        'user_email'  => $email,
        'role'        => 'artist',
        'token'       => arkylIssueToken($claims),
        'expires_in'  => ARKYL_TOKEN_TTL,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
