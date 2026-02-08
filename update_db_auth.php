<?php
/**
 * REPARATEUR DE BASE DE DONNEES ARKYL
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config_mysql.php';

echo "<html><head><meta charset='utf-8'></head><body style='font-family:sans-serif; padding:50px;'>";
try {
    $db = getDB();
    
    // Suppression et recréation propre pour éviter les erreurs de colonnes manquantes
    // ATTENTION : Cela effacera les comptes tests créés précédemment
    $db->exec("DROP TABLE IF EXISTS artists");
    
    $sql = "CREATE TABLE artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        artist_name TEXT,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'artist',
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    
    echo "<h1 style='color:green'>✅ Base de données réparée !</h1>";
    echo "<p>La table <b>artists</b> a été créée avec succès.</p>";
    echo "<p>👉 <b>Etape suivante :</b> Retournez sur <a href='register.html'>register.html</a> pour créer votre vrai compte.</p>";

} catch (Exception $e) {
    echo "<h1 style='color:red'>❌ Erreur de réparation</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "</body></html>";
