<?php
// Script pour créer la table users et ajouter les colonnes manquantes
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // 1. CRÉER LA TABLE USERS (Si elle n'existe pas encore)
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(255) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. AJOUTER LES NOUVELLES COLONNES DU PROFIL ARTISTE
    $db->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS phone VARCHAR(50),
        ADD COLUMN IF NOT EXISTS country VARCHAR(100),
        ADD COLUMN IF NOT EXISTS specialty TEXT,
        ADD COLUMN IF NOT EXISTS bio TEXT,
        ADD COLUMN IF NOT EXISTS website VARCHAR(255),
        ADD COLUMN IF NOT EXISTS social TEXT,
        ADD COLUMN IF NOT EXISTS avatar TEXT;
    ");

    // 3. AJOUTER LES COLONNES POUR LES ŒUVRES
    // (On ajoute aussi IF NOT EXISTS sur la table au cas où)
    $db->exec("
        CREATE TABLE IF NOT EXISTS artworks (
            id SERIAL PRIMARY KEY,
            artist_id VARCHAR(255),
            title VARCHAR(255),
            category VARCHAR(100),
            price DECIMAL(10,2),
            description TEXT,
            image_url TEXT,
            artist_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        ALTER TABLE artworks 
        ADD COLUMN IF NOT EXISTS technique VARCHAR(255),
        ADD COLUMN IF NOT EXISTS dimensions TEXT,
        ADD COLUMN IF NOT EXISTS photos TEXT;
    ");
    
    echo "<h2 style='color: #0f0;'>✅ Succès total !</h2>";
    echo "<p>La table 'users' a été créée et toutes les nouvelles colonnes sont prêtes.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
