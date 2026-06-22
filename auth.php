<?php
/**
 * Système d'authentification ARKYL
 * FIX : config_mysql.php remplacé par db_config.php (PostgreSQL Render)
 */
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

// Créer la table artists si elle n'existe pas encore
function ensureArtistsTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS artists (
            id            SERIAL PRIMARY KEY,
            name          VARCHAR(255),
            artist_name   VARCHAR(255),
            email         VARCHAR(255) UNIQUE,
            password_hash VARCHAR(255),
            avatar        TEXT,
            bio           TEXT,
            specialty     VARCHAR(255),
            country       VARCHAR(100) DEFAULT 'Côte d''Ivoire',
            website       VARCHAR(255),
            role          VARCHAR(50)  DEFAULT 'artist',
            created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

try {
    $db = getDatabase();
    ensureArtistsTable($db);

    $action = $_GET['action'] ?? '';
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- ACTION : INSCRIPTION ---
    if ($action === 'register') {
        $name        = trim($data['name']        ?? '');
        $email       = strtolower(trim($data['email']       ?? ''));
        $password    = $data['password']    ?? '';
        $artist_name = trim($data['artist_name'] ?? $name);

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
            exit;
        }

        $check = $db->prepare("SELECT id FROM artists WHERE LOWER(email) = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO artists (name, artist_name, email, password_hash, role) VALUES (?, ?, ?, ?, 'artist') RETURNING id");
        $stmt->execute([$name, $artist_name, $email, $hashedPassword]);
        $newId = (string) $stmt->fetchColumn();

        echo json_encode([
            'success'     => true,
            'message'     => 'Inscription réussie',
            'user_id'     => $newId,
            'user_name'   => $name,
            'user_email'  => $email,
            'artist_name' => $artist_name,
            'role'        => 'artist'
        ]);

    // --- ACTION : CONNEXION ---
    } elseif ($action === 'login') {
        $email    = strtolower(trim($data['email']    ?? ''));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM artists WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];

            echo json_encode([
                'success'     => true,
                'message'     => 'Connexion réussie',
                'user_id'     => (string) $user['id'],
                'user_name'   => $user['name']        ?? '',
                'user_email'  => $user['email']       ?? '',
                'artist_name' => $user['artist_name'] ?? $user['name'] ?? '',
                'avatar'      => $user['avatar']      ?? '',
                'country'     => $user['country']     ?? "Côte d'Ivoire",
                'role'        => $user['role']        ?? 'artist'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        }

    // --- ACTION : CHECK SESSION ---
    } elseif ($action === 'check_session') {
        echo json_encode(isset($_SESSION['user_id'])
            ? ['success' => true,  'user_id' => $_SESSION['user_id']]
            : ['success' => false]
        );

    } else {
        echo json_encode(['success' => false, 'message' => "Action '$action' inconnue"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
