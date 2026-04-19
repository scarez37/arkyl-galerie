<?php
// db_config.php

function getDatabase() {
    $url = getenv('DATABASE_URL');
    if (!$url) throw new Exception("DATABASE_URL manquante.");

    $parts = parse_url($url);
    $host   = $parts['host'];
    $port   = $parts['port'] ?? 6543; // Utilise 6543 par défaut pour le pooler
    $user   = $parts['user'];
    $pass   = $parts['pass'];
    $dbname = ltrim($parts['path'], '/');

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // OBLIGATOIRE pour le mode Transaction de Supabase
            PDO::ATTR_EMULATE_PREPARES   => true, 
        ];

        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new Exception("Erreur de connexion : " . $e->getMessage());
    }
}
