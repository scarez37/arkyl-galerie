<?php
// ===================================================================
// router.php — routeur du serveur PHP intégré :  php -S 0.0.0.0:$PORT router.php
// (développement local ou hébergement sans Apache). En production Docker/Apache,
// ce sont les règles du fichier .htaccess qui jouent ce rôle.
//
// Sécurité :
//  • les chemins sont résolus avec realpath() et doivent rester DANS le dossier du site
//    (plus d'« include » construit à partir de l'URL → pas de remontée « ../ »)
//  • les fichiers internes (config, scripts SQL, docs…) ne sont jamais servis
// La gestion CORS est faite par chaque endpoint (bootstrap.php), pas ici.
// ===================================================================

$base = realpath(__DIR__);
$uri  = str_replace("\0", '', urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));

// Fichiers internes : jamais accessibles depuis Internet
$blocked = '~(^|/)(\.[^/]|vendor/|supabase/|scripts/|docs/|node_modules/)'
         . '|\.(sql|md|lock|ya?ml|sh|log|env|bak|dockerignore|gitignore)$'
         . '|(^|/)(composer\.json|vercel\.json|Dockerfile|router\.php|db_config\.php|bootstrap\.php|auth_helper\.php'
         . '|cors_helper\.php|cors_init\.php|notify_helpers\.php|stripe_order_helper\.php)$~i';
if (preg_match($blocked, $uri)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$inside = function ($p) use ($base) {
    return $p !== false && strpos($p, $base . DIRECTORY_SEPARATOR) === 0;
};

// Accueil
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Fichier existant (statique ou .php) dans le dossier du site → laissé au serveur intégré
$path = realpath($base . $uri);
if ($inside($path) && is_file($path)) {
    return false;
}

// URL sans extension : /api_galerie_publique → api_galerie_publique.php
$phpPath = realpath($base . $uri . '.php');
if ($inside($phpPath) && is_file($phpPath)) {
    require $phpPath;
    return true;
}

// Repli : l'application (page unique)
require __DIR__ . '/index.php';
return true;
