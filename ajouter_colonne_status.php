<?php
/**
 * AJOUTER LA COLONNE STATUS
 * Ce script ajoute la colonne 'status' manquante à la table artworks
 */

// Utiliser la configuration centralisée
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Ajout colonne status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #1a1a1a; color: #fff; }
        .success { color: #0f0; background: #002200; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { color: #f00; background: #220000; padding: 15px; border-radius: 8px; margin: 10px 0; }
    </style>
</head>
<body>";

echo "<h1>🔧 Ajout de la colonne 'status'</h1>";

try {
    $db = getDatabase();
    
    // Ajouter la colonne status si elle n'existe pas
    echo "<p>Ajout de la colonne 'status'...</p>";
    
    $db->exec("ALTER TABLE artworks ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'publiée'");
    
    echo "<div class='success'>";
    echo "<h2>✅ Succès !</h2>";
    echo "<p>La colonne 'status' a été ajoutée à la table artworks.</p>";
    echo "<p>Toutes les œuvres existantes ont automatiquement le statut 'publiée'.</p>";
    echo "</div>";
    
    // Mettre à jour les œuvres sans status
    echo "<p>Mise à jour des œuvres existantes...</p>";
    $stmt = $db->exec("UPDATE artworks SET status = 'publiée'");
    echo "<div class='success'>";
    echo "<p>✅ Œuvres mises à jour !</p>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p><strong>Tu peux maintenant :</strong></p>";
    echo "<ul>";
    echo "<li>Retourner sur ton site</li>";
    echo "<li>Publier une nouvelle œuvre</li>";
    echo "<li>Elle devrait apparaître dans la galerie !</li>";
    echo "</ul>";
    
    echo "<p class='error'><strong>⚠️ N'oublie pas de supprimer ce fichier après !</strong></p>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
