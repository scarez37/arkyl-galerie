<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs.");
    }

    // 1. Chercheur de Base de données
    $possiblePaths = [
        __DIR__ . '/artgallery.db',
        '/opt/render/project/src/artgallery.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/galerie.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) throw new Exception("Base de données introuvable.");

    // 2. Connexion PDO
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. 🛠️ LE MAGIQUE : Réparation automatique de la base !
    try { $db->exec("ALTER TABLE artists ADD COLUMN password TEXT"); } catch (Exception $e) { /* Existe déjà */ }
    try { $db->exec("ALTER TABLE artists ADD COLUMN country TEXT"); } catch (Exception $e) { /* Existe déjà */ }
    try { $db->exec("ALTER TABLE artists ADD COLUMN created_at TEXT"); } catch (Exception $e) { /* Existe déjà */ }

    // 4. On cherche l'artiste
    $stmt = $db->prepare("SELECT * FROM artists WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($artist) {
        // 5. ASTUCE : Si c'est un de tes vieux comptes de test sans mot de passe, on enregistre celui que tu viens de taper !
        if (empty($artist['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $upd = $db->prepare("UPDATE artists SET password = :pwd WHERE id = :id");
            $upd->execute([':pwd' => $hashedPassword, ':id' => $artist['id']]);
            $artist['password'] = $hashedPassword;
        }

        // 6. On vérifie le mot de passe
        if (password_verify($data['password'], $artist['password'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Connexion réussie !',
                'user_id' => $artist['id'],
                'user_name' => $artist['artist_name'] ?? $artist['name'],
                'user_email' => $artist['email']
            ]);
        } else {
            throw new Exception("Mot de passe incorrect.");
        }
    } else {
        throw new Exception("Aucun compte trouvé avec cet email.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
}
?>
