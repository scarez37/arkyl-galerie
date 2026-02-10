<?php
/**
 * SCRIPT DE RÉPARATION TOTALE
 * Recrée toutes les tables manquantes (Compte, Oeuvres, Stats)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // On force la création/connexion au fichier DB
    $db = new SQLite3('artgallery.db');
    
    echo "<html><body style='font-family:sans-serif; padding:40px; text-align:center;'>";
    echo "<h1>🚑 Réparation de la Base de Données</h1>";
    
    // 1. Recréer la table ARTISTS (Indispensable pour la connexion)
    $db->exec("CREATE TABLE IF NOT EXISTS artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        artist_name TEXT,
        email TEXT UNIQUE,
        password_hash TEXT,
        role TEXT DEFAULT 'artist',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ Table 'artists' restaurée.</p>";

    // 2. Recréer la table ARTWORKS (Pour les oeuvres)
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
    echo "<p style='color:green'>✅ Table 'artworks' restaurée.</p>";

    // 3. Recréer les tables de STATISTIQUES (Ventes, Vues, Likes)
    $db->exec("CREATE TABLE IF NOT EXISTS artwork_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        viewer_ip VARCHAR(45), viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    $db->exec("CREATE TABLE IF NOT EXISTS artwork_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        user_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(artwork_id, user_id))");

    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, artist_id INTEGER, 
        buyer_name VARCHAR(255), buyer_email VARCHAR(255), sale_price DECIMAL(10,2), 
        commission_amount DECIMAL(10,2), artist_revenue DECIMAL(10,2), 
        status VARCHAR(20) DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        
    echo "<p style='color:green'>✅ Tables Statistiques (Ventes/Vues) restaurées.</p>";

    echo "<hr>";
    echo "<h3>⚠️ Important : Votre ancien compte a été effacé.</h3>";
    echo "<p>Comme la base a été réinitialisée, vous devez <b>créer un nouveau compte</b>.</p>";
    echo "<a href='register.html' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>👉 Re-créer mon compte</a>";
    echo "</body></html>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur : " . $e->getMessage() . "</h2>";
}
?>
