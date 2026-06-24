<?php
/**
 * cors_helper.php
 * Inclure en TOUTE PREMIERE ligne de chaque API PHP.
 * Garantit les headers CORS meme en cas de fatal error PHP.
 */
if (!function_exists('sendCorsHeaders')) {

    ob_start();

    function sendCorsHeaders() {
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Credentials: true');
            header('Cross-Origin-Embedder-Policy: unsafe-none');
            header('Cross-Origin-Opener-Policy: unsafe-none');
            header('Cross-Origin-Resource-Policy: cross-origin');
        }
    }

    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            ob_end_clean();
            sendCorsHeaders();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $error['message']]);
        } else {
            ob_end_flush();
        }
    });

    sendCorsHeaders();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        ob_end_clean();
        exit();
    }
}
?>
