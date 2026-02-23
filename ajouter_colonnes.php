<?php
// Script temporaire pour ajouter les colonnes manquantes
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();
    
    // 1. Ajouter les colonnes pour les utilisateurs (artistes)
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

    // 2. Ajouter les colonnes pour les œuvres
    $db->exec("
        ALTER TABLE artworks 
        ADD COLUMN IF NOT EXISTS technique VARCHAR(255),
        ADD COLUMN IF NOT EXISTS dimensions TEXT,
        ADD COLUMN IF NOT EXISTS photos TEXT;
    ");
    
    echo "<h2 style='color: #0f0;'>✅ Succès total !</h2>";
    echo "<p>Les nouvelles colonnes ont été ajoutées à tes tables 'users' et 'artworks'.</p>";
    echo "<p>Tes modifications depuis l'application devraient maintenant s'enregistrer !</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
