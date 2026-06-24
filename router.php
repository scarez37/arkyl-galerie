<?php
// ===================================================================
// ROUTER PHP — injecte les headers CORS sur TOUTES les requêtes
// avant que le script cible s'exécute.
// Utilisé par : php -S 0.0.0.0:$PORT router.php  (render.yaml)
// ===================================================================

// --- Headers CORS globaux ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Cross-Origin-Embedder-Policy: unsafe-none');
header('Cross-Origin-Opener-Policy: unsafe-none');
header('Cross-Origin-Resource-Policy: cross-origin');

// --- Répondre immédiatement aux pre-flight OPTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Laisser le serveur PHP servir le fichier normalement ---
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Fichier statique existant (images, CSS, JS…) → servir directement
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // PHP built-in server sert le fichier lui-même
}

// Fichier PHP explicitement demandé → l'inclure (headers déjà envoyés)
if (file_exists($file . '.php')) {
    include $file . '.php';
    exit();
}

// Fallback → index.html
include __DIR__ . '/index.html';
