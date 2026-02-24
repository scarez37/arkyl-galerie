<?php
// Script de mise à jour de la base de données PostgreSQL
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // 1. Correction des IDs (Ce que tu avais déjà fait)
    $db->exec("ALTER TABLE users ALTER COLUMN id TYPE VARCHAR(255) USING id::VARCHAR;");
    $db->exec("ALTER TABLE artworks ALTER COLUMN artist_id TYPE VARCHAR(255) USING artist_id::VARCHAR;");
    
    // 2. Système de livraison (Pour La Poste de Côte d'Ivoire)
    $db->exec("ALTER TABLE orders 
               ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100),
               ADD COLUMN IF NOT EXISTS shipping_status VARCHAR(50) DEFAULT 'en_preparation';");

    // 3. NOUVEAU : Création de la table des abonnements
    $db->exec("CREATE TABLE IF NOT EXISTS followers (
        user_id VARCHAR(255) NOT NULL,
        artist_id VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, artist_id)
    );");
    
    echo "<h2 style='color: #0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Table 'followers' prête pour sauvegarder les abonnements.</li>";
    echo "<li>Colonnes de livraison prêtes.</li>";
    echo "<li>Format des IDs vérifié.</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
