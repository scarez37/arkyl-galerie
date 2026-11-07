<?php
// ===================================================================
// auth_helper.php — authentification SERVEUR d'ARKYL
//
// Principe : à la connexion (mot de passe ou Google vérifié côté serveur),
// l'API délivre un jeton signé (HMAC-SHA256, format JWT). Le navigateur le
// renvoie dans l'en-tête « Authorization: Bearer … » ; l'API en déduit QUI
// est l'utilisateur et ce qu'il a le droit de faire. Rien de ce qu'envoie le
// navigateur (user_id, artist_id, ?admin=1, rôle…) n'est cru sans ce jeton.
//
// Variables d'environnement :
//   AUTH_SECRET        secret de signature (≥ 32 caractères aléatoires) — OBLIGATOIRE en production
//   ADMIN_EMAILS       e-mails administrateurs (séparés par des virgules)
//   GOOGLE_CLIENT_ID   identifiant client Google attendu dans le jeton Google
// ===================================================================

require_once __DIR__ . '/bootstrap.php';

const ARKYL_TOKEN_TTL = 2592000; // 30 jours

// ───────────────────────── Jetons ─────────────────────────

function arkylB64uEncode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function arkylB64uDecode(string $s): string {
    $r = strlen($s) % 4;
    if ($r) {
        $s .= str_repeat('=', 4 - $r);
    }
    return (string) base64_decode(strtr($s, '-_', '+/'), true);
}

function arkylAuthSecret(): string {
    $s = getenv('AUTH_SECRET');
    if ($s && strlen($s) >= 16) {
        return $s;
    }
    // Repli : secret dérivé de la configuration base de données (connue du serveur seul).
    $seed = getenv('SUPABASE_DB_URL') ?: getenv('DATABASE_URL') ?: getenv('PGPASSWORD') ?: '';
    if ($seed === '') {
        throw new RuntimeException('AUTH_SECRET manquant');
    }
    static $warned = false;
    if (!$warned) {
        error_log('ARKYL : AUTH_SECRET non défini — secret dérivé de la base de données. Définissez AUTH_SECRET (32+ caractères aléatoires).');
        $warned = true;
    }
    return hash('sha256', 'arkyl-auth-v1|' . $seed);
}

/** Crée un jeton signé à partir de revendications (claims). */
function arkylIssueToken(array $claims, int $ttl = ARKYL_TOKEN_TTL): string {
    $now     = time();
    $payload = array_merge($claims, ['iat' => $now, 'exp' => $now + $ttl]);
    $h = arkylB64uEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = arkylB64uEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $s = arkylB64uEncode(hash_hmac('sha256', "$h.$p", arkylAuthSecret(), true));
    return "$h.$p.$s";
}

/** Vérifie signature + expiration. Retourne les claims ou null. */
function arkylVerifyToken(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $header = json_decode(arkylB64uDecode($h), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
        return null; // refuse alg=none et tout autre algorithme
    }
    $expected = arkylB64uEncode(hash_hmac('sha256', "$h.$p", arkylAuthSecret(), true));
    if (!hash_equals($expected, $s)) {
        return null;
    }
    $payload = json_decode(arkylB64uDecode($p), true);
    if (!is_array($payload) || empty($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }
    return $payload;
}

function arkylBearerToken(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $h = $v;
                break;
            }
        }
    }
    if ($h !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($h), $m)) {
        return trim($m[1]);
    }
    // Certains serveurs (Apache/CGI) suppriment « Authorization » : en-tête de secours.
    $alt = $_SERVER['HTTP_X_ARKYL_TOKEN'] ?? '';
    return $alt !== '' ? trim($alt) : null;
}

// ───────────────────────── Identité courante ─────────────────────────

/** Retourne les claims du jeton valide, ou null (visiteur non connecté). */
function arkylAuth(): ?array {
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $tok = arkylBearerToken();
    if ($tok) {
        try {
            $claims = arkylVerifyToken($tok);
        } catch (Throwable $e) {
            error_log('ARKYL auth : ' . $e->getMessage());
            $claims = null;
        }
        if ($claims) {
            $claims['ids']   = array_values(array_map('strval', $claims['ids'] ?? []));
            $claims['names'] = array_values(array_map('strval', $claims['names'] ?? []));
            $claims['adm']   = !empty($claims['adm']);
            $cache = $claims;
        }
    }
    return $cache;
}

