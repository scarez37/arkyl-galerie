<?php
/**
 * 🔧 CONFIGURATION BASE DE DONNÉES POSTGRESQL
 * Ce fichier récupère automatiquement l'URL de connexion depuis l'environnement (Railway/Render)
 */

// 1. Récupération de l'URL de la base de données
// On cherche d'abord la variable d'environnement 'DATABASE_URL' injectée par Railway ou Render.
// Si elle n'existe pas, on utilise l'ancien lien par défaut pour ne pas casser le code local.
$db_url = getenv('DATABASE_URL') ?: 'postgresql://arkyl_db_user:196PXpwGBH4Qr31JPyyeog0S1hn04XVs@dpg-d69ptr7gi27c73clct3g-a/arkyl_db';

define('DATABASE_URL', $db_url);

/**
 * Fonction pour obtenir une connexion PDO à PostgreSQL
 */
function getDatabase() {
    try {
        // Parser l'URL PostgreSQL
        $dbopts = parse_url(DATABASE_URL);
        
        // Railway et Render incluent souvent le port dans l'URL. 
        // On le récupère dynamiquement ou on utilise 5432 par défaut.
        $port = isset($dbopts["port"]) ? $dbopts["port"] : "5432";
        
        // Construction du DSN pour PostgreSQL
        $dsn = "pgsql:host=" . $dbopts["host"] . 
               ";port=" . $port .
               ";dbname=" . ltrim($dbopts["path"], '/') .
               ";sslmode=require";
        
        // Création de la connexion PDO
        $db = new PDO(
            $dsn,
            $dbopts["user"],
            $dbopts["pass"]
        );
        
        // Configuration de PDO pour la gestion des erreurs
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $db;
        
    } catch (PDOException $e) {
        // En cas d'erreur, logger le message technique
        error_log("Erreur connexion PostgreSQL : " . $e->getMessage());
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
    $dbopts = parse_url(DATABASE_URL);
    
    return [
        'type' => 'PostgreSQL',
        'host' => isset($dbopts["host"]) ? $dbopts["host"] : 'unknown',
        'database' => isset($dbopts["path"]) ? ltrim($dbopts["path"], '/') : 'unknown',
        'status' => 'initialized'
    ];
}

/**
 * Test de connexion (utile pour le premier déploiement)
 */
function testConnection() {
    try {
        $db = getDatabase();
        return [
            'success' => true,
            'message' => 'Connexion réussie !',
            'info' => getDebugInfo()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
