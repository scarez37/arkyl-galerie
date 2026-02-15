<?php
// ==================== SCRIPT DE DIAGNOSTIC ====================
// Ce fichier va chercher où est galerie.db sur ton serveur

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$possiblePaths = [
    '/opt/render/project/src/galerie.db',
    __DIR__ . '/galerie.db',
    '/var/data/galerie.db',
    '/tmp/galerie.db',
    getcwd() . '/galerie.db',
    dirname(__FILE__) . '/galerie.db'
];

$results = [];
foreach ($possiblePaths as $path) {
    $results[$path] = [
        'exists' => file_exists($path),
        'readable' => file_exists($path) && is_readable($path),
        'writable' => file_exists($path) && is_writable($path)
    ];
}

echo json_encode([
    'current_dir' => __DIR__,
    'getcwd' => getcwd(),
    'paths_tested' => $results,
    'found_db' => array_filter($results, function($r) { return $r['exists']; })
], JSON_PRETTY_PRINT);
?>
