<?php
/**
 * auth.php — Sessions ARKYL (Google vérifié côté serveur + compte artiste)
 *
 * Actions (?action=…) :
 *   google_session  POST {credential}  → vérifie le jeton Google auprès de Google, délivre le jeton ARKYL
 *   login_google    POST (jeton ARKYL) → retrouve le compte artiste lié au compte Google connecté
 *   register        POST (jeton ARKYL) → crée le compte artiste lié au compte Google connecté
 *   login           POST {email,password} → connexion par mot de passe
 *
 * Plus aucune identité n'est crue sur simple déclaration du navigateur.
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    arkylJsonError(405, 'Méthode non autorisée.');
}

function ensureArtistsTable(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS artists (
            id            SERIAL PRIMARY KEY,
            google_id     VARCHAR(255) UNIQUE,
            name          VARCHAR(255),
            artist_name   VARCHAR(255),
            email         VARCHAR(255) UNIQUE,
            phone         VARCHAR(50),
            password_hash VARCHAR(255),
            avatar        TEXT,
            bio           TEXT,
            specialty     TEXT,
            country       VARCHAR(100) DEFAULT 'Côte d''Ivoire',
            website       VARCHAR(255),
            role          VARCHAR(50)  DEFAULT 'artist',
            created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
        )
    ");
    arkylEnsureColumn($db, 'artists', 'google_id', 'VARCHAR(255)');
    arkylEnsureColumn($db, 'artists', 'phone', 'VARCHAR(50)');
    arkylEnsureColumn($db, 'artists', 'specialty', 'TEXT');
    arkylEnsureColumn($db, 'artists', 'password_hash', 'VARCHAR(255)');
}

/** Cherche le compte artiste lié à un compte Google vérifié (par google_id, puis par e-mail). */
function findArtistForGoogle(PDO $db, string $sub, string $email): ?array {
    $st = $db->prepare('SELECT * FROM artists WHERE google_id = :g LIMIT 1');
    $st->execute([':g' => $sub]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a && $email !== '') {
        $st = $db->prepare('SELECT * FROM artists WHERE LOWER(email) = :e LIMIT 1');
        $st->execute([':e' => mb_strtolower($email)]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if ($a && empty($a['google_id'])) {
            // E-mail vérifié par Google → on lie le compte
            $db->prepare('UPDATE artists SET google_id = :g WHERE id = :id')->execute([':g' => $sub, ':id' => $a['id']]);
            $a['google_id'] = $sub;
        }
    }
    return $a ?: null;
}

function artistPayload(array $artist, string $googleId = ''): array {
    return [
        'user_id'     => (string) $artist['id'],
        'user_name'   => $artist['name']        ?? '',
        'user_email'  => $artist['email']       ?? '',
        'artist_name' => $artist['artist_name'] ?? $artist['name'] ?? '',
        'avatar'      => $artist['avatar']      ?? '',
        'country'     => $artist['country']     ?? "Côte d'Ivoire",
        'google_id'   => $googleId ?: ($artist['google_id'] ?? ''),
        'role'        => 'artist',
    ];
}

try {
    $db     = getDatabase();
    ensureArtistsTable($db);
    $action = $_GET['action'] ?? '';
    $data   = arkylJsonBody();

    // ── SESSION GOOGLE : le seul endroit qui transforme « Google a dit oui » en jeton ARKYL ──
    if ($action === 'google_session') {
        try {
            $g = arkylVerifyGoogleCredential((string) ($data['credential'] ?? ''));
        } catch (Exception $e) {
            arkylJsonError(401, $e->getMessage());
        }
        $artist = findArtistForGoogle($db, (string) $g['sub'], (string) $g['email']);
        $claims = arkylGoogleClaims($g, $artist);

        echo json_encode([
            'success'    => true,
            'token'      => arkylIssueToken($claims),
            'expires_in' => ARKYL_TOKEN_TTL,
            'user'       => [
                'id'          => $claims['sub'],
                'email'       => $claims['email'],
                'name'        => $claims['name'],
                'picture'     => $g['picture'] ?? '',
                'isAdmin'     => $claims['adm'],
                'isArtist'    => (bool) $artist,
                'artist_id'   => $artist ? (string) $artist['id'] : null,
                'artist_name' => $artist ? ($artist['artist_name'] ?? $artist['name']) : null,
            ],
        ], JSON_UNESCAPED_UNICODE);

    // ── COMPTE ARTISTE LIÉ AU GOOGLE CONNECTÉ ──
    } elseif ($action === 'login_google') {
        $auth = arkylRequireAuth();
        if (($auth['prov'] ?? '') !== 'google') {
            arkylJsonError(403, 'Connectez-vous avec Google.');
        }
        $artist = findArtistForGoogle($db, (string) $auth['sub'], (string) $auth['email']);
        if (!$artist) {
            echo json_encode(['success' => false, 'message' => 'Aucun compte artiste lié à ce compte Google. Créez votre compte artiste.', 'not_found' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(array_merge(
            ['success' => true],
            artistPayload($artist, (string) $auth['sub']),
            ['token' => arkylIssueToken(arkylClaimsWithArtist($auth, $artist)), 'expires_in' => ARKYL_TOKEN_TTL]
        ), JSON_UNESCAPED_UNICODE);

    // ── INSCRIPTION ARTISTE (liée au Google connecté) ──
    } elseif ($action === 'register') {
        $auth = arkylRequireAuth();
        if (($auth['prov'] ?? '') !== 'google') {
            arkylJsonError(403, 'Connectez-vous avec Google avant de créer un compte artiste.');
        }
        $google_id = (string) $auth['sub'];
        $email     = mb_strtolower((string) $auth['email']);          // e-mail vérifié par Google
        $name      = trim((string) ($data['name'] ?? ''));
        $artist_name = trim((string) ($data['artist_name'] ?? $name));
        $phone     = trim((string) ($data['phone'] ?? ''));
        $bio       = trim((string) ($data['bio'] ?? ''));
        $specialty = is_array($data['specialty'] ?? null) ? implode(', ', $data['specialty']) : (string) ($data['specialty'] ?? '');
        $country   = trim((string) ($data['country'] ?? "Côte d'Ivoire"));
        $avatar    = isset($data['avatar']) ? (string) $data['avatar'] : null;

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Le nom est obligatoire.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existing = findArtistForGoogle($db, $google_id, $email);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Un compte artiste existe déjà pour ce compte Google. Connectez-vous.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO artists (google_id, name, artist_name, email, phone, bio, specialty, country, avatar, role)
            VALUES (:g, :n, :an, :e, :p, :b, :s, :c, :av, 'artist') RETURNING id
        ");
        $stmt->execute([':g' => $google_id, ':n' => $name, ':an' => $artist_name, ':e' => $email, ':p' => $phone,
                        ':b' => $bio, ':s' => $specialty, ':c' => $country, ':av' => $avatar]);
        $newId  = (string) $stmt->fetchColumn();
        $artist = ['id' => $newId, 'name' => $name, 'artist_name' => $artist_name, 'email' => $email,
                   'avatar' => $avatar, 'country' => $country, 'google_id' => $google_id];

        echo json_encode(array_merge(
            ['success' => true, 'message' => 'Compte artiste créé avec succès !'],
            artistPayload($artist, $google_id),
            ['token' => arkylIssueToken(arkylClaimsWithArtist($auth, $artist)), 'expires_in' => ARKYL_TOKEN_TTL]
        ), JSON_UNESCAPED_UNICODE);

    // ── CONNEXION PAR MOT DE PASSE ──
    } elseif ($action === 'login') {
        $email    = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || $password === '') {
            echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        arkylThrottleCheck($db, $email);
        $user = arkylPasswordLogin($db, $email, $password);
        if (!$user) {
            arkylThrottleFail($db, $email);
            echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        arkylThrottleClear($db, $email);
        $claims = arkylClaimsFromArtist($user, 'password');
        echo json_encode(array_merge(
            ['success' => true],
            artistPayload($user),
            ['role' => $claims['role'], 'token' => arkylIssueToken($claims), 'expires_in' => ARKYL_TOKEN_TTL]
        ), JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['success' => false, 'message' => 'Action inconnue'], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
