<?php
// Script de création de la table des adresses de livraison
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";

try {
    $db = getDatabase();

    // Création de la table adresses_livraison
    $db->exec("CREATE TABLE IF NOT EXISTS adresses_livraison (
        id         SERIAL PRIMARY KEY,
        user_id    VARCHAR(255) NOT NULL UNIQUE,
        nom        VARCHAR(255),
        tel        VARCHAR(50),
        quartier   VARCHAR(255),
        ville      VARCHAR(255),
        pays       VARCHAR(255) DEFAULT 'Côte d''Ivoire',
        detail     TEXT,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    );");

    // Index pour accélérer les recherches par user_id
    $db->exec("CREATE INDEX IF NOT EXISTS idx_adresses_user_id ON adresses_livraison(user_id);");

    echo "<h2 style='color: #0f0;'>✅ Mise à jour réussie !</h2>";
    echo "<ul>";
    echo "<li>Table <strong>adresses_livraison</strong> créée (ou déjà existante).</li>";
    echo "<li>Index sur <strong>user_id</strong> ajouté.</li>";
    echo "<li>Les champs : nom, tel, quartier, ville, pays, detail sont prêts.</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h2 style='color: #f00;'>❌ Erreur SQL</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