function arkylRequireAuth(): array {
    $a = arkylAuth();
    if (!$a) {
        arkylJsonError(401, 'Connexion requise.', ['auth_required' => true]);
    }
    return $a;
}

function arkylRequireAdmin(): array {
    $a = arkylRequireAuth();
    if (empty($a['adm'])) {
        arkylJsonError(403, 'Accès réservé à l\'administrateur.');
    }
    return $a;
}

function arkylIsAdmin(?array $a = null): bool {
    $a = $a ?? arkylAuth();
    return !empty($a['adm']);
}

/** L'utilisateur connecté possède-t-il cet identifiant (id artiste ou id Google) ? */
function arkylOwnsId(?array $a, $id): bool {
    if (!$a || $id === null || $id === '') {
        return false;
    }
    return in_array((string) $id, $a['ids'], true);
}

/** Le nom d'artiste fourni est-il celui du compte connecté ? */
function arkylOwnsArtistName(?array $a, string $name): bool {
    if (!$a || trim($name) === '') {
        return false;
    }
    $n = mb_strtolower(trim($name));
    foreach ($a['names'] as $mine) {
        if (mb_strtolower(trim($mine)) === $n) {
            return true;
        }
    }
    return false;
}

/** Identifiant du compte artiste de l'utilisateur connecté (null s'il n'a pas de compte artiste). */
function arkylArtistId(?array $a): ?string {
    return ($a && !empty($a['aid'])) ? (string) $a['aid'] : null;
}

/** Identifiant anonyme généré par le navigateur pour les paniers/favoris des visiteurs. */
function arkylIsGuestId($id): bool {
    return is_string($id) && preg_match('/^(user|guest)_[A-Za-z0-9_\-]{8,64}$/', $id) === 1;
}

/**
 * Détermine l'identifiant sous lequel agir pour une requête « panier / favoris ».
 *  - identifiant visiteur (user_xxx / guest_xxx) : accepté tel quel
 *  - sinon : connexion obligatoire ET l'identifiant doit appartenir au jeton
 * Sans identifiant fourni, on utilise celui du jeton.
 */
function arkylActorId($claimed, bool $allowGuest = true): string {
    $claimed = is_scalar($claimed) ? trim((string) $claimed) : '';
    if ($allowGuest && $claimed !== '' && arkylIsGuestId($claimed)) {
        return $claimed;
    }
    $a = arkylRequireAuth();
    if ($claimed === '') {
        return (string) $a['sub'];
    }
    if (!arkylOwnsId($a, $claimed) && !arkylIsAdmin($a)) {
        arkylJsonError(403, 'Cet identifiant ne correspond pas à votre compte.');
    }
    return $claimed;
}

// ───────────────────────── Administrateurs ─────────────────────────

function arkylAdminEmails(): array {
    $raw = getenv('ADMIN_EMAILS');
    if ($raw === false || trim($raw) === '') {
        $raw = 'scarez37@gmail.com,arkyl.app@gmail.com';
    }
    return array_values(array_filter(array_map(function ($e) {
        return mb_strtolower(trim($e));
    }, explode(',', $raw))));
}

function arkylIsAdminEmail(string $email): bool {
    return in_array(mb_strtolower(trim($email)), arkylAdminEmails(), true);
}

// ───────────────────────── Construction des claims ─────────────────────────

/**
 * Claims d'un jeton à partir d'une ligne de la table artists (connexion par mot de passe).
 * L'admin n'est reconnu que par la colonne role='admin' en base (jamais par e-mail déclaratif).
 */
