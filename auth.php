<?php
/**
 * Système d'authentification pour la Galerie d'Art
 * Gestion des sessions, login, logout, inscription
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config_mysql.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================

/**
 * Hacher un mot de passe de manière sécurisée
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Vérifier un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Générer un token sécurisé
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Valider un email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Créer la session utilisateur
 */
function createSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['artist_name'] = $user['artist_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Régénérer l'ID de session pour la sécurité
    session_regenerate_id(true);
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Obtenir l'utilisateur connecté
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'artist_name' => $_SESSION['artist_name'] ?? null
    ];
}

/**
 * Logger une tentative de connexion
 */
function logLoginAttempt($email, $success, $ip) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO login_attempts (email, success, ip_address, attempted_at) 
            VALUES (:email, :success, :ip, NOW())
        ");
        
        $stmt->execute([
            'email' => $email,
            'success' => $success ? 1 : 0,
            'ip' => $ip
        ]);
    } catch (Exception $e) {
        // Ne pas bloquer si la table n'existe pas
        error_log("Log login attempt failed: " . $e->getMessage());
    }
}

/**
 * Vérifier les tentatives de connexion (protection brute force)
 */
function checkLoginAttempts($email) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE email = :email 
            AND success = 0 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        
        // Bloquer après 5 tentatives échouées en 15 minutes
        return ($result['attempts'] ?? 0) < 5;
        
    } catch (Exception $e) {
        // Si la table n'existe pas, autoriser la connexion
        return true;
    }
}

// ============================================================
// ROUTES GET
// ============================================================

if ($method === 'GET') {
    
    // Vérifier si l'utilisateur est connecté
    if ($action === 'check_session') {
        if (isLoggedIn()) {
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user' => getCurrentUser()
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'logged_in' => false
            ]);
        }
    }
    
    // Obtenir les informations de l'utilisateur connecté
    elseif ($action === 'user_info') {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non authentifie']);
            exit();
        }
        
        $user = getCurrentUser();
        echo json_encode(['success' => true, 'user' => $user]);
    }
    
    // Déconnexion
    elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Deconnexion reussie']);
    }
    
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
}

// ============================================================
// ROUTES POST
// ============================================================

elseif ($method === 'POST') {
    
    // Connexion
    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $remember = $data['remember'] ?? false;
        
        // Validation
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
            exit();
        }
        
        if (!isValidEmail($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email invalide']);
            exit();
        }
        
        // Vérifier les tentatives de connexion (anti brute-force)
        if (!checkLoginAttempts($email)) {
            http_response_code(429);
            echo json_encode([
                'success' => false, 
                'message' => 'Trop de tentatives. Reessayez dans 15 minutes.'
            ]);
            exit();
        }
        
        try {
            // Récupérer l'utilisateur
            $stmt = $db->prepare("
                SELECT id, name, email, artist_name, password_hash, status 
                FROM artists 
                WHERE email = :email
            ");
            
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Utilisateur inexistant
                logLoginAttempt($email, false, $_SERVER['REMOTE_ADDR']);
                
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
                exit();
            }
            
            // Vérifier le mot de passe
            if (!verifyPassword($password, $user['password_hash'])) {
                // Mot de passe incorrect
                logLoginAttempt($email, false, $_SERVER['REMOTE_ADDR']);
                
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
                exit();
            }
            
            // Vérifier le statut du compte
            if ($user['status'] === 'blocked') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Compte bloque. Contactez l\'administrateur.']);
                exit();
            }
            
            if ($user['status'] === 'pending') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Compte en attente d\'approbation.']);
                exit();
            }
            
            // Connexion réussie
            createSession($user);
            logLoginAttempt($email, true, $_SERVER['REMOTE_ADDR']);
            
            // Mettre à jour la dernière connexion
            $updateStmt = $db->prepare("UPDATE artists SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute(['id' => $user['id']]);
            
            // Gérer "Se souvenir de moi"
            if ($remember) {
                // Prolonger la durée de vie du cookie de session à 30 jours
                setcookie(session_name(), session_id(), time() + (30 * 24 * 60 * 60), '/');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Connexion reussie',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'artist_name' => $user['artist_name']
                ]
            ]);
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }
    
    // Inscription
    elseif ($action === 'register') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $artist_name = trim($data['artist_name'] ?? '');
        $password = $data['password'] ?? '';
        $password_confirm = $data['password_confirm'] ?? '';
        
        // Validation
        if (empty($name) || empty($email) || empty($artist_name) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
            exit();
        }
        
        if (!isValidEmail($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email invalide']);
            exit();
        }
        
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caracteres']);
            exit();
        }
        
        if ($password !== $password_confirm) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
            exit();
        }
        
        try {
            // Vérifier si l'email existe déjà
            $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
            $checkStmt->execute(['email' => $email]);
            
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cet email est deja utilise']);
                exit();
            }
            
            // Hacher le mot de passe
            $passwordHash = hashPassword($password);
            
            // Insérer le nouvel utilisateur
            $stmt = $db->prepare("
                INSERT INTO artists (name, email, artist_name, password_hash, status) 
                VALUES (:name, :email, :artist_name, :password_hash, 'approved')
            ");
            
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'artist_name' => $artist_name,
                'password_hash' => $passwordHash
            ]);
            
            $userId = $db->lastInsertId();
            
            // Créer la session automatiquement
            createSession([
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'artist_name' => $artist_name
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Inscription reussie',
                'user_id' => $userId
            ]);
            
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription']);
        }
    }
    
    // Changer le mot de passe
    elseif ($action === 'change_password') {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non authentifie']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
            exit();
        }
        
        if (strlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caracteres']);
            exit();
        }
        
        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
            exit();
        }
        
        try {
            $userId = $_SESSION['user_id'];
            
            // Vérifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password_hash FROM artists WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!verifyPassword($currentPassword, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
                exit();
            }
            
            // Mettre à jour le mot de passe
            $newPasswordHash = hashPassword($newPassword);
            
            $updateStmt = $db->prepare("
                UPDATE artists 
                SET password_hash = :password_hash 
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                'password_hash' => $newPasswordHash,
                'id' => $userId
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Mot de passe modifie avec succes']);
            
        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
        }
    }
    
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
}

else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode non supportee']);
}

$db = null;
?>
