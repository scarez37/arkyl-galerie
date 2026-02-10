<?php
/**
 * RESET TOTAL ARKYL - MÉTHODE RADICALE
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<html><body style='font-family:sans-serif; padding:50px; text-align:center; background:#f4f4f4;'>";

// 1. SUPPRESSION PHYSIQUE DU FICHIER
if (file_exists('artgallery.db')) {
    if (unlink('artgallery.db')) {
        echo "<h2 style='color:orange'>🗑️ Ancien fichier 'artgallery.db' supprimé.</h2>";
    } else {
        echo "<h2 style='color:red'>⚠️ Impossible de supprimer le fichier. Le serveur le verrouille.</h2>";
    }
}

// 2. RECRÉATION AVEC LA STRUCTURE PARFAITE
try {
    $db = new SQLite3('artgallery.db');
    
    // Table Artistes (AVEC la colonne password_hash)
    $db->exec("CREATE TABLE artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        artist_name TEXT,
        email TEXT UNIQUE,
        password_hash TEXT, 
        role TEXT DEFAULT 'artist',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Table Oeuvres
    $db->exec("CREATE TABLE artworks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT,
        description TEXT,
        price DECIMAL(10,2),
        image_url TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tables Stats
    $db->exec("CREATE TABLE artwork_views (id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, viewer_ip VARCHAR(45), viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE artwork_likes (id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, user_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(artwork_id, user_id))");
    $db->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, artist_id INTEGER, buyer_name VARCHAR(255), buyer_email VARCHAR(255), sale_price DECIMAL(10,2), commission_amount DECIMAL(10,2), artist_revenue DECIMAL(10,2), status VARCHAR(20) DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    echo "<h1 style='color:green'>✅ BASE DE DONNÉES NEUVE ET PROPRE !</h1>";
    echo "<p>L'erreur 'no column named password_hash' est maintenant corrigée.</p>";
    echo "<br><a href='register.html' style='background:#000; color:#fff; padding:15px 30px; text-decoration:none; border-radius:30px; font-weight:bold;'>👉 CRÉER MON COMPTE MAINTENANT</a>";

} catch (Exception $e) {
    echo "<h1 style='color:red'>❌ Erreur : " . $e->getMessage() . "</h1>";
}
echo "</body></html>";
?>
