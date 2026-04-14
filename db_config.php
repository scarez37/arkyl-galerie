<?php
function getDatabase() {
    try {
        $host     = 'db.vkxzxzcufwjqaaioypdr.supabase.co';
        $port     = '5432';
        $dbname   = 'postgres';
        $user     = 'postgres';
        $password = 'Apia!2026#Xr9vLq';

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

        $db = new PDO($dsn, $user, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $db;

    } catch (PDOException $e) {
        error_log("Erreur connexion Supabase : " . $e->getMessage());
        throw new Exception("Impossible de se connecter à la base de données");
    }
}
?>
