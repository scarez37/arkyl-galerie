<?php
/**
 * Script de mise à jour de la base de données (VERSION SQLITE CORRIGÉE)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connexion à la configuration existante
require_once 'config_mysql.php';

echo "<html><head><meta charset='utf-8'><title>Mise à jour ARKYL</title></head><body>";
echo "<h1>🔐 Mise à jour de la Base de Données</h1>";

try {
    $db = getDB();
    echo "<p style='color:green'>✅ Connexion à la base de données réussie</p>";

    // Liste des colonnes nécessaires pour la sécurité (Version sans AFTER pour SQLite)
    $colonnes = [
        "email" => "VARCHAR(255)",
        "password_hash" => "VARCHAR(255)",
        "last_login" => "DATETIME",
        "role" => "VARCHAR(20) DEFAULT 'artist'"
    ];

    foreach ($colonnes as $nom => $type) {
        try {
            // Tentative d'ajout de la colonne
            $db->exec("ALTER TABLE artists ADD COLUMN $nom $type");
            echo "✅ Colonne <b>$nom</b> ajoutée avec succès.<br>";
        } catch (PDOException $e) {
            // Si la colonne existe déjà, on l'ignore
            echo "ℹ️ Colonne <b>$nom</b> déjà présente.<br>";
        }
    }

    echo "<h2 style='color:green'>🎉 ARKYL est maintenant sécurisé !</h2>";
    echo "<p><a href='register.html'>Cliquez ici pour créer votre compte</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
