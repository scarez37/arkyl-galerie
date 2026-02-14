<?php
/**
 * Configuration de la base de données pour ARKYL
 */

function getDB() {
    try {
        // On pointe vers votre fichier de base de données SQLite
        $dbPath = __DIR__ . '/artgallery.db';
        $db = new PDO("sqlite:" . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}
?>
