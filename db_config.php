<?php
// db_config.php

function getDatabase() {
    // Sur Render, l'URL interne est injectée automatiquement via cette variable
    $url = getenv('DATABASE_URL');

    if (!$url) {
        throw new Exception("Erreur : La variable DATABASE_URL est introuvable. Vérifiez la liaison de la base dans Render.");
    }

    // Découpage de l'URL (format postgresql://user:pass@host:port/dbname)
    $parts = parse_url($url);
    
    $host     = $parts['host'];
    $port     = $parts['port'] ?? 5432;
    $user     = $parts['user'];
    $pass     = $parts['pass'];
    $dbname   = ltrim($parts['path'], '/');

    try {
        // Connexion simplifiée (plus besoin d'émuler les requêtes préparées ici)
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        return new PDO($dsn, $user, $pass, $options);
        
    } catch (PDOException $e) {
        throw new Exception("Connexion à la base Render échouée : " . $e->getMessage());
    }
}
