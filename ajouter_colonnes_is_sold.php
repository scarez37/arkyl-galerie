<?php
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();

    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE;");
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS badge VARCHAR(50) DEFAULT 'Disponible';");
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'publiée';");

    // Synchroniser : si status = 'vendue', mettre is_sold = TRUE et badge = 'Vendu'
    $db->exec("UPDATE artworks SET is_sold = TRUE, badge = 'Vendu' WHERE status = 'vendue' AND (is_sold IS NULL OR is_sold = FALSE);");
    // Synchroniser dans l'autre sens
    $db->exec("UPDATE artworks SET badge = 'Vendu', status = 'vendue' WHERE is_sold = TRUE AND badge != 'Vendu';");

    echo "<h2 style='color:#0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Colonne <strong>is_sold</strong> ajoutée (ou déjà existante)</li>";
    echo "<li>Colonne <strong>badge</strong> ajoutée (ou déjà existante)</li>";
    echo "<li>Colonne <strong>status</strong> vérifiée</li>";
    echo "<li>Synchronisation is_sold ↔ badge ↔ status effectuée</li>";
    echo "</ul>";
    echo "<p style='color:#aaa;'>Upload maintenant <strong>api_marquer_vendu.php</strong> sur le serveur.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:#f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
