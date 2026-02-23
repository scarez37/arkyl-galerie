<?php
/**
 * PATCH pour api_stripe_checkout.php
 * 
 * Dans ton fichier api_stripe_checkout.php existant sur le serveur,
 * trouve la ligne success_url et remplace-la par ceci :
 * 
 * AVANT :
 *   'success_url' => 'https://ton-site.com/?success=true',
 * 
 * APRÈS :
 *   'success_url' => 'https://arkyl-galerie.onrender.com/?payment=success&session_id={CHECKOUT_SESSION_ID}',
 * 
 * Le {CHECKOUT_SESSION_ID} est un placeholder Stripe — il sera
 * automatiquement remplacé par le vrai session_id au moment du paiement.
 * 
 * ──────────────────────────────────────────────────────────────────
 * Si tu veux remplacer complètement ton api_stripe_checkout.php,
 * voici une version complète compatible :
 */

require_once __DIR__ . '/db_config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Charger Stripe via Composer ou include direct ──────────────────────────
// Option 1 : Composer (si vendor/ présent)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
// Option 2 : Stripe PHP standalone
if (!class_exists('\\Stripe\\Stripe') && file_exists(__DIR__ . '/stripe-php/init.php')) {
    require_once __DIR__ . '/stripe-php/init.php';
}

$STRIPE_SECRET = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_VOTRE_CLE_TEST';
$SITE_URL = 'https://arkyl-galerie.onrender.com';

try {
    $body = json_decode(file_get_contents('php://input'), true);

    $userId      = $body['user_id'] ?? '';
    $cartItems   = $body['cart_items'] ?? [];
    $shippingCost= intval($body['shipping_cost'] ?? 3000);
    $shippingMode= $body['shipping_mode'] ?? 'poste';
    $shippingLabel = $body['shipping_label'] ?? 'Frais de livraison';

    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Panier vide']);
        exit;
    }

    \Stripe\Stripe::setApiKey($STRIPE_SECRET);

    // Récupérer les détails des articles depuis la BDD
    $db = getDatabase();
    $lineItems = [];

    foreach ($cartItems as $item) {
        $artworkId = $item['id'] ?? $item['artwork_id'] ?? null;
        if (!$artworkId) continue;

        $stmt = $db->prepare("SELECT * FROM artworks WHERE id = ?");
        $stmt->execute([$artworkId]);
        $artwork = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$artwork) continue;

        $priceXOF = intval($artwork['price'] ?? 0);
        // Stripe utilise les centimes — pour XOF (monnaie sans décimales), multiplier par 1
        // Note: XOF n'est pas supporté par Stripe — utiliser EUR comme proxy si nécessaire
        // En mode test, on peut utiliser EUR : 1 EUR ≈ 655 XOF
        $priceEUR = max(50, intval($priceXOF / 655 * 100)); // en centimes EUR

        $lineItems[] = [
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $artwork['title'] ?? 'Œuvre',
                    'description' => ($artwork['artist_name'] ?? '') . ' — ARKYL Galerie',
                    'images' => $artwork['image_url'] ? [$artwork['image_url']] : [],
                ],
                'unit_amount' => $priceEUR,
            ],
            'quantity' => intval($item['quantity'] ?? 1),
        ];
    }

    // Frais de livraison
    if ($shippingCost > 0) {
        $lineItems[] = [
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => $shippingLabel],
                'unit_amount' => max(50, intval($shippingCost / 655 * 100)),
            ],
            'quantity' => 1,
        ];
    }

    // Créer la session Stripe Checkout
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'line_items' => $lineItems,
        // {CHECKOUT_SESSION_ID} est remplacé automatiquement par Stripe
        'success_url' => $SITE_URL . '/?payment=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $SITE_URL . '/?payment=cancelled',
        'metadata' => [
            'user_id'      => $userId,
            'shipping_mode'=> $shippingMode,
        ],
    ]);

    echo json_encode([
        'success'    => true,
        'url'        => $session->url,
        'session_id' => $session->id,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
