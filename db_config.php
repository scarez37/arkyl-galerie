<?php
// db_config.php — connexion PostgreSQL (Railway, Neon, Render… n'importe quel hébergeur)

function getDatabase() {
    // 1. URL complète (Railway : ${{Postgres.DATABASE_URL}} ; Neon : chaîne de connexion)
    $url = getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL');

    if ($url) {
        $parts  = parse_url($url);
        $host   = $parts['host'] ?? '';
        $port   = $parts['port'] ?? 5432;
        $user   = isset($parts['user']) ? urldecode($parts['user']) : '';
        $pass   = isset($parts['pass']) ? urldecode($parts['pass']) : '';
        $dbname = ltrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $sslmode = $query['sslmode'] ?? null;
    } else {
        // 2. Variables PG* (Railway les fournit aussi automatiquement)
        $host   = getenv('PGHOST');
        $port   = getenv('PGPORT') ?: 5432;
        $user   = getenv('PGUSER');
        $pass   = getenv('PGPASSWORD');
        $dbname = getenv('PGDATABASE');
        $sslmode = getenv('PGSSLMODE') ?: null;
    }

    if (!$host || !$dbname) {
        throw new Exception("Erreur : DATABASE_URL (ou PGHOST/PGDATABASE) est introuvable. Vérifiez la liaison de la base PostgreSQL dans les variables du service.");
    }

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        if ($sslmode) {
            $dsn .= ";sslmode=$sslmode";
        }
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        return new PDO($dsn, $user, $pass, $options);

    } catch (PDOException $e) {
        throw new Exception("Connexion à la base PostgreSQL échouée : " . $e->getMessage());
    }
}
