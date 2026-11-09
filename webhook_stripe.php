<?php
// ==================== WEBHOOK STRIPE ====================
// Stripe appelle ce fichier silencieusement après chaque paiement réussi.
// C'est lui qui CRÉE la commande en base et vide le panier.
// ⚠️  Ne jamais appeler ce fichier manuellement — uniquement via Stripe.

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/notify_helpers.php';
require_once __DIR__ . '/stripe_order_helper.php';   // logique de commande partagée (+ bootstrap.php)

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ');

// 🔐 Secret webhook — à copier depuis ton tableau de bord Stripe
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_yjPEMxUgwPmuDWvS48z4fFQz7PpqcLaP';

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 1 — Lire et vérifier la signature cryptographique de Stripe
// ─────────────────────────────────────────────────────────────────
$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 2 — On ne traite que l'événement "paiement réussi"
// ─────────────────────────────────────────────────────────────────
if ($event->type !== 'checkout.session.completed') {
    http_response_code(200);
    exit();
}

$session = $event->data->object;

try {
    $db  = getDatabase();
    $res = arkylCreateOrderFromSession($db, $session);
    error_log('ARKYL webhook : ' . json_encode($res));
} catch (Throwable $e) {
    error_log('❌ Webhook ARKYL — erreur : ' . $e->getMessage());
}

// Toujours répondre 200 à Stripe (sinon il rejoue l'événement en boucle)
http_response_code(200);
echo json_encode(['received' => true]);
