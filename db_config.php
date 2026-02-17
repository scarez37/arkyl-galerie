<?php
/**
 * 🔧 CONFIGURATION BASE DE DONNÉES POSTGRESQL
 * Ce fichier gère la connexion à PostgreSQL sur Render
 */

// 👉 REMPLACE LE TEXTE CI-DESSOUS PAR TON LIEN POSTGRESQL DE RENDER
define('DATABASE_URL', 'TON_LIEN_SECRET_ICI');

/**
 * Fonction pour obtenir une connexion PDO à PostgreSQL
 */
function getDatabase() {
    try {
        // Parser l'URL PostgreSQL de Render
        $dbopts = parse_url(DATABASE_URL);
        
        // Construire le DSN pour PostgreSQL
        $dsn = "pgsql:host=" . $dbopts["host"] . 
               ";port=5432" .
               ";dbname=" . ltrim($dbopts["path"], '/') .
               ";sslmode=require";
        
        // Créer la connexion PDO
        $db = new PDO(
            $dsn,
            $dbopts["user"],
            $dbopts["pass"]
        );
        
        // Configurer PDO
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $db;
        
    } catch (PDOException $e) {
        // En cas d'erreur, logger et retourner null
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
        'host' => $dbopts["host"],
        'database' => ltrim($dbopts["path"], '/'),
        'status' => 'connected'
    ];
}

/**
 * Test de connexion (à supprimer en production)
 */
function testConnection() {
    try {
        $db = getDatabase();
        return [
            'success' => true,
            'message' => 'Connexion PostgreSQL réussie !',
            'info' => getDebugInfo()
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
