<?php
// ==================== WEBHOOK STRIPE ====================
// Stripe appelle ce fichier silencieusement après chaque paiement réussi.
// C'est lui qui CRÉE la commande en base et vide le panier.
// ⚠️  Ne jamais appeler ce fichier manuellement — uniquement via Stripe.

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey('après je vais mettre');

// 🔐 Secret webhook — à copier depuis ton tableau de bord Stripe
$endpoint_secret = 'whsec_yjPEMxUgwPmuDWvS48z4fFQz7PpqcLaP';

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

// 🆕 RÉCUPÉRATION DE TOUTES LES METADATA CRÉÉES DANS L'API CHECKOUT
$order_id          = $session->metadata->order_id ?? '';
$user_id           = $session->metadata->user_id ?? $session->client_reference_id ?? '';
$artist_id         = $session->metadata->artist_id ?? null;
$shipping_cost     = $session->metadata->shipping_cost ?? 3000;
$shipping_mode     = $session->metadata->shipping_mode ?? 'La Poste';
$commission_amount = $session->metadata->commission_amount ?? 0; // Ta part (31%)
$artist_payout     = $session->metadata->artist_payout ?? 0;     // La part de l'artiste (69%)

$payment_method = 'Carte bancaire (Stripe)';

if (empty($order_id) || empty($user_id)) {
    error_log("⚠️ Webhook ARKYL — order_id ou user_id manquant. Session ID : " . $session->id);
    http_response_code(200); 
    exit();
}

try {
    $db = getDatabase();

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Vérifier l'idempotence (éviter les doublons)
    // ─────────────────────────────────────────────────────────────────
    $checkStmt = $db->prepare("SELECT id FROM orders WHERE order_number = :order_number LIMIT 1");
    $checkStmt->execute([':order_number' => $order_id]);
    if ($checkStmt->fetch()) {
        http_response_code(200);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 4 — Récupérer le contenu du panier
    // ─────────────────────────────────────────────────────────────────
    $cartStmt = $db->prepare("
        SELECT c.quantity, c.artwork_id, a.title, a.price, a.image_url, a.artist_name
        FROM cart c
        INNER JOIN artworks a ON c.artwork_id = a.id
        WHERE c.user_id = :user_id
    ");
    $cartStmt->execute([':user_id' => $user_id]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        error_log("⚠️ Webhook ARKYL — Panier vide pour user_id: $user_id, order_id: $order_id");
        http_response_code(200);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 5 — Calculer les totaux de base
    // ─────────────────────────────────────────────────────────────────
    $subtotal_fcfa = 0;
    foreach ($cartItems as $item) {
        $subtotal_fcfa += floatval($item['price']) * intval($item['quantity']);
    }

    $tva_rate      = 0.18; 
    $tax_fcfa      = round($subtotal_fcfa * $tva_rate);
    $total_fcfa    = $subtotal_fcfa + $shipping_cost; 

    $delivery_date = new DateTime();
    $delivery_date->modify('+21 days');
    $auto_release_date = $delivery_date->format('Y-m-d H:i:s');

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 6 — Créer la commande en base avec la COMMISSION (31%)
    // ─────────────────────────────────────────────────────────────────
    $db->beginTransaction();

    // 🆕 AJOUT : artist_id, commission_amount et artist_payout dans la requête
    $insertOrder = $db->prepare("
        INSERT INTO orders (
            order_number,
            user_id,
            artist_id,
            status,
            escrow_status,
            escrow_auto_release_date,
            subtotal,
            tax,
            shipping,
            shipping_name,
            payment_method,
            total,
            commission_amount,
            artist_payout,
            stripe_session_id,
            created_at
        ) VALUES (
            :order_number,
            :user_id,
            :artist_id,
            'En préparation',
            'payée_en_attente',
            :auto_release_date,
            :subtotal,
            :tax,
            :shipping,
            :shipping_name,
            :payment_method,
            :total,
            :commission_amount,
            :artist_payout,
            :stripe_session_id,
            CURRENT_TIMESTAMP
        )
        RETURNING id
    ");

    $insertOrder->execute([
        ':order_number'       => $order_id,
        ':user_id'            => $user_id,
        ':artist_id'          => $artist_id,
        ':auto_release_date'  => $auto_release_date,
        ':subtotal'           => $subtotal_fcfa,
        ':tax'                => $tax_fcfa,
        ':shipping'           => $shipping_cost,
        ':shipping_name'      => $shipping_mode,
        ':payment_method'     => $payment_method,
        ':total'              => $total_fcfa,
        ':commission_amount'  => $commission_amount, // 🆕 On sauvegarde ta part
        ':artist_payout'      => $artist_payout,     // 🆕 On sauvegarde l'argent bloqué de l'artiste
        ':stripe_session_id'  => $session->id,
    ]);

    $newOrderRow = $insertOrder->fetch(PDO::FETCH_ASSOC);
    $new_order_db_id = $newOrderRow['id'];

    // 6b. Insérer chaque article de la commande
    $insertItem = $db->prepare("
        INSERT INTO order_items (order_id, artwork_id, title, artist_name, price, quantity, image_url)
        VALUES (:order_id, :artwork_id, :title, :artist_name, :price, :quantity, :image_url)
    ");

    foreach ($cartItems as $item) {
        $insertItem->execute([
            ':order_id'    => $new_order_db_id,
            ':artwork_id'  => $item['artwork_id'],
            ':title'       => $item['title'],
            ':artist_name' => $item['artist_name'] ?? '',
            ':price'       => $item['price'],
            ':quantity'    => $item['quantity'],
            ':image_url'   => $item['image_url'] ?? '',
        ]);
    }

    // 6c. Marquer les œuvres comme vendues
    $markSold = $db->prepare("UPDATE artworks SET is_sold = TRUE WHERE id = :artwork_id");
    foreach ($cartItems as $item) {
        $markSold->execute([':artwork_id' => $item['artwork_id']]);
    }

    // 6d. Vider le panier
    $deleteCart = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
    $deleteCart->execute([':user_id' => $user_id]);

    $db->commit();

    error_log("✅ Webhook ARKYL — Commande $order_id créée. Commission ARKYL: $commission_amount, Artiste: $artist_payout");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("❌ Webhook ARKYL — Erreur BDD : " . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 7 — Toujours répondre 200 à Stripe
// ─────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);
?>
