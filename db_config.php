<?php
// ===================================================================
// db_config.php — connexion PostgreSQL (Supabase, Neon, Railway, local…)
//
// Variables d'environnement lues (par ordre de priorité) :
//   SUPABASE_DB_URL  → chaîne de connexion Supabase (recommandé)
//   DATABASE_URL     → chaîne de connexion PostgreSQL générique
//   DATABASE_PUBLIC_URL
//   sinon PGHOST / PGPORT / PGUSER / PGPASSWORD / PGDATABASE / PGSSLMODE
//
// SUPABASE — quelle chaîne utiliser ?
//   Supabase > Project Settings > Database > Connection string :
//   • « Session pooler » (port 5432) : le plus simple, compatible IPv4 → RECOMMANDÉ
//       postgresql://postgres.<ref>:<MOT_DE_PASSE>@aws-0-<region>.pooler.supabase.com:5432/postgres
//   • « Transaction pooler » (port 6543) : supporté (les requêtes préparées
//     côté serveur sont alors désactivées automatiquement).
//   • « Direct connection » (db.<ref>.supabase.co) : IPv6 uniquement, à éviter
//     sur Render/Railway/Vercel sans add-on IPv4.
//   Si le mot de passe contient des caractères spéciaux (@ : / ? # %),
//   encodez-le en URL (ex. @ → %40).
// ===================================================================

if (!function_exists('getDatabase')) {

    /**
     * Retourne une connexion PDO PostgreSQL (réutilisée pendant la requête).
     * Ne divulgue jamais les détails de connexion au client : l'erreur réelle
     * est écrite dans les logs serveur uniquement.
     */
    function getDatabase(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $url = getenv('SUPABASE_DB_URL') ?: getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL');

        if ($url) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host'])) {
                error_log('ARKYL db_config : URL de base de données invalide (vérifiez l\'encodage du mot de passe).');
                throw new Exception('Configuration base de données invalide.');
            }
            $host    = $parts['host'];
            $port    = $parts['port'] ?? 5432;
            $user    = isset($parts['user']) ? urldecode($parts['user']) : '';
            $pass    = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            $dbname  = ltrim($parts['path'] ?? '', '/');
            parse_str($parts['query'] ?? '', $query);
            $sslmode = $query['sslmode'] ?? null;
        } else {
            $host    = getenv('PGHOST');
            $port    = getenv('PGPORT') ?: 5432;
            $user    = getenv('PGUSER');
            $pass    = getenv('PGPASSWORD');
            $dbname  = getenv('PGDATABASE');
            $sslmode = getenv('PGSSLMODE') ?: null;
        }

        if (!$host || !$dbname) {
            error_log('ARKYL db_config : SUPABASE_DB_URL / DATABASE_URL (ou PGHOST + PGDATABASE) introuvable.');
            throw new Exception('Base de données non configurée.');
        }

        // SSL : obligatoire chez Supabase → on l'active par défaut pour tout hôte distant.
        if (!$sslmode) {
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || strpos($host, '/') === 0;
            $sslmode = $isLocal ? 'prefer' : 'require';
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 8,
        ];

        // Transaction pooler (pgbouncer / Supavisor, port 6543) : pas de
        // requêtes préparées côté serveur → on les émule côté PHP.
        if ((int)$port === 6543 || getenv('DB_EMULATE_PREPARES') === '1') {
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            // Le message réel (hôte, utilisateur…) reste dans les logs.
            error_log('ARKYL db_config : connexion impossible — ' . $e->getMessage());
            throw new Exception('Connexion à la base de données impossible.');
        }
    }
}
