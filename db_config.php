<?php
/**
 * 🔧 CONFIGURATION CENTRALISÉE DE LA BASE DE DONNÉES
 * 
 * Ce fichier définit quel fichier de base de données utiliser.
 * Tous les autres fichiers PHP doivent l'inclure avec :
 * require_once __DIR__ . '/db_config.php';
 */

// Fonction pour trouver et retourner le chemin de la base de données
function getDatabasePath() {
    // Liste des chemins possibles, par ORDRE DE PRIORITÉ
    $possiblePaths = [
        // 1. Chemin détecté par diagnostic_db.php
        '/var/www/html/artgallery.db',
        
        // 2. Chemins Render
        '/opt/render/project/src/artgallery.db',
        '/opt/render/project/src/galerie.db',
        
        // 3. Chemin relatif (même dossier)
        __DIR__ . '/artgallery.db',
        __DIR__ . '/galerie.db',
    ];
    
    // Chercher le premier qui existe
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Si aucun n'existe, retourner le chemin par défaut
    // (celui qui sera créé si besoin)
    return '/var/www/html/artgallery.db';
}

// Fonction pour obtenir une connexion PDO
function getDatabase() {
    $dbPath = getDatabasePath();
    
    // Créer la connexion
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    return $db;
}

// Pour debug : afficher quel fichier est utilisé
function getDebugInfo() {
    $path = getDatabasePath();
    $exists = file_exists($path) ? 'OUI' : 'NON';
    $size = file_exists($path) ? filesize($path) : 0;
    
    return [
        'path' => $path,
        'exists' => $exists,
        'size' => $size,
        'readable' => is_readable($path) ? 'OUI' : 'NON',
        'writable' => is_writable($path) ? 'OUI' : 'NON'
    ];
}

// Définir le chemin comme constante globale
define('DB_PATH', getDatabasePath());
?>
