<?php
// ==================== INSTALLATION TABLE COMMANDES ====================
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Installation Commandes</title></head><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // Création de la table orders
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id SERIAL PRIMARY KEY,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        buyer_id VARCHAR(255) NOT NULL,
        seller_id INTEGER NOT NULL,
        artwork_id INTEGER NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(50) DEFAULT 'payée_en_attente',
        shipping_address TEXT NOT NULL,
        tracking_number VARCHAR(100),
        shipping_proof_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (artwork_id) REFERENCES artworks(id)
    )";
    
    $db->exec($sql);
    
    echo "<h2 style='color: #0f0;'>✅ Succès ! La table 'orders' a été créée.</h2>";
    echo "<p>Le système de Tiers de Confiance est maintenant prêt à enregistrer les transactions.</p>";
    echo "<p>Tu peux supprimer ce fichier (installer_commandes.php).</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
