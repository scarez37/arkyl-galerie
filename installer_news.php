<?php
require_once __DIR__ . '/db_config.php';
try {
    $db = getDatabase();
    // Cette commande crée la table "news"
    $db->exec("CREATE TABLE IF NOT EXISTS news (
        id SERIAL PRIMARY KEY,
        icon TEXT,
        gradient VARCHAR(50),
        text TEXT NOT NULL,
        is_image BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ La table 'news' a été créée avec succès !";
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
