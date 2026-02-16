<?php
/**
 * API INSCRIPTION ARTISTE - VERSION ULTRA-ROBUSTE
 * Ne plante jamais, cherche la base automatiquement, crée les colonnes si nécessaire
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. Lire les données JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception("Veuillez remplir tous les champs obligatoires.");
    }

    // 2. CHERCHEUR AUTOMATIQUE DE BASE DE DONNÉES
    $possiblePaths = [
        __DIR__ . '/artgallery.db',              // Dossier actuel
        '/opt/render/project/src/artgallery.db', // Render
        '/var/www/html/artgallery.db',           // Apache standard
        __DIR__ . '/galerie.db',                 // Ancien nom
        '/opt/render/project/src/galerie.db'     // Ancien nom Render
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        throw new Exception("Base de données introuvable. Chemins testés : " . implode(', ', $possiblePaths));
    }

    // 3. CONNEXION AVEC PDO (plus robuste pour ALTER TABLE)
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. AJOUT AUTOMATIQUE DES COLONNES MANQUANTES (sans planter si elles existent)
    $columnsToAdd = [
        "ALTER TABLE artists ADD COLUMN password TEXT",
        "ALTER TABLE artists ADD COLUMN country TEXT DEFAULT 'Côte d''Ivoire'",
        "ALTER TABLE artists ADD COLUMN artist_name TEXT",
        "ALTER TABLE artists ADD COLUMN profile_image TEXT",
        "ALTER TABLE artists ADD COLUMN bio TEXT",
        "ALTER TABLE artists ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($columnsToAdd as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // La colonne existe déjà, on continue sans erreur
        }
    }

    // 5. VÉRIFIER SI L'EMAIL EXISTE DÉJÀ
    $checkStmt = $db->prepare("SELECT id FROM artists WHERE email = :email");
    $checkStmt->execute([':email' => $data['email']]);
    
    if ($checkStmt->fetch()) {
        throw new Exception("Cet email est déjà utilisé par un autre artiste.");
    }

    // 6. SÉCURISER LE MOT DE PASSE
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // 7. PRÉPARER LES VALEURS
    $name = trim($data['name']);
    $artist_name = !empty($data['artist_name']) ? trim($data['artist_name']) : $name;
    $email = trim($data['email']);
    $country = !empty($data['country']) ? trim($data['country']) : "Côte d'Ivoire";

    // 8. INSÉRER LE NOUVEL ARTISTE
    $sql = "INSERT INTO artists (name, artist_name, email, password, country, created_at) 
            VALUES (:name, :artist_name, :email, :password, :country, datetime('now'))";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':artist_name' => $artist_name,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':country' => $country
    ]);

    // 9. RÉCUPÉRER L'ID CRÉÉ
    $newArtistId = $db->lastInsertId();

    // 10. LOG DE SUCCÈS
    error_log("✅ Nouvel artiste créé : ID=$newArtistId, Email=$email");

    // 11. RÉPONSE DE SUCCÈS
    echo json_encode([
        'success' => true, 
        'message' => 'Inscription réussie ! Bienvenue sur ARKYL 🎨',
        'user_id' => intval($newArtistId),
        'user_name' => $artist_name,
        'user_email' => $email,
        'db_path' => $dbPath // Pour debug
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // TOUJOURS RENVOYER DU JSON MÊME EN CAS D'ERREUR
    error_log("❌ Erreur inscription : " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'error_line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
