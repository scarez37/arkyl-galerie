<?php
// ===================================================================
// stripe_order_helper.php — création d'une commande à partir d'une session
// Stripe Checkout PAYÉE. Logique unique, partagée par :
//   • webhook_stripe.php            (appel serveur-à-serveur de Stripe)
//   • api_commandes.php (webhook)   (même rôle, ancienne URL)
//   • api_commandes.php action=create (rattrapage depuis le navigateur,
//     après vérification du paiement auprès de Stripe)
// ===================================================================

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/notify_helpers.php';

/**
 * Crée la commande (lignes, œuvres vendues, timeline, panier vidé) si elle n'existe pas encore.
 * Idempotent : un second appel pour la même session ne recrée rien.
 *
 * @param PDO    $db
 * @param object $session  objet Stripe\Checkout\Session (payé)
 * @return array ['status' => 'created'|'exists'|'skipped', 'order_id' => ?int, 'order_number' => string, 'reason' => ?string]
 */
function arkylCreateOrderFromSession(PDO $db, $session): array {
    $meta = $session->metadata ?? null;

    $order_id          = (string) ($meta->order_id ?? '');
    $user_id           = (string) ($meta->user_id ?? $session->client_reference_id ?? '');
    $user_name         = (string) ($meta->user_name ?? ($session->customer_details->name ?? ''));
    $user_email        = (string) ($meta->user_email ?? ($session->customer_details->email ?? $session->customer_email ?? ''));
    $artist_id         = $meta->artist_id ?? null;
    $shipping_cost     = floatval($meta->shipping_cost ?? 3000);
    $shipping_mode     = (string) ($meta->shipping_mode ?? 'La Poste');
    $shipping_address  = (string) ($meta->shipping_address ?? '');
    $commission_amount = floatval($meta->commission_amount ?? 0); // part ARKYL (35 %)
    $artist_payout     = floatval($meta->artist_payout ?? 0);     // part artiste (65 %)
    $payment_method    = 'Carte bancaire (Stripe)';

    if ($order_id === '' || $user_id === '') {
        error_log('ARKYL commande — order_id ou user_id manquant, session ' . ($session->id ?? '?'));
        return ['status' => 'skipped', 'order_id' => null, 'order_number' => $order_id, 'reason' => 'metadata_incomplete'];
    }

    // Colonnes ajoutées après la création initiale (contrôle avant ALTER : pas de verrou inutile)
    foreach ([
        ['orders', 'commission_amount', 'NUMERIC(12,2) DEFAULT 0'],
        ['orders', 'artist_payout', 'NUMERIC(12,2) DEFAULT 0'],
        ['orders', 'stripe_session_id', 'VARCHAR(255)'],
        ['orders', 'shipping_address', 'TEXT'],
        ['orders', 'user_name', 'VARCHAR(255)'],
        ['orders', 'user_email', 'VARCHAR(255)'],
        ['orders', 'artist_id', 'VARCHAR(255)'],
        ['artworks', 'is_sold', 'BOOLEAN DEFAULT FALSE'],
        ['artworks', 'sold_at', 'TIMESTAMPTZ'],
        ['order_items', 'artist_id', 'VARCHAR(255)'],
    ] as [$t, $c, $ddl]) {
        try { arkylEnsureColumn($db, $t, $c, $ddl); } catch (Throwable $e) { /* déjà présente */ }
    }

    // Idempotence : par numéro de commande OU par session Stripe
    $chk = $db->prepare('SELECT id, order_number FROM orders WHERE order_number = :n OR stripe_session_id = :s LIMIT 1');
    $chk->execute([':n' => $order_id, ':s' => (string) ($session->id ?? '')]);
    if ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
        return ['status' => 'exists', 'order_id' => (int) $row['id'], 'order_number' => $row['order_number'], 'reason' => null];
    }

    // Contenu du panier + artiste de chaque œuvre
    $cartStmt = $db->prepare("
        SELECT c.quantity, c.artwork_id, a.title, a.price, a.image_url, a.artist_name, a.artist_id
        FROM cart c
        INNER JOIN artworks a ON c.artwork_id = a.id
        WHERE c.user_id = :user_id
    ");
    $cartStmt->execute([':user_id' => $user_id]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cartItems)) {
        error_log("ARKYL commande — panier vide pour user_id=$user_id, order_id=$order_id");
        return ['status' => 'skipped', 'order_id' => null, 'order_number' => $order_id, 'reason' => 'empty_cart'];
    }

    $subtotal_fcfa = 0;
    foreach ($cartItems as $item) {
        $subtotal_fcfa += floatval($item['price']) * intval($item['quantity']);
    }
    $tax_fcfa          = round($subtotal_fcfa * 0.18);
    $total_fcfa        = $subtotal_fcfa + $shipping_cost;
    $auto_release_date = (new DateTime())->modify('+21 days')->format('Y-m-d H:i:s');

    $db->beginTransaction();
    try {
        $insertOrder = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, user_name, user_email,
                artist_id, status, escrow_status, escrow_auto_release_date,
                subtotal, tax, shipping_cost, shipping_name, shipping_address,
                payment_method, total, commission_amount, artist_payout,
                stripe_session_id, created_at
            ) VALUES (
                :order_number, :user_id, :user_name, :user_email,
                :artist_id, 'En préparation', 'payée_en_attente', :auto_release_date,
                :subtotal, :tax, :shipping_cost, :shipping_name, :shipping_address,
                :payment_method, :total, :commission_amount, :artist_payout,
                :stripe_session_id, CURRENT_TIMESTAMP
            ) RETURNING id
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
            ':commission_amount' => $commission_amount,
            ':artist_payout'     => $artist_payout,
            ':stripe_session_id' => (string) ($session->id ?? ''),
        ]);
        $newId = (int) $insertOrder->fetchColumn();

        $insertItem = $db->prepare("
            INSERT INTO order_items (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
            VALUES (:order_id, :artwork_id, :title, :artist_name, :artist_id, :price, :quantity, :image_url)
        ");
        $markSold = $db->prepare("
            UPDATE artworks SET is_sold = TRUE, sold_at = NOW(), badge = 'Vendu', status = 'vendue' WHERE id = :artwork_id
        ");
        foreach ($cartItems as $item) {
            $insertItem->execute([
                ':order_id'    => $newId,
                ':artwork_id'  => $item['artwork_id'],
                ':title'       => $item['title'],
                ':artist_name' => $item['artist_name'] ?? '',
                ':artist_id'   => (string) ($item['artist_id'] ?? ''),
                ':price'       => $item['price'],
                ':quantity'    => $item['quantity'],
                ':image_url'   => $item['image_url'] ?? '',
            ]);
            $markSold->execute([':artwork_id' => $item['artwork_id']]);
        }

        $db->prepare("
            INSERT INTO order_timeline (order_id, status, note, updated_by_role)
            VALUES (:order_id, 'En préparation', 'Commande créée — paiement Stripe validé', 'system')
        ")->execute([':order_id' => $newId]);

        $db->prepare('DELETE FROM cart WHERE user_id = :user_id')->execute([':user_id' => $user_id]);

        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Course entre webhook et rattrapage : l'autre a déjà créé la commande
        if ($e->getCode() === '23505') {
            $chk->execute([':n' => $order_id, ':s' => (string) ($session->id ?? '')]);
            if ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
                return ['status' => 'exists', 'order_id' => (int) $row['id'], 'order_number' => $row['order_number'], 'reason' => null];
            }
        }
        throw $e;
    }

    try {
        notifyArtists($db, $newId, $order_id, $cartItems, $user_name ?: $user_email,
                      $subtotal_fcfa + $shipping_cost, $shipping_address, $shipping_mode);
    } catch (Throwable $e) {
        error_log('ARKYL notifyArtists : ' . $e->getMessage());
    }

    error_log("ARKYL commande $order_id créée (id $newId) — " . count($cartItems) . ' article(s)');
    return ['status' => 'created', 'order_id' => $newId, 'order_number' => $order_id, 'reason' => null];
}
