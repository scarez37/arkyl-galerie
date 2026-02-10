<?php
/**
 * SCRIPT DE RÉPARATION FORCEE (VERSION 2)
 * Supprime les anciennes tables pour garantir la bonne structure
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new SQLite3('artgallery.db');
    
    echo "<html><body style='font-family:sans-serif; padding:40px; text-align:center;'>";
    echo "<h1>🚑 Réparation Forcée de la Base</h1>";
    
    // 1. SUPPRIMER la vieille table qui pose problème
    $db->exec("DROP TABLE IF EXISTS artists");
    echo "<p style='color:orange'>🗑️ Ancienne table 'artists' supprimée.</p>";

    // 2. RECRÉER la table avec la colonne password_hash
    $db->exec("CREATE TABLE artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        artist_name TEXT,
        email TEXT UNIQUE,
        password_hash TEXT, 
        role TEXT DEFAULT 'artist',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ Nouvelle table 'artists' créée (avec password_hash).</p>";

    // 3. Vérifier les autres tables (On les laisse si elles sont bonnes)
    $db->exec("CREATE TABLE IF NOT EXISTS artworks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT,
        description TEXT,
        price DECIMAL(10,2),
        image_url TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Tables stats
    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, artist_id INTEGER, 
        buyer_name VARCHAR(255), buyer_email VARCHAR(255), sale_price DECIMAL(10,2), 
        commission_amount DECIMAL(10,2), artist_revenue DECIMAL(10,2), 
        status VARCHAR(20) DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    $db->exec("CREATE TABLE IF NOT EXISTS artwork_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        viewer_ip VARCHAR(45), viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    $db->exec("CREATE TABLE IF NOT EXISTS artwork_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        user_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(artwork_id, user_id))");

    echo "<hr>";
    echo "<h3>🎉 Tout est propre !</h3>";
    echo "<p>Vous devez maintenant recréer votre compte.</p>";
    echo "<a href='register.html' style='background:#28a745; color:white; padding:15px 30px; text-decoration:none; border-radius:5px; font-weight:bold;'>👉 M'INSCRIRE MAINTENANT</a>";
    echo "</body></html>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur : " . $e->getMessage() . "</h2>";
}
?>
