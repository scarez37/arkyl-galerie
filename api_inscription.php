<?php
require_once __DIR__ . '/cors_helper.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // ── Vérification Google obligatoire ────────────────────────
    $google_id = trim($data['google_id'] ?? '');
    if (empty($google_id)) {
        http_response_code(401);
        echo json_encode([
            'success'  => false,
            'message'  => 'Vous devez être connecté avec votre compte Google avant de créer un compte artiste.',
            'redirect' => 'index.php'
        ]);
        exit;
    }

    // ── Champs obligatoires ────────────────────────────────────
    $name     = trim($data['name']     ?? '');
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';
    $artist_name = trim($data['artist_name'] ?? $name);
    $country  = trim($data['country']  ?? "Côte d'Ivoire");

    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
        exit;
    }

    require_once __DIR__ . '/db_config.php';
    $db = getDatabase();

    // ── Créer la table si besoin ───────────────────────────────
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
            specialty     VARCHAR(255),
            country       VARCHAR(100) DEFAULT 'Côte d\'Ivoire',
            website       VARCHAR(255),
            role          VARCHAR(50)  DEFAULT 'artist',
            created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Migration : ajouter google_id si absent
    try { $db->exec("ALTER TABLE artists ADD COLUMN IF NOT EXISTS google_id VARCHAR(255)"); } catch(Exception $e) {}

    // ── Vérifier si email déjà utilisé ────────────────────────
    $check = $db->prepare("SELECT id FROM artists WHERE LOWER(email) = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé par un autre artiste.']);
        exit;
    }

    // ── Insérer l'artiste (RETURNING id = syntaxe PostgreSQL) ──
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO artists (name, artist_name, email, password_hash, google_id, country, role)
        VALUES (?, ?, ?, ?, ?, ?, 'artist')
        RETURNING id
    ");
    $stmt->execute([$name, $artist_name, $email, $hashedPassword, $google_id, $country]);
    $newId = (string) $stmt->fetchColumn();

    echo json_encode([
        'success'     => true,
        'message'     => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id'     => $newId,
        'user_name'   => $name,
        'artist_name' => $artist_name,
        'user_email'  => $email,
        'role'        => 'artist'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>