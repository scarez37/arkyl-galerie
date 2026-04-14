<?php
/**
 * 🔧 CONFIGURATION BASE DE DONNÉES POSTGRESQL
 * Ce fichier gère la connexion à PostgreSQL sur Supabase
 */

// ✅ Connexion Supabase
define('DATABASE_URL', 'postgresql://postgres:Apia!2026#Xr9vLq@db.vkxzxzcufwjqaaioypdr.supabase.co:5432/postgres');

/**
 * Fonction pour obtenir une connexion PDO à PostgreSQL
 */
function getDatabase() {
    try {
        $url = DATABASE_URL;

        // Parser manuellement l'URL pour éviter les bugs avec les caractères spéciaux (#, !, @)
        preg_match('/^postgresql:\/\/([^:]+):(.+)@([^:]+):(\d+)\/(.+)$/', $url, $matches);

        if (!$matches) {
            throw new Exception("URL de base de données invalide");
        }

        $user   = $matches[1];
        $pass   = $matches[2];
        $host   = $matches[3];
        $port   = $matches[4];
        $dbname = $matches[5];

        // Construire le DSN pour PostgreSQL avec SSL obligatoire (Supabase)
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

        // Créer la connexion PDO
        $db = new PDO($dsn, $user, $pass);

        // Configurer PDO
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $db;

    } catch (PDOException $e) {
        error_log("Erreur connexion PostgreSQL (Supabase) : " . $e->getMessage());
        throw new Exception("Impossible de se connecter à la base de données");
    }
}

/**
 * Fonction pour obtenir le chemin de la base (pour compatibilité)
 */
function getDatabasePath() {
    return DATABASE_URL;
}

/**
 * Fonction de debug (infos sur la connexion)
 */
function getDebugInfo() {
    preg_match('/^postgresql:\/\/([^:]+):(.+)@([^:]+):(\d+)\/(.+)$/', DATABASE_URL, $m);
    return [
        'type'     => 'PostgreSQL',
        'provider' => 'Supabase',
        'host'     => $m[3] ?? 'N/A',
        'port'     => $m[4] ?? '5432',
        'database' => $m[5] ?? 'N/A',
        'user'     => $m[1] ?? 'N/A',
        'status'   => 'connected'
    ];
}

/**
 * Test de connexion
 */
function testConnection() {
    try {
        $db = getDatabase();
        return [
            'success' => true,
            'message' => '✅ Connexion Supabase PostgreSQL réussie !',
            'info'    => getDebugInfo()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Auto-test en mode debug (décommenter pour tester)
// header('Content-Type: application/json');
// echo json_encode(testConnection(), JSON_PRETTY_PRINT);
?>
