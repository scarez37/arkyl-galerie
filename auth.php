<?php
/**
 * auth.php — Authentification artiste ARKYL
 * Inscription liée au compte Google (google_id obligatoire)
 */
require_once __DIR__ . '/cors_helper.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_config.php';

function ensureArtistsTable($db) {
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
            specialty     VARCHAR(500),
            country       VARCHAR(100) DEFAULT 'Côte d''Ivoire',
            website       VARCHAR(255),
            role          VARCHAR(50)  DEFAULT 'artist',
            created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Migrations pour tables existantes
    foreach (['google_id VARCHAR(255)', 'phone VARCHAR(50)', 'specialty VARCHAR(500)'] as $col) {
        [$colName] = explode(' ', $col);
        try { $db->exec("ALTER TABLE artists ADD COLUMN IF NOT EXISTS $col"); } catch(Exception $e) {}
    }
}

try {
    $db = getDatabase();
    ensureArtistsTable($db);

    $action = $_GET['action'] ?? '';
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── INSCRIPTION ──────────────────────────────────────────────
    if ($action === 'register') {
        $name        = trim($data['name']        ?? '');
        $email       = strtolower(trim($data['email'] ?? ''));
        $artist_name = trim($data['artist_name'] ?? $name);
        $phone       = trim($data['phone']       ?? '');
        $bio         = trim($data['bio']         ?? '');
        $specialty   = is_array($data['specialty']) ? implode(', ', $data['specialty']) : ($data['specialty'] ?? '');
        $country     = trim($data['country']     ?? "Côte d'Ivoire");
        $avatar      = $data['avatar']           ?? null;
        $google_id   = trim($data['google_id']   ?? '');

        // Validation
        if (empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Nom et email obligatoires']);
            exit;
        }

        // Vérifier unicité email
        $check = $db->prepare("SELECT id, google_id FROM artists WHERE LOWER(email) = ?");
        $check->execute([$email]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Si le compte existe mais sans google_id → lier
            if (!empty($google_id) && empty($existing['google_id'])) {
                $db->prepare("UPDATE artists SET google_id = ? WHERE id = ?")
                   ->execute([$google_id, $existing['id']]);
                echo json_encode(['success' => false, 'message' => 'Ce compte existe déjà. Google ID lié — connectez-vous.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Un compte artiste existe déjà avec cet email. Connectez-vous.']);
            }
            exit;
        }

        // Vérifier unicité google_id si fourni
        if (!empty($google_id)) {
            $checkG = $db->prepare("SELECT id FROM artists WHERE google_id = ?");
            $checkG->execute([$google_id]);
            if ($checkG->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Un compte artiste est déjà lié à votre compte Google.']);
                exit;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO artists (google_id, name, artist_name, email, phone, bio, specialty, country, avatar, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'artist') RETURNING id
        ");
        $stmt->execute([
            $google_id ?: null,
            $name, $artist_name, $email, $phone, $bio, $specialty, $country, $avatar
        ]);
        $newId = (string) $stmt->fetchColumn();

        echo json_encode([
            'success'     => true,
            'message'     => 'Compte artiste créé avec succès !',
            'user_id'     => $newId,
            'user_name'   => $name,
            'user_email'  => $email,
            'artist_name' => $artist_name,
            'avatar'      => $avatar,
            'country'     => $country,
            'google_id'   => $google_id,
            'role'        => 'artist'
        ]);

    // ── CONNEXION (email/mot de passe) ───────────────────────────
    } elseif ($action === 'login') {
        $email    = strtolower(trim($data['email']    ?? ''));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM artists WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            echo json_encode([
                'success'     => true,
                'user_id'     => (string) $user['id'],
                'user_name'   => $user['name']        ?? '',
                'user_email'  => $user['email']       ?? '',
                'artist_name' => $user['artist_name'] ?? $user['name'] ?? '',
                'avatar'      => $user['avatar']      ?? '',
                'country'     => $user['country']     ?? "Côte d'Ivoire",
                'google_id'   => $user['google_id']   ?? '',
                'role'        => 'artist'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        }

    // ── CONNEXION VIA GOOGLE (google_id) ─────────────────────────
    } elseif ($action === 'login_google') {
        $google_id = trim($data['google_id'] ?? '');
        $email     = strtolower(trim($data['email'] ?? ''));

        if (empty($google_id) && empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Identifiant manquant']);
            exit;
        }

        // Chercher par google_id d'abord, puis par email
        $stmt = $db->prepare("SELECT * FROM artists WHERE google_id = ? OR LOWER(email) = ? LIMIT 1");
        $stmt->execute([$google_id, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Mettre à jour le google_id si manquant
            if (!empty($google_id) && empty($user['google_id'])) {
                $db->prepare("UPDATE artists SET google_id = ? WHERE id = ?")
                   ->execute([$google_id, $user['id']]);
            }
            echo json_encode([
                'success'     => true,
                'user_id'     => (string) $user['id'],
                'user_name'   => $user['name']        ?? '',
                'user_email'  => $user['email']       ?? '',
                'artist_name' => $user['artist_name'] ?? $user['name'] ?? '',
                'avatar'      => $user['avatar']      ?? '',
                'country'     => $user['country']     ?? "Côte d'Ivoire",
                'google_id'   => $google_id ?: $user['google_id'],
                'role'        => 'artist'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Aucun compte artiste lié à ce compte Google. Créez votre compte artiste.', 'not_found' => true]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => "Action inconnue: $action"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
