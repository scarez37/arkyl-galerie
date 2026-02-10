<?php
/**
 * nettoyage_total.php - Vide toutes les données de test
 */
try {
    $db = new SQLite3('artgallery.db');
    
    echo "<h2>🧹 Nettoyage des données...</h2>";
    
    // On vide les tables mais on garde la structure
    $db->exec("DELETE FROM artwork_views");
    $db->exec("DELETE FROM sales");
    $db->exec("DELETE FROM artwork_likes");
    
    // On remet les compteurs à zéro (AUTOINCREMENT)
    $db->exec("DELETE FROM sqlite_sequence WHERE name='artwork_views'");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='sales'");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='artwork_likes'");

    echo "<p style='color:green'>✅ Les statistiques ont été remises à ZERO.</p>";
    echo "<p>Vérifie maintenant ton Dashboard. Si tu vois encore des chiffres, c'est le cache de ton navigateur.</p>";
    echo "<a href='artist_dashboard.html'>Retour au Dashboard</a>";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
