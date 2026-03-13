<?php
// Script d'ajout de la colonne poids dans la table artworks
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();

    // Ajouter la colonne poids si elle n'existe pas
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS poids DECIMAL(10,2) DEFAULT NULL;");

    echo "<h2 style='color: #0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Colonne <strong>poids</strong> (DECIMAL 10,2) ajoutée à la table artworks.</li>";
    echo "</ul>";
    echo "<p style='color: #aaa;'>Tu peux maintenant uploader le fichier <strong>api_ajouter_oeuvre.php</strong> corrigé pour utiliser ce champ.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
