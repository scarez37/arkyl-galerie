<?php
// ==================== WEBHOOK STRIPE ====================
// Stripe appelle ce fichier silencieusement après chaque paiement réussi.
// C'est lui qui CRÉE la commande en base et vide le panier.
// ⚠️  Ne jamais appeler ce fichier manuellement — uniquement via Stripe.

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey('');

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

// 🆕 RÉCUPÉRATION DE TOUTES LES METADATA
$order_id          = $session->metadata->order_id          ?? '';
$user_id           = $session->metadata->user_id           ?? $session->client_reference_id ?? '';
$user_name         = $session->metadata->user_name         ?? ($session->customer_details->name ?? '');
$user_email        = $session->metadata->user_email        ?? ($session->customer_details->email ?? $session->customer_email ?? '');
$artist_id         = $session->metadata->artist_id         ?? null;
$shipping_cost     = floatval($session->metadata->shipping_cost     ?? 3000);
$shipping_mode     = $session->metadata->shipping_mode     ?? 'La Poste';
$shipping_address  = $session->metadata->shipping_address  ?? '';
$commission_amount = floatval($session->metadata->commission_amount ?? 0); // Part ARKYL (35%)
$artist_payout     = floatval($session->metadata->artist_payout     ?? 0); // Part artiste (65%)

$payment_method = 'Carte bancaire (Stripe)';

if (empty($order_id) || empty($user_id)) {
    error_log("⚠️ Webhook ARKYL — order_id ou user_id manquant. Session ID : " . $session->id);
    http_response_code(200);
    exit();
}

try {
    $db = getDatabase();

    // ─────────────────────────────────────────────────────────────────
    // MIGRATION SAFE — Ajouter les colonnes manquantes si nécessaire
    // ─────────────────────────────────────────────────────────────────
    $migrations = [
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS commission_amount NUMERIC(12,2) DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_payout NUMERIC(12,2) DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_name VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_email VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS sold_at TIMESTAMPTZ",
        "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* colonne déjà présente */ }
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Vérifier l'idempotence (éviter les doublons)
    // ─────────────────────────────────────────────────────────────────
    $checkStmt = $db->prepare("SELECT id FROM orders WHERE order_number = :order_number LIMIT 1");
    $checkStmt->execute([':order_number' => $order_id]);
    if ($checkStmt->fetch()) {
        error_log("ℹ️ Webhook ARKYL — Commande $order_id déjà traitée, ignorée (idempotence)");
        http_response_code(200);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 4 — Récupérer le contenu du panier + artist_id de chaque œuvre
    // ─────────────────────────────────────────────────────────────────
    $cartStmt = $db->prepare("
        SELECT c.quantity, c.artwork_id,
               a.title, a.price, a.image_url, a.artist_name,
               a.artist_id
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
    // ÉTAPE 5 — Calculer les totaux
    // ─────────────────────────────────────────────────────────────────
    $subtotal_fcfa = 0;
    foreach ($cartItems as $item) {
        $subtotal_fcfa += floatval($item['price']) * intval($item['quantity']);
    }

    $tva_rate   = 0.18;
    $tax_fcfa   = round($subtotal_fcfa * $tva_rate);
    $total_fcfa = $subtotal_fcfa + $shipping_cost;

    $auto_release_date = (new DateTime())->modify('+21 days')->format('Y-m-d H:i:s');

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 6 — Créer la commande + items + marquer vendues + vider panier
    // Répartition : Commission ARKYL 35% | Artiste 65% | Port non taxé
    // ─────────────────────────────────────────────────────────────────
    $db->beginTransaction();

    // 6a. Insérer la commande
    $insertOrder = $db->prepare("
        INSERT INTO orders (
            order_number, user_id, user_name, user_email,
            artist_id, status, escrow_status, escrow_auto_release_date,
            subtotal, tax, shipping_cost, shipping_name, shipping_address,
            payment_method, total,
            commission_amount, artist_payout,
            stripe_session_id, created_at
        ) VALUES (
            :order_number, :user_id, :user_name, :user_email,
            :artist_id, 'En préparation', 'payée_en_attente', :auto_release_date,
            :subtotal, :tax, :shipping_cost, :shipping_name, :shipping_address,
            :payment_method, :total,
            :commission_amount, :artist_payout,
            :stripe_session_id, CURRENT_TIMESTAMP
        )
        RETURNING id
    ");

    $insertOrder->execute([
        ':order_number'      => $order_id,
        ':user_id'           => $user_id,
        ':user_name'         => $user_name,
        ':user_email'        => $user_email,
        ':artist_id'         => $artist_id,
        ':auto_release_date' => $auto_release_date,
        ':subtotal'          => $subtotal_fcfa,
        ':tax'               => $tax_fcfa,
        ':shipping_cost'     => $shipping_cost,
        ':shipping_name'     => $shipping_mode,
        ':shipping_address'  => $shipping_address,
        ':payment_method'    => $payment_method,
        ':total'             => $total_fcfa,
        ':commission_amount' => $commission_amount, // 35% — calculé dans api_stripe_checkout.php
        ':artist_payout'     => $artist_payout,     // 65% — calculé dans api_stripe_checkout.php
        ':stripe_session_id' => $session->id,
    ]);

    $new_order_db_id = $insertOrder->fetchColumn();

    // 6b. Insérer chaque article avec son artist_id
    $insertItem = $db->prepare("
        INSERT INTO order_items
            (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
        VALUES
            (:order_id, :artwork_id, :title, :artist_name, :artist_id, :price, :quantity, :image_url)
    ");

    foreach ($cartItems as $item) {
        $insertItem->execute([
            ':order_id'    => $new_order_db_id,
            ':artwork_id'  => $item['artwork_id'],
            ':title'       => $item['title'],
            ':artist_name' => $item['artist_name'] ?? '',
            ':artist_id'   => (string)($item['artist_id'] ?? ''),
            ':price'       => $item['price'],
            ':quantity'    => $item['quantity'],
            ':image_url'   => $item['image_url'] ?? '',
        ]);
    }

    // 6c. Marquer les œuvres comme vendues (is_sold + sold_at)
    $markSold = $db->prepare("
        UPDATE artworks SET is_sold = TRUE, sold_at = NOW() WHERE id = :artwork_id
    ");
    foreach ($cartItems as $item) {
        $markSold->execute([':artwork_id' => $item['artwork_id']]);
    }

    // 6d. Créer l'entrée dans order_timeline
    $db->prepare("
        INSERT INTO order_timeline (order_id, status, note, updated_by_role)
        VALUES (:order_id, 'En préparation', 'Commande créée — paiement Stripe validé', 'system')
    ")->execute([':order_id' => $new_order_db_id]);

    // 6e. Vider le panier
    $db->prepare("DELETE FROM cart WHERE user_id = :user_id")
       ->execute([':user_id' => $user_id]);

    $db->commit();

    error_log("✅ Webhook ARKYL — Commande $order_id créée (db_id: $new_order_db_id) | "
        . count($cartItems) . " article(s) | "
        . "Commission ARKYL (35%) : {$commission_amount} FCFA | "
        . "Reversement artiste (65%) : {$artist_payout} FCFA | "
        . "Livraison (non taxée) : {$shipping_cost} FCFA");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
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
