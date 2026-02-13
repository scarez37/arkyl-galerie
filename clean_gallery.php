<?php
/**
 * NETTOYAGE DE LA GALERIE
 * Supprime toutes les œuvres de la base de données pour repartir à zéro.
 */
header('Content-Type: text/html; charset=utf-8');

try {
    $db = new SQLite3('artgallery.db');
    
    // On compte combien il y a d'œuvres avant
    $countBefore = $db->querySingle("SELECT COUNT(*) FROM artworks");
    
    // On supprime TOUT dans la table 'artworks'
    $db->exec("DELETE FROM artworks");
    
    // On remet le compteur d'ID à zéro
    $db->exec("DELETE FROM sqlite_sequence WHERE name='artworks'");

    echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'>";
    echo "<h1 style='color:green'>✅ Nettoyage Terminé !</h1>";
    echo "<p><b>$countBefore</b> œuvres de test ont été supprimées.</p>";
    echo "<p>Votre galerie est maintenant <b>vide</b> et prête pour vos vraies œuvres.</p>";
    echo "<br>";
    echo "<a href='index.html' style='background:#333; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Retour à l'accueil</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
