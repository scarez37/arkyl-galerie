<?php
// Script temporaire pour créer les tables de commandes
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // 🧹 ON NETTOIE L'ANCIENNE TABLE BROUILLON D'ABORD
    $db->exec("DROP TABLE IF EXISTS order_items CASCADE;");
    $db->exec("DROP TABLE IF EXISTS orders CASCADE;");
    
    // On va lire le fichier SQL que tu as créé
    $chemin_fichier_sql = __DIR__ . '/migration_orders.sql';
    
    if (!file_exists($chemin_fichier_sql)) {
        throw new Exception("Le fichier migration_orders.sql est introuvable sur le serveur.");
    }

    $sql = file_get_contents($chemin_fichier_sql);
    
    // On exécute toutes les commandes SQL du nouveau fichier
    $db->exec($sql);
    
    echo "<h2 style='color: #0f0;'>✅ Succès total !</h2>";
    echo "<p>L'ancienne table a été effacée. Les NOUVELLES tables 'orders', 'order_items' et 'cart' sont prêtes et parfaites.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
