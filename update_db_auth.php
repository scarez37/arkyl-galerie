<?php
/**
 * Script de mise à jour de la base de données
 * Ajoute les champs nécessaires pour l'authentification
 * 
 * INSTRUCTIONS:
 * 1. Uploader ce fichier sur votre serveur
 * 2. Accéder via navigateur: http://votresite.com/update_db_auth.php
 * 3. SUPPRIMER ce fichier après exécution
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config_mysql.php';

echo "<html><head><meta charset='utf-8'><title>Mise à jour DB - Authentification</title>";
echo "<style>
    body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .warning { color: orange; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    h1 { color: #333; }
    h2 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
</style></head><body>";

echo "<h1>🔐 Mise à jour de la Base de Données - Système d'Authentification</h1>";
echo "<p>Ce script ajoute les colonnes nécessaires pour le système d'authentification</p>";
echo "<hr>";

try {
    $db = getDB();
    echo "<div class='success'>✅ Connexion à la base de données réussie</div>";
    
    // ========================================
    // 1. Ajouter la colonne password_hash
    // ========================================
    echo "<h2>1. Ajout de la colonne password_hash</h2>";
    
    try {
        $db->exec("ALTER TABLE artists ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email");
        echo "<div class='success'>✅ Colonne password_hash ajoutée</div>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<div class='info'>ℹ️ Colonne password_hash existe déjà</div>";
        } else {
            throw $e;
        }
    }
    
    // ========================================
    // 2. Ajouter la colonne last_login
    // ========================================
    echo "<h2>2. Ajout de la colonne last_login</h2>";
    
    try {
        $db->exec("ALTER TABLE artists ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER status");
        echo "<div class='success'>✅ Colonne last_login ajoutée</div>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<div class='info'>ℹ️ Colonne last_login existe déjà</div>";
        } else {
            throw $e;
        }
    }
    
    // ========================================
    // 3. Créer la table login_attempts
    // ========================================
    echo "<h2>3. Création de la table login_attempts</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45),
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_attempted_at (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<div class='success'>✅ Table login_attempts créée</div>";
    
    // ========================================
    // 4. Créer des mots de passe pour les utilisateurs existants
    // ========================================
    echo "<h2>4. Création de mots de passe pour les utilisateurs existants</h2>";
    
    // Vérifier combien d'utilisateurs n'ont pas de mot de passe
    $stmt = $db->query("SELECT COUNT(*) FROM artists WHERE password_hash IS NULL");
    $countWithoutPassword = $stmt->fetchColumn();
    
    if ($countWithoutPassword > 0) {
        echo "<div class='info'>📊 $countWithoutPassword utilisateur(s) sans mot de passe</div>";
        
        // Créer un mot de passe par défaut pour chaque utilisateur
        $defaultPassword = 'password123'; // À CHANGER après la première connexion
        $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $updateStmt = $db->prepare("
            UPDATE artists 
            SET password_hash = :password_hash 
            WHERE password_hash IS NULL
        ");
        
        $updateStmt->execute(['password_hash' => $passwordHash]);
        
        echo "<div class='warning'>";
        echo "<h3>⚠️ IMPORTANT - Mots de passe par défaut créés</h3>";
        echo "<p><strong>Mot de passe par défaut:</strong> <code>password123</code></p>";
        echo "<p><strong>Action requise:</strong> Connectez-vous et changez immédiatement votre mot de passe !</p>";
        echo "</div>";
        
        // Afficher les comptes créés
        $usersStmt = $db->query("SELECT id, email, name, artist_name FROM artists");
        $users = $usersStmt->fetchAll();
        
        echo "<div class='info'>";
        echo "<h4>📋 Comptes avec mot de passe par défaut:</h4>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li><strong>" . htmlspecialchars($user['artist_name']) . "</strong> (" . htmlspecialchars($user['email']) . ")</li>";
        }
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<div class='success'>✅ Tous les utilisateurs ont déjà un mot de passe</div>";
    }
    
    // ========================================
    // 5. Statistiques
    // ========================================
    echo "<hr>";
    echo "<h2>📊 Statistiques de la Base de Données</h2>";
    
    $totalArtists = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
    $approvedArtists = $db->query("SELECT COUNT(*) FROM artists WHERE status='approved'")->fetchColumn();
    $totalArtworks = $db->query("SELECT COUNT(*) FROM artworks WHERE status='active'")->fetchColumn();
    
    echo "<div class='info'>";
    echo "<ul>";
    echo "<li>👨‍🎨 <strong>Total artistes:</strong> $totalArtists</li>";
    echo "<li>✅ <strong>Artistes approuvés:</strong> $approvedArtists</li>";
    echo "<li>🎨 <strong>Œuvres actives:</strong> $totalArtworks</li>";
    echo "</ul>";
    echo "</div>";
    
    // ========================================
    // 6. Instructions finales
    // ========================================
    echo "<hr>";
    echo "<div class='success'>";
    echo "<h2>🎉 Mise à jour terminée avec succès !</h2>";
    echo "<h3>Prochaines étapes:</h3>";
    echo "<ol>";
    echo "<li>Tester la connexion: <a href='login.html' target='_blank'>login.html</a></li>";
    echo "<li>Se connecter avec:</li>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> Un des emails ci-dessus</li>";
    echo "<li><strong>Mot de passe:</strong> <code>password123</code></li>";
    echo "</ul>";
    echo "<li><strong style='color:red;'>CHANGER immédiatement votre mot de passe après connexion!</strong></li>";
    echo "<li><strong style='color:red;'>SUPPRIMER ce fichier (update_db_auth.php) pour la sécurité!</strong></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h3>🔒 Sécurité</h3>";
    echo "<p>Pour sécuriser votre application:</p>";
    echo "<ul>";
    echo "<li>✓ Supprimez <code>update_db_auth.php</code></li>";
    echo "<li>✓ Changez tous les mots de passe par défaut</li>";
    echo "<li>✓ Activez HTTPS sur votre domaine</li>";
    echo "<li>✓ Protégez <code>config_mysql.php</code> avec .htaccess</li>";
    echo "</ul>";
    echo "</div>";
    
    // ========================================
    // 7. Informations de test
    // ========================================
    echo "<hr>";
    echo "<h2>🧪 Informations de Test</h2>";
    
    echo "<div class='info'>";
    echo "<h4>URLs de test:</h4>";
    echo "<ul>";
    echo "<li>Page de connexion: <a href='login.html'>login.html</a></li>";
    echo "<li>Espace artiste: <a href='artist_dashboard.html'>artist_dashboard.html</a> (protégé)</li>";
    echo "<li>API auth (check session): <a href='auth.php?action=check_session'>auth.php?action=check_session</a></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h4>Test de l'API:</h4>";
    echo "<pre>";
    echo "// Via JavaScript Console (F12)\n";
    echo "fetch('auth.php?action=login', {\n";
    echo "  method: 'POST',\n";
    echo "  headers: {'Content-Type': 'application/json'},\n";
    echo "  body: JSON.stringify({\n";
    echo "    email: 'marie@example.com',\n";
    echo "    password: 'password123'\n";
    echo "  })\n";
    echo "})\n";
    echo ".then(r => r.json())\n";
    echo ".then(console.log);";
    echo "</pre>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erreur lors de la mise à jour</h2>";
    echo "<p><strong>Message d'erreur:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Vérifications:</strong></p>";
    echo "<ul>";
    echo "<li>Le fichier config_mysql.php est-il correctement configuré?</li>";
    echo "<li>La base de données existe-t-elle?</li>";
    echo "<li>L'utilisateur a-t-il les permissions ALTER TABLE?</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</body></html>";
?>
