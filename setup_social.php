<?php
require_once 'config_mysql.php';
try {
    $db = getDB();
    
    // Table pour les Likes
    $db->exec("CREATE TABLE IF NOT EXISTS likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        artwork_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, artwork_id)
    )");

    // Table pour les Commentaires
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        artwork_id INTEGER,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    echo "✅ Système social (Likes/Commentaires) prêt !";
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
