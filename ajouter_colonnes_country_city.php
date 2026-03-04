<?php
// Script d'ajout des colonnes country et city dans la table artworks
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();

    // Ajouter les colonnes country et city si elles n'existent pas
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT '';");
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT '';");
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS artist_country VARCHAR(100) DEFAULT '';");

    echo "<h2 style='color: #0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Colonne <strong>country</strong> ajoutée à la table artworks.</li>";
    echo "<li>Colonne <strong>city</strong> ajoutée à la table artworks.</li>";
    echo "<li>Colonne <strong>artist_country</strong> ajoutée à la table artworks.</li>";
    echo "</ul>";
    echo "<p style='color: #aaa;'>Tu peux maintenant uploader le fichier <strong>api_ajouter_oeuvre.php</strong> corrigé.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
