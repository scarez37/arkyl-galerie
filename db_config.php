<?php
// db_config.php

function getDatabase() {
    // 1. Récupération de l'URL de connexion depuis l'environnement Render
    // Format attendu : postgresql://user:password@host:port/dbname
    $url = getenv('DATABASE_URL');

    if (!$url) {
        throw new Exception("La variable DATABASE_URL est manquante sur Render.");
    }

    // 2. Extraction des composants de l'URL
    $parts = parse_url($url);
    
    if (!$parts) {
        throw new Exception("Le format de DATABASE_URL est invalide.");
    }

    $host     = $parts['host'];
    $port     = $parts['port'] ?? 5432;
    $user     = $parts['user'];
    $pass     = $parts['pass'];
    $dbname   = ltrim($parts['path'], '/');

    try {
        // 3. Connexion PDO avec les bons paramètres pour PostgreSQL
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        return new PDO($dsn, $user, $pass, $options);
        
    } catch (PDOException $e) {
        // C'est ce message qui s'affiche sur ton site actuellement
        throw new Exception("Impossible de se connecter à la base de données : " . $e->getMessage());
    }
}
