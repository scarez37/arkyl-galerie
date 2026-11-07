<?php
// ===================================================================
// bootstrap.php — à inclure en TOUTE PREMIÈRE ligne de chaque endpoint API.
//  • CORS restreint aux origines autorisées (plus de « * »)
//  • Réponse immédiate aux pré-requêtes OPTIONS
//  • Aucune erreur PHP / SQL / chemin de fichier n'est renvoyée au client
//  • JSON par défaut
//
// Variables d'environnement :
//   ALLOWED_ORIGINS  liste séparée par des virgules (ex. https://arkyl.site,https://www.arkyl.site)
//   APP_URL          URL publique du site (ajoutée automatiquement aux origines autorisées)
//   ARKYL_DEV=1      autorise http://localhost:* (développement uniquement)
// ===================================================================

if (defined('ARKYL_BOOTSTRAP')) {
    return;
}
define('ARKYL_BOOTSTRAP', true);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (ob_get_level() === 0) {
    ob_start();
}

/** Liste des origines autorisées à lire les réponses de l'API depuis un navigateur. */
function arkylAllowedOrigins(): array {
    $list = ['https://arkyl.site', 'https://www.arkyl.site'];
    foreach (['APP_URL', 'FRONTEND_URL'] as $var) {
        $v = getenv($var);
        if ($v) {
            $list[] = rtrim($v, '/');
        }
    }
    $extra = getenv('ALLOWED_ORIGINS');
    if ($extra) {
        foreach (explode(',', $extra) as $o) {
            $o = rtrim(trim($o), '/');
            if ($o !== '') {
                $list[] = $o;
            }
        }
    }
    return array_values(array_unique($list));
}

function arkylOriginAllowed(string $origin): bool {
    if ($origin === '') {
        return false;
    }
    $allowed = arkylAllowedOrigins();
    if (in_array('*', $allowed, true) || in_array($origin, $allowed, true)) {
        return true;
    }
    // Même hôte que l'API (appel same-origin)
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '' && preg_match('#^https?://' . preg_quote($host, '#') . '$#i', $origin)) {
        return true;
    }
    if (getenv('ARKYL_DEV') === '1' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
        return true;
    }
    return false;
}

function arkylSendCors(): void {
    if (headers_sent()) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Vary: Origin');
    if ($origin !== '' && arkylOriginAllowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Arkyl-Token, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
    header('Cross-Origin-Resource-Policy: cross-origin');
    header('X-Content-Type-Options: nosniff');
}

/** Réponse JSON d'erreur + arrêt. */
function arkylJsonError(int $status, string $message, array $extra = []): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        arkylSendCors();
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    echo json_encode(array_merge(['success' => false, 'message' => $message, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Message d'erreur présentable à l'utilisateur.
 * Les erreurs techniques (SQL, PHP, connexion) sont journalisées et remplacées
 * par un texte générique ; les erreurs « métier » (Exception simple) sont conservées.
 */
function arkylSafeError(Throwable $e): string {
    if ($e instanceof PDOException || $e instanceof Error || $e instanceof ErrorException) {
        error_log('ARKYL erreur technique : ' . get_class($e) . ' — ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        return 'Erreur serveur. Réessayez dans un instant.';
    }
    return $e->getMessage();
}

// ── CORS + pré-requêtes ───────────────────────────────────────────────
arkylSendCors();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit;
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// ── Gestion d'erreurs : on journalise, on ne divulgue rien ───────────
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("ARKYL PHP[$errno] $errstr @ " . basename($errfile) . ":$errline");
    return true; // géré : rien n'est affiché
});

set_exception_handler(function (Throwable $e) {
    error_log('ARKYL exception non gérée : ' . get_class($e) . ' — ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    arkylJsonError(500, 'Erreur serveur. Réessayez dans un instant.');
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('ARKYL fatal : ' . $err['message'] . ' @ ' . basename($err['file']) . ':' . $err['line']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            arkylSendCors();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'message' => 'Erreur serveur.', 'error' => 'Erreur serveur.']);
    }
});
