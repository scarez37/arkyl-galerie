<?php
// ==================== API STRIPE CHECKOUT ====================
// Ce fichier prépare le panier et redirige le client vers le paiement sécurisé Stripe

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

// Capturer toutes les erreurs PHP et les retourner en JSON propre
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

require_once __DIR__ . '/db_config.php';

// Charger Stripe — Composer ou fallback
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo json_encode(['success' => false, 'message' => 'vendor/autoload.php manquant — lancez: composer require stripe/stripe-php']);
    exit;
}

// 🔑 Clé secrète Stripe (test)
\Stripe\Stripe::setApiKey('sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ'');

try {
    $db = getDatabase();

    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    $user_id        = $data['user_id']        ?? '';
    // Le front peut envoyer le panier localStorage en secours
    $cart_fallback  = $data['cart_items']     ?? [];
    // Frais de livraison envoyés depuis le panier JS
    $shipping_cost  = intval($data['shipping_cost']  ?? 3000);  // FCFA
    $shipping_mode  = $data['shipping_mode']          ?? 'poste';
    $shipping_label = $data['shipping_label']         ?? 'Frais de livraison';

    if (empty($user_id)) {
        throw new Exception("Identifiant utilisateur manquant.");
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 1 — Chercher le panier en base PostgreSQL (source de vérité)
    // ─────────────────────────────────────────────────────────────────
    // 🆕 AJOUT : a.artist_id pour savoir à qui appartient l'œuvre
    $stmt = $db->prepare("
        SELECT c.quantity, c.artwork_id, a.title, a.price, a.image_url, a.artist_id
        FROM cart c
        INNER JOIN artworks a ON c.artwork_id = a.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 2 — Fallback : si la BDD est vide, on utilise le panier
    //           envoyé depuis localStorage et on resynchronise la BDD
    // ─────────────────────────────────────────────────────────────────
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

            // 🆕 AJOUT : artist_id dans la vérification de sécurité
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
                    'artist_id'  => $artwork['artist_id'] // 🆕 Sauvegarde de l'ID artiste
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

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Calculer le total en FCFA et convertir pour Stripe
    // ─────────────────────────────────────────────────────────────────
    define('FCFA_TO_EUR', 655.957);

    $line_items = [];
    foreach ($cartItems as $item) {
        $price_fcfa = floatval($item['price']);
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

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3b — Ajouter les frais de livraison
    // ─────────────────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3.5 — 💰 CALCUL DE LA NOUVELLE COMMISSION (35%)
    // ─────────────────────────────────────────────────────────────────
    $total_artworks_fcfa = 0;
    $first_artist_id = null;

    foreach ($cartItems as $item) {
        $total_artworks_fcfa += (floatval($item['price']) * intval($item['quantity']));
        if (!$first_artist_id && isset($item['artist_id'])) {
            $first_artist_id = $item['artist_id'];
        }
    }

    // Nouvelle répartition : 35% / 65%
    $commission_rate   = 0.35;
    $commission_amount = $total_artworks_fcfa * $commission_rate; // Part du site (35%)
    $artist_payout     = $total_artworks_fcfa * 0.65;             // Part de l'artiste (65%)
    // ⚠️ Les frais de port ($shipping_cost) ne sont PAS inclus dans la base
    // de calcul — ils sont reversés intégralement à l'artiste/transporteur.

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 4 — Générer la session Stripe avec le "sac à dos" (metadata)
    // ─────────────────────────────────────────────────────────────────
    $order_id = 'ARKYL-' . strtoupper(substr(md5($user_id . microtime()), 0, 8));

    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'client_reference_id'  => $user_id,
        'metadata'             => [
            'order_id'          => $order_id,
            'user_id'           => $user_id,
            'artist_id'         => $first_artist_id,   // 🆕 ID de l'artiste
            'shipping_cost'     => $shipping_cost,
            'shipping_mode'     => $shipping_mode,
            'commission_amount' => $commission_amount, // Part ARKYL (35%)
            'artist_payout'     => $artist_payout,     // Part Artiste (65%)
        ],
        // 🆕 IMPORTANT : Modification en index.php au lieu de index.html
        'success_url' => 'https://arkyl-galerie.onrender.com/index.php?order_id=' . $order_id . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'https://arkyl-galerie.onrender.com/index.php',
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
