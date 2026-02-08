<?php
/**
 * Système d'authentification ARKYL - VERSION SQLITE
 */
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config_mysql.php';

$db = getDB();
$action = $_GET['action'] ?? '';

// Récupérer les données JSON envoyées
$data = json_decode(file_get_contents('php://input'), true);

if ($action === 'register') {
    try {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $artist_name = $data['artist_name'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
            exit;
        }

        // Vérifier si l'email existe déjà
        $check = $db->prepare("SELECT id FROM artists WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            exit;
        }

        // Hachage du mot de passe
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insertion (Version SQLite)
        $stmt = $db->prepare("INSERT INTO artists (name, artist_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $artist_name, $email, $hashedPassword, 'artist']);

        // Créer la session automatiquement
        $_SESSION['user_id'] = $db->lastInsertId();
        $_SESSION['user_email'] = $email;

        echo json_encode(['success' => true, 'message' => 'Inscription réussie']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données : ' . $e->getMessage()]);
    }
} 
// Ajoutez ici la gestion du login si nécessaire, mais testons déjà l'inscription.
?>
