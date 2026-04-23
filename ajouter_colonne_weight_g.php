<?php
// Script d'ajout de la colonne weight_g dans la table artworks
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();

    // Ajouter la colonne weight_g si elle n'existe pas
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS weight_g INTEGER DEFAULT 0;");

    echo "<h2 style='color: #0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Colonne <strong>weight_g</strong> (INTEGER, défaut 0) ajoutée à la table artworks.</li>";
    echo "</ul>";
    echo "<p style='color: #aaa;'>Tu peux maintenant déployer <strong>api_galerie_publique.php</strong> avec le champ weight_g.</p>";
    echo "<p style='color: #f90;'>⚠️ Supprime ce fichier du serveur après utilisation.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
