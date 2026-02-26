<?php
/**
 * Système d'authentification ARKYL - VERSION SQLITE (Complet)
 */
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config_mysql.php';

try {
    $db = getDB();
    $action = $_GET['action'] ?? '';
    $data = json_decode(file_get_contents('php://input'), true);

    // --- ACTION : INSCRIPTION ---
    if ($action === 'register') {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $artist_name = $data['artist_name'] ?? $name;

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
            exit;
        }

        // ⭐ FIX : Vérifier si l'email existe déjà
        $check = $db->prepare("SELECT id FROM artists WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO artists (name, artist_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $artist_name, $email, $hashedPassword, 'artist']);

        // ⭐ FIX : Retourner l'ID du nouvel artiste pour permettre l'auto-login
        $newId = (string) $db->lastInsertId();

        echo json_encode([
            'success'     => true,
            'message'     => 'Inscription réussie',
            'user_id'     => $newId,
            'user_name'   => $name,
            'user_email'  => $email,
            'artist_name' => $artist_name,
            'role'        => 'artist'
        ]);
    } 

    // --- ACTION : CONNEXION ---
    elseif ($action === 'login') {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM artists WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Stocker en session (côté serveur)
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];

            // ⭐ FIX : Retourner toutes les données nécessaires au frontend
            // (connexion.html a besoin de user_id, name, email pour le localStorage)
            echo json_encode([
                'success'      => true,
                'message'      => 'Connexion réussie',
                'user_id'      => (string) $user['id'],      // Toujours string pour éviter === raté en JS
                'user_name'    => $user['name']     ?? '',
                'user_email'   => $user['email']    ?? '',
                'artist_name'  => $user['artist_name'] ?? $user['name'] ?? '',
                'avatar'       => $user['avatar']   ?? '',
                'country'      => $user['country']  ?? 'Côte d\'Ivoire',
                'role'         => $user['role']     ?? 'artist'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        }
    }

    // --- ACTION : VÉRIFIER LA SESSION (Pour le Dashboard) ---
    elseif ($action === 'check_session') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'user_id' => $_SESSION['user_id']]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
