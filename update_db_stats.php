<?php
/**
 * Script d'initialisation des Statistiques (VERSION PRO - SANS FAUSSES DONNÉES)
 * Crée uniquement la structure pour enregistrer les vrais visiteurs et ventes.
 */

try {
    // Connexion ou création de la base
    $db = new SQLite3('artgallery.db');
    
    echo "<html><body style='font-family:sans-serif; padding:20px;'>";
    echo "<h2>🏗️ Installation de la structure des ventes</h2>";
    
    // 1. Table des vues (Pour compter les vrais visiteurs)
    $db->exec("CREATE TABLE IF NOT EXISTS artwork_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        artwork_id INTEGER NOT NULL,
        viewer_ip VARCHAR(45),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div style='color:green'>✓ Compteur de vues : Prêt (0 vue)</div>";
    
    // 2. Table des likes
    $db->exec("CREATE TABLE IF NOT EXISTS artwork_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        artwork_id INTEGER NOT NULL,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(artwork_id, user_id)
    )");
    echo "<div style='color:green'>✓ Système de likes : Prêt (0 like)</div>";
    
    // 3. Table des ventes (Livre de comptes)
    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        artwork_id INTEGER NOT NULL,
        artist_id INTEGER NOT NULL,
        buyer_name VARCHAR(255),
        buyer_email VARCHAR(255),
        sale_price DECIMAL(10,2) NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        artist_revenue DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div style='color:green'>✓ Livre de ventes : Prêt (0 vente)</div>";

    echo "<h3>🚀 Terminé ! Votre galerie est prête pour le monde réel.</h3>";
    echo "<p>Vos statistiques sont à 0. Elles augmenteront dès qu'un vrai visiteur viendra.</p>";
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<h3 style='color:red'>Erreur : " . $e->getMessage() . "</h3>";
}
?>
