<?php
/**
 * CORS INIT — Inclure EN PREMIER dans api_commandes.php
 * Gère les headers CORS et les erreurs PHP
 */

// Capturer toute sortie parasite
if (ob_get_level() === 0) {
    ob_start();
}

// Envoyer les headers CORS immédiatement
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');
}

// Gérer les preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Gestionnaire d'erreurs pour éviter les erreurs HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $level = ob_get_level();
    while ($level > 1) {
        ob_end_clean();
        $level--;
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur PHP: ' . $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit();
}, E_ALL);

// Gestionnaire d'exceptions
set_exception_handler(function($exception) {
    $level = ob_get_level();
    while ($level > 1) {
        ob_end_clean();
        $level--;
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit();
});

?>
