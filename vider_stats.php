<?php
/**
 * vider_stats.php
 * Ce script NETTOIE toutes les fausses statistiques (Ventes, Vues, Likes)
 * mais GARDE les comptes et les oeuvres.
 */
header('Content-Type: text/html; charset=utf-8');

try {
    $db = new SQLite3('artgallery.db');
    
    echo "<h1>🧹 Grand Nettoyage des Chiffres...</h1>";
    
    // 1. Supprimer le contenu des tables de statistiques
    $db->exec("DELETE FROM sales");
    echo "<p>✅ Toutes les ventes (vraies et fausses) sont effacées.</p>";
    
    $db->exec("DELETE FROM artwork_views");
    echo "<p>✅ Toutes les vues sont effacées.</p>";
    
    $db->exec("DELETE FROM artwork_likes");
    echo "<p>✅ Tous les likes sont effacés.</p>";

    // 2. Remettre les compteurs à zéro (pour que la prochaine vente soit la n°1)
    $db->exec("DELETE FROM sqlite_sequence WHERE name='sales'");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='artwork_views'");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='artwork_likes'");

    echo "<hr>";
    echo "<h2 style='color:green'>C'est tout propre ! 🧼</h2>";
    echo "<p>Retourne sur ton tableau de bord. Tu devrais voir <b>0 partout</b>.</p>";
    echo "<a href='artist_dashboard.html' style='background:#333; color:white; padding:10px 20px; text-decoration:none;'>Retour au Dashboard</a>";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
