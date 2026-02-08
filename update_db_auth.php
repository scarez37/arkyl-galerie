<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config_mysql.php';

echo "<html><head><meta charset='utf-8'></head><body>";
try {
    $db = getDB();
    
    // 1. Création de la table artists si elle n'existe pas
    $sql = "CREATE TABLE IF NOT EXISTS artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        artist_name TEXT,
        email TEXT UNIQUE,
        password_hash TEXT,
        role TEXT DEFAULT 'artist',
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "<h2 style='color:green'>✅ Table 'artists' prête !</h2>";
    echo "<p>Vous pouvez maintenant créer votre compte : <a href='register.html'>S'inscrire ici</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Erreur : " . $e->getMessage() . "</h2>";
}
echo "</body></html>";
