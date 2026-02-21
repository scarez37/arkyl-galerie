<?php
// Script temporaire pour créer les tables de commandes
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // On va lire le fichier SQL que tu as créé
    $chemin_fichier_sql = __DIR__ . '/migration_orders.sql';
    
    if (!file_exists($chemin_fichier_sql)) {
        throw new Exception("Le fichier migration_orders.sql est introuvable sur le serveur.");
    }

    $sql = file_get_contents($chemin_fichier_sql);
    
    // On exécute toutes les commandes SQL d'un coup
    $db->exec($sql);
    
    echo "<h2 style='color: #0f0;'>✅ Succès total !</h2>";
    echo "<p>Les tables 'orders', 'order_items' et 'cart' sont prêtes.</p>";
    echo "<p>Tu peux maintenant supprimer ce fichier (executer_migration.php) et le fichier SQL.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
