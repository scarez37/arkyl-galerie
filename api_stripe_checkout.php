<?php
// ==================== API STRIPE CHECKOUT ====================
// Ce fichier prépare le panier et redirige le client vers le paiement sécurisé Stripe

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
\Stripe\Stripe::setApiKey('je vais mettre après');

try {
    $db = getDatabase();

    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    $user_id        = $data['user_id']        ?? '';
    // 🆕 Le front peut envoyer le panier localStorage en secours
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
    $stmt = $db->prepare("
        SELECT c.quantity, c.artwork_id, a.title, a.price, a.image_url
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

        // Resynchroniser chaque article dans la table cart
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

            // Vérifier que l'œuvre existe vraiment en base (sécurité anti-falsification)
            $checkStmt = $db->prepare("SELECT id, title, price FROM artworks WHERE id = :id");
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
                    'price'      => $artwork['price'], // ✅ Prix de la BDD, jamais du front
                    'quantity'   => $quantity
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
    //
    // ⚠️  Stripe ne supporte pas XOF (FCFA) nativement.
    //     Solution retenue : on affiche les prix en EUR en divisant par
    //     le taux de change fixe CFA → EUR (1 EUR ≈ 655.957 FCFA).
    //     Les montants restent EXACTS côté ARKYL.
    // ─────────────────────────────────────────────────────────────────
    define('FCFA_TO_EUR', 655.957);

    $line_items = [];
    foreach ($cartItems as $item) {
        $price_fcfa = floatval($item['price']);
        $price_eur_cents = intval(round(($price_fcfa / FCFA_TO_EUR) * 100));

        // Sécurité : Stripe exige un minimum de 50 centimes
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
    // ÉTAPE 3b — Ajouter les frais de livraison comme ligne Stripe
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
    // ÉTAPE 4 — Générer un order_id unique et le passer à Stripe
    //           pour pouvoir retrouver la commande dans le webhook
    // ─────────────────────────────────────────────────────────────────
    $order_id = 'ARKYL-' . strtoupper(substr(md5($user_id . microtime()), 0, 8));

    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'client_reference_id'  => $user_id,  // 🆕 Stripe garde la trace du client
        'metadata'             => [           // 🆕 Données custom récupérables dans le webhook
            'order_id'      => $order_id,
            'user_id'       => $user_id,
            'shipping_cost' => $shipping_cost,
            'shipping_mode' => $shipping_mode,
        ],
        // ✅ On passe l'order_id dans l'URL de succès pour afficher la confirmation
        'success_url' => 'https://arkyl-galerie.onrender.com/index.html?order_id=' . $order_id . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'https://arkyl-galerie.onrender.com/index.html',
    ]);

    echo json_encode([
        'success'    => true,
        'url'        => $checkout_session->url,
        'order_id'   => $order_id,  // 🆕 Renvoyé au front pour référence
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
