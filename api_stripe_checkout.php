<?php
// ==================== API STRIPE CHECKOUT ====================
// FIX : clés Stripe lues depuis variables d'environnement Render
// FIX : success_url utilise l'URL absolue du serveur (pas de chemin relatif)
// FIX : adresse de livraison ajoutée dans les metadata Stripe

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cross-Origin-Embedder-Policy: unsafe-none');
header('Cross-Origin-Opener-Policy: unsafe-none');
header('Cross-Origin-Resource-Policy: cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

require_once __DIR__ . '/db_config.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo json_encode(['success' => false, 'message' => 'vendor/autoload.php manquant — lancez: composer require stripe/stripe-php']);
    exit;
}

// 🔑 Clé secrète Stripe depuis variable d'environnement (fallback clé de test)
$stripeKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ';
\Stripe\Stripe::setApiKey($stripeKey);

// 🌐 URL de base du serveur (pour les redirections Stripe)
$baseUrl = getenv('APP_URL') ?: 'https://arkyl-galerie-nvwn.onrender.com';

try {
    $db = getDatabase();

    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    $user_id         = $data['user_id']        ?? '';
    $cart_fallback   = $data['cart_items']     ?? [];
    $shipping_cost   = intval($data['shipping_cost']  ?? 3000);
    $shipping_mode   = $data['shipping_mode']          ?? 'poste';
    $shipping_label  = $data['shipping_label']         ?? 'Frais de livraison';
    // ✅ FIX : récupérer l'adresse de livraison envoyée depuis le front
    $shipping_address = $data['shipping_address'] ?? '';

    if (empty($user_id)) {
        throw new Exception("Identifiant utilisateur manquant.");
    }

    // ÉTAPE 1 — Panier depuis PostgreSQL
    $stmt = $db->prepare("
        SELECT c.quantity, c.artwork_id, a.title, a.price, a.image_url, a.artist_id
        FROM cart c
        INNER JOIN artworks a ON c.artwork_id = a.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ÉTAPE 2 — Fallback localStorage
    if (empty($cartItems) && !empty($cart_fallback)) {
        $upsertStmt = $db->prepare("
            INSERT INTO cart (user_id, artwork_id, quantity)
            VALUES (:user_id, :artwork_id, :quantity)
            ON CONFLICT (user_id, artwork_id)
            DO UPDATE SET quantity = EXCLUDED.quantity
        ");

        $validItems = [];
        foreach ($cart_fallback as $fbItem) {
            $artwork_id = intval($fbItem['id']       ?? 0);
            $quantity   = intval($fbItem['quantity'] ?? 1);
            if ($artwork_id <= 0) continue;

            $checkStmt = $db->prepare("SELECT id, title, price, artist_id FROM artworks WHERE id = :id");
            $checkStmt->execute([':id' => $artwork_id]);
            $artwork = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($artwork) {
                $upsertStmt->execute([
                    ':user_id'    => $user_id,
                    ':artwork_id' => $artwork_id,
                    ':quantity'   => $quantity
                ]);
                $validItems[] = [
                    'artwork_id' => $artwork_id,
                    'title'      => $artwork['title'],
                    'price'      => $artwork['price'],
                    'quantity'   => $quantity,
                    'artist_id'  => $artwork['artist_id']
                ];
            }
        }

        if (empty($validItems)) {
            throw new Exception("Aucun article valide trouvé dans le panier.");
        }
        $cartItems = $validItems;
    }

    if (empty($cartItems)) {
        throw new Exception("Le panier est vide. Ajoutez des œuvres avant de passer commande.");
    }

    // ÉTAPE 3 — Construire les line_items Stripe
    define('FCFA_TO_EUR', 655.957);

    $line_items = [];
    foreach ($cartItems as $item) {
        $price_fcfa      = floatval($item['price']);
        $price_eur_cents = intval(round(($price_fcfa / FCFA_TO_EUR) * 100));
        if ($price_eur_cents < 50) $price_eur_cents = 50;

        $line_items[] = [
            'price_data' => [
                'currency'     => 'eur',
                'product_data' => [
                    'name'        => $item['title'],
                    'description' => 'Galerie ARKYL — Prix original : ' . number_format($price_fcfa, 0, ',', ' ') . ' FCFA',
                ],
                'unit_amount'  => $price_eur_cents,
            ],
            'quantity' => intval($item['quantity']),
        ];
    }

    // ÉTAPE 3b — Frais de livraison
    if ($shipping_cost > 0) {
        $shipping_eur_cents = intval(round(($shipping_cost / FCFA_TO_EUR) * 100));
        if ($shipping_eur_cents < 1) $shipping_eur_cents = 1;

        $line_items[] = [
            'price_data' => [
                'currency'     => 'eur',
                'product_data' => [
                    'name'        => $shipping_label,
                    'description' => 'Frais de livraison — ' . number_format($shipping_cost, 0, ',', ' ') . ' FCFA',
                ],
                'unit_amount'  => $shipping_eur_cents,
            ],
            'quantity' => 1,
        ];
    }

    // ÉTAPE 3.5 — Calcul commission
    $total_artworks_fcfa = 0;
    $first_artist_id     = null;

    foreach ($cartItems as $item) {
        $total_artworks_fcfa += (floatval($item['price']) * intval($item['quantity']));
        if (!$first_artist_id && isset($item['artist_id'])) {
            $first_artist_id = $item['artist_id'];
        }
    }

    $commission_amount = $total_artworks_fcfa * 0.35;
    $artist_payout     = $total_artworks_fcfa * 0.65;

    // ÉTAPE 4 — Créer la session Stripe
    $order_id = 'ARKYL-' . strtoupper(substr(md5($user_id . microtime()), 0, 8));

    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'client_reference_id'  => $user_id,
        'metadata'             => [
            'order_id'          => $order_id,
            'user_id'           => $user_id,
            'artist_id'         => $first_artist_id,
            'shipping_cost'     => $shipping_cost,
            'shipping_mode'     => $shipping_mode,
            // ✅ FIX : adresse de livraison transmise à Stripe → disponible dans le webhook
            'shipping_address'  => mb_substr($shipping_address, 0, 500), // Stripe limite à 500 chars
            'commission_amount' => $commission_amount,
            'artist_payout'     => $artist_payout,
        ],
        // ✅ FIX : URL absolue (plus de chemin relatif)
        'success_url' => $baseUrl . '/index.php?order_id=' . $order_id . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $baseUrl . '/index.php',
    ]);

    echo json_encode([
        'success'    => true,
        'url'        => $checkout_session->url,
        'order_id'   => $order_id,
        'session_id' => $checkout_session->id,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
