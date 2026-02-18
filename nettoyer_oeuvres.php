<?php
/**
 * NETTOYEUR D'ŒUVRES FANTÔMES
 * Supprime les œuvres qui n'ont pas d'image valide
 */

require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Nettoyage de la Galerie</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #1a1a1a; color: #fff; }
        .success { color: #0f0; background: #002200; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { color: #f00; background: #220000; padding: 15px; border-radius: 8px; margin: 10px 0; }
    </style>\n</head>
<body>";

echo "<h1>🧹 Grand nettoyage de la galerie</h1>";

try {
    $db = getDatabase();
    
    // Supprimer les œuvres avec image null, vide ou contenant le texte 'null'
    $sql = "DELETE FROM artworks WHERE image_url IS NULL OR image_url = '' OR image_url = 'null' OR photos = '[]' OR photos IS NULL";
    
    $stmt = $db->exec($sql);
    
    echo "<div class='success'>";
    echo "<h2>✅ Nettoyage terminé !</h2>";
    echo "<p><strong>$stmt</strong> ancienne(s) œuvre(s) fantôme(s) ont été supprimées définitivement de la base de données.</p>";
    echo "</div>";
    
    echo "<p>Tu peux maintenant retourner sur ta galerie, les erreurs /null ont disparu !</p>";
    echo "<p class='error'><strong>⚠️ IMPORTANT : N'oublie pas de supprimer ce fichier (nettoyer_oeuvres.php) de ton projet après l'avoir utilisé.</strong></p>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
