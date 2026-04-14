<?php
/**
 * 🔧 CONFIGURATION BASE DE DONNÉES POSTGRESQL - SUPABASE
 */

define('DATABASE_URL', 'postgresql://postgres:Apia!2026#Xr9vLq@db.vkxzxzcufwjqaaioypdr.supabase.co:5432/postgres');

function getDatabase() {
    try {
        // ✅ Regex au lieu de parse_url() — nécessaire car le mot de passe contient !, #
        preg_match('/^postgresql:\/\/([^:]+):(.+)@([^:@]+):(\d+)\/(.+)$/', DATABASE_URL, $m);

        if (!$m) {
            throw new Exception("URL de base de données invalide");
        }

        $dsn = "pgsql:host={$m[3]};port={$m[4]};dbname={$m[5]};sslmode=require";

        $db = new PDO($dsn, $m[1], $m[2]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $db;

    } catch (PDOException $e) {
        error_log("Erreur connexion Supabase : " . $e->getMessage());
        throw new Exception("Impossible de se connecter à la base de données");
    }
}

function getDatabasePath() { return DATABASE_URL; }

function testConnection() {
    try {
        $db = getDatabase();
        return ['success' => true, 'message' => '✅ Connexion Supabase réussie !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Décommenter pour tester :
// header('Content-Type: application/json');
// echo json_encode(testConnection(), JSON_PRETTY_PRINT);
?>
