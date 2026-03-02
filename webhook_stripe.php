<?php
// ==================== WEBHOOK STRIPE ====================
// Stripe appelle ce fichier silencieusement après chaque paiement réussi.
// C'est lui qui CRÉE la commande en base et vide le panier.
// ⚠️  Ne jamais appeler ce fichier manuellement — uniquement via Stripe.

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey('je vais mêttre après');

// 🔐 Secret webhook — à copier depuis ton tableau de bord Stripe
// Tableau de bord Stripe → Développeurs → Webhooks → Révéler le secret
$endpoint_secret = 'whsec_yjPEMxUgwPmuDWvS48z4fFQz7PpqcLaP';

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 1 — Lire et vérifier la signature cryptographique de Stripe
// Sans ça, n'importe qui pourrait simuler un faux paiement réussi !
// ─────────────────────────────────────────────────────────────────
$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException $e) {
    // Payload illisible
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Signature invalide — tentative de fraude bloquée
    http_response_code(400);
    exit();
}

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 2 — On ne traite que l'événement "paiement réussi"
// ─────────────────────────────────────────────────────────────────
if ($event->type !== 'checkout.session.completed') {
    http_response_code(200); // On ignore les autres événements sans erreur
    exit();
}

$session = $event->data->object;

// Récupérer les données passées depuis api_stripe_checkout.php
$order_id = $session->metadata->order_id        ?? '';
$user_id  = $session->client_reference_id       ?? '';
$payment_method = 'Carte bancaire (Stripe)';

// Sécurité : on ne fait rien si les données essentielles manquent
if (empty($order_id) || empty($user_id)) {
    error_log("⚠️ Webhook ARKYL — order_id ou user_id manquant. Session ID : " . $session->id);
    http_response_code(200); // On répond 200 quand même pour que Stripe ne renvoie pas
    exit();
}

try {
    $db = getDatabase();

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Vérifier si cette commande n'a pas déjà été traitée
    // (Stripe peut envoyer le même webhook plusieurs fois — idempotence)
    // ─────────────────────────────────────────────────────────────────
    $checkStmt = $db->prepare("SELECT id FROM orders WHERE order_number = :order_number LIMIT 1");
    $checkStmt->execute([':order_number' => $order_id]);
    if ($checkStmt->fetch()) {
        // Commande déjà créée — on répond OK sans rien faire
        http_response_code(200);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 4 — Récupérer le contenu du panier depuis la BDD
    // C'est la source de vérité — jamais les données du front
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
    // ÉTAPE 5 — Calculer les montants (FCFA)
    // ─────────────────────────────────────────────────────────────────
    define('FCFA_TO_EUR', 655.957);
    $stripe_amount_eur = $session->amount_total / 100; // Stripe renvoie en centimes

    // On reconvertit le montant Stripe en FCFA pour garder la cohérence
    $total_fcfa    = round($stripe_amount_eur * FCFA_TO_EUR);
    $shipping_fcfa = 3000; // Valeur par défaut (La Poste)
    $subtotal_fcfa = 0;

    foreach ($cartItems as $item) {
        $subtotal_fcfa += floatval($item['price']) * intval($item['quantity']);
    }

    $tva_rate      = 0.18; // 18% TVA Côte d'Ivoire
    $tax_fcfa      = round($subtotal_fcfa * $tva_rate);

    // Date de livraison estimée (21 jours ouvrés par défaut)
    $delivery_date = new DateTime();
    $delivery_date->modify('+21 days');
    $auto_release_date = $delivery_date->format('Y-m-d H:i:s');

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 6 — Créer la commande en base PostgreSQL
    // ─────────────────────────────────────────────────────────────────
    $db->beginTransaction();

    // 6a. Insérer la commande principale
    $insertOrder = $db->prepare("
        INSERT INTO orders (
            order_number,
            user_id,
            status,
            escrow_status,
            escrow_auto_release_date,
            subtotal,
            tax,
            shipping,
            shipping_name,
            payment_method,
            total,
            stripe_session_id,
            created_at
        ) VALUES (
            :order_number,
            :user_id,
            'En préparation',
            'payée_en_attente',
            :auto_release_date,
            :subtotal,
            :tax,
            :shipping,
            'La Poste',
            :payment_method,
            :total,
            :stripe_session_id,
            CURRENT_TIMESTAMP
        )
        RETURNING id
    ");

    $insertOrder->execute([
        ':order_number'       => $order_id,
        ':user_id'            => $user_id,
        ':auto_release_date'  => $auto_release_date,
        ':subtotal'           => $subtotal_fcfa,
        ':tax'                => $tax_fcfa,
        ':shipping'           => $shipping_fcfa,
        ':payment_method'     => $payment_method,
        ':total'              => $total_fcfa,
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

    // 6d. Vider le panier de l'utilisateur
    $deleteCart = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
    $deleteCart->execute([':user_id' => $user_id]);

    $db->commit();

    error_log("✅ Webhook ARKYL — Commande créée : $order_id pour user $user_id (total: $total_fcfa FCFA)");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // On log l'erreur mais on répond 200 à Stripe pour qu'il ne spamme pas le webhook
    error_log("❌ Webhook ARKYL — Erreur BDD : " . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────
// ÉTAPE 7 — Toujours répondre 200 à Stripe
// Si on répond autre chose, Stripe va réessayer pendant 3 jours !
// ─────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);
?>
