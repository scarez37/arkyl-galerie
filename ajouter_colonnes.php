<?php
// Script pour convertir les colonnes ID en format Texte (VARCHAR)
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // 1. Convertir l'ID dans la table users
    $db->exec("ALTER TABLE users ALTER COLUMN id TYPE VARCHAR(255) USING id::VARCHAR;");

    // 2. Convertir l'artist_id dans la table artworks
    $db->exec("ALTER TABLE artworks ALTER COLUMN artist_id TYPE VARCHAR(255) USING artist_id::VARCHAR;");
    
    echo "<h2 style='color: #0f0;'>✅ Succès total !</h2>";
    echo "<p>Les cases ID ont été agrandies ! Ton identifiant Google rentre parfaitement maintenant.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