function arkylClaimsFromArtist(array $artist, string $provider = 'password'): array {
    $ids = [(string) $artist['id']];
    if (!empty($artist['google_id'])) {
        $ids[] = (string) $artist['google_id'];
    }
    $names = array_values(array_filter([(string) ($artist['artist_name'] ?? ''), (string) ($artist['name'] ?? '')]));
    return [
        'sub'   => (string) $artist['id'],
        'aid'   => (string) $artist['id'],
        'ids'   => array_values(array_unique($ids)),
        'email' => (string) ($artist['email'] ?? ''),
        'name'  => (string) ($artist['artist_name'] ?? $artist['name'] ?? ''),
        'names' => $names,
        'role'  => ($artist['role'] ?? 'artist') === 'admin' ? 'admin' : 'artist',
        'adm'   => ($artist['role'] ?? '') === 'admin',
        'prov'  => $provider,
    ];
}

// ───────────────────────── Google (vérification serveur) ─────────────────────────

/**
 * Vérifie un jeton d'identité Google (« credential ») auprès de Google.
 * Contrôle : signature (via Google), audience = notre client_id, émetteur, expiration, e-mail vérifié.
 * Lève une Exception « métier » (message sûr) si le jeton est refusé.
 */
function arkylVerifyGoogleCredential(string $credential): array {
    if ($credential === '' || strlen($credential) > 4096) {
        throw new Exception('Jeton Google manquant.');
    }
    $clientId = getenv('GOOGLE_CLIENT_ID') ?: '814095132615-nug0r3e9cgdc5kv4uj2du10e7dkas88b.apps.googleusercontent.com';
    $base     = getenv('GOOGLE_TOKENINFO_URL') ?: 'https://oauth2.googleapis.com/tokeninfo';

    $ch = curl_init($base . '?id_token=' . urlencode($credential));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        error_log('ARKYL Google tokeninfo : ' . curl_error($ch));
    }
    curl_close($ch);

    if ($body === false || $code !== 200) {
        throw new Exception('Connexion Google refusée. Réessayez.');
    }
    $p = json_decode($body, true);
    if (!is_array($p)) {
        throw new Exception('Réponse Google invalide.');
    }
    $okIss = in_array($p['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
    $okAud = ($p['aud'] ?? '') === $clientId;
    $okExp = (int) ($p['exp'] ?? 0) > time();
    $okMail = ($p['email_verified'] ?? false) === true || ($p['email_verified'] ?? '') === 'true';
    if (!$okIss || !$okAud || !$okExp || !$okMail || empty($p['sub']) || empty($p['email'])) {
        throw new Exception('Jeton Google non valide.');
    }
    return $p;
}

// ───────────────────────── Anti force brute ─────────────────────────

function arkylClientIp(): string {
    // On ne se fie qu'à REMOTE_ADDR (X-Forwarded-For est falsifiable).
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function arkylEnsureThrottleTable(PDO $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id SERIAL PRIMARY KEY,
        k  VARCHAR(80) NOT NULL,
        at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    try {
        $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_k ON login_attempts (k, at)');
        $db->exec('ALTER TABLE login_attempts ENABLE ROW LEVEL SECURITY');
    } catch (Throwable $e) {
        // sans gravité : le schéma Supabase le fait déjà
    }
    $done = true;
}

function arkylThrottleKeys(string $email): array {
    return ['e:' . sha1(mb_strtolower(trim($email))), 'i:' . sha1(arkylClientIp())];
}

/** Bloque (HTTP 429) si trop d'échecs récents pour cet e-mail ou cette adresse IP. */
function arkylThrottleCheck(PDO $db, string $email, int $maxPerEmail = 10, int $maxPerIp = 40, int $windowSec = 900): void {
    arkylEnsureThrottleTable($db);
    [$ke, $ki] = arkylThrottleKeys($email);
    $st = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE k = :k AND at > CURRENT_TIMESTAMP - (:w || ' seconds')::interval");
    foreach ([[$ke, $maxPerEmail], [$ki, $maxPerIp]] as [$k, $max]) {
        $st->execute([':k' => $k, ':w' => (string) $windowSec]);
        if ((int) $st->fetchColumn() >= $max) {
            arkylJsonError(429, 'Trop de tentatives. Patientez quelques minutes avant de réessayer.');
        }
    }
}

function arkylThrottleFail(PDO $db, string $email): void {
    arkylEnsureThrottleTable($db);
    $ins = $db->prepare('INSERT INTO login_attempts (k) VALUES (:k)');
    foreach (arkylThrottleKeys($email) as $k) {
        $ins->execute([':k' => $k]);
    }
    // Nettoyage occasionnel
    if (random_int(1, 50) === 1) {
        $db->exec("DELETE FROM login_attempts WHERE at < CURRENT_TIMESTAMP - interval '1 day'");
    }
}

function arkylThrottleClear(PDO $db, string $email): void {
    arkylEnsureThrottleTable($db);
    $db->prepare('DELETE FROM login_attempts WHERE k = :k')->execute([':k' => arkylThrottleKeys($email)[0]]);
}

/** Lit le corps JSON de la requête. */
function arkylJsonBody(): array {
    $d = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($d) ? $d : [];
}

// ───────────────────────── Connexion : fonctions partagées ─────────────────────────

/** Ajoute une colonne seulement si elle manque (évite un ALTER TABLE verrouillant à chaque requête). */
function arkylEnsureColumn(PDO $db, string $table, string $column, string $ddl): void {
    $st = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :t AND column_name = :c");
    $st->execute([':t' => $table, ':c' => $column]);
    if (!$st->fetchColumn()) {
        $db->exec("ALTER TABLE $table ADD COLUMN IF NOT EXISTS $column $ddl");
    }
}

/**
 * Vérifie e-mail + mot de passe. Retourne la ligne artists ou null.
 * Compatible avec les deux noms de colonne historiques (password_hash / password).
 * Le temps de réponse est identique que le compte existe ou non.
 */
function arkylPasswordLogin(PDO $db, string $email, string $password): ?array {
    $st = $db->prepare('SELECT * FROM artists WHERE LOWER(email) = :e LIMIT 1');
    $st->execute([':e' => mb_strtolower(trim($email))]);
    $a = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $hash = '';
    if ($a) {
        $hash = !empty($a['password_hash']) ? (string) $a['password_hash'] : (string) ($a['password'] ?? '');
    }
    $dummy = '$2y$10$XYbHtv0nqy9oxBNgK7C6aOL6H.29zUUAqXGywEjd8BEcumeoh.GqC';
    $ok = password_verify($password, $hash !== '' ? $hash : $dummy);
    return ($a && $hash !== '' && $ok) ? $a : null;
}

/** Claims d'une session ouverte avec un compte Google vérifié (+ compte artiste éventuel). */
function arkylGoogleClaims(array $google, ?array $artist): array {
    $sub   = (string) $google['sub'];
    $email = mb_strtolower((string) $google['email']);
    $ids   = [$sub];
    $names = [];
    if ($artist) {
        $ids[]   = (string) $artist['id'];
        $names   = array_values(array_filter([(string) ($artist['artist_name'] ?? ''), (string) ($artist['name'] ?? '')]));
    }
    $adm = arkylIsAdminEmail($email) || ($artist && ($artist['role'] ?? '') === 'admin');
    return [
        'sub'   => $sub,
        'aid'   => $artist ? (string) $artist['id'] : null,
        'ids'   => array_values(array_unique($ids)),
        'email' => $email,
        'name'  => (string) ($google['name'] ?? ($artist['artist_name'] ?? $email)),
        'names' => $names,
        'role'  => $adm ? 'admin' : ($artist ? 'artist' : 'user'),
        'adm'   => $adm,
        'prov'  => 'google',
    ];
}

/** Ré-émet un jeton à partir de celui de la requête, en y ajoutant l'identifiant artiste. */
function arkylClaimsWithArtist(array $auth, array $artist): array {
    $claims = $auth;
    unset($claims['iat'], $claims['exp']);
    $claims['aid']   = (string) $artist['id'];
    $claims['ids']   = array_values(array_unique(array_merge($auth['ids'], [(string) $artist['id']])));
    $claims['names'] = array_values(array_unique(array_merge(
        $auth['names'],
        array_filter([(string) ($artist['artist_name'] ?? ''), (string) ($artist['name'] ?? '')])
    )));
    if (empty($claims['adm']) && ($claims['role'] ?? '') !== 'admin') {
        $claims['role'] = 'artist';
    }
    return $claims;
}
