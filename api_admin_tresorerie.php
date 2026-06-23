<?php
// ==================== TRÉSORERIE ARKYL ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/notify_helpers.php';

function ensurePayoutsTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS artist_payouts (
            id               SERIAL PRIMARY KEY,
            order_id         INTEGER,
            order_number     VARCHAR(255),
            artist_id        VARCHAR(255),
            artist_name      VARCHAR(255),
            amount_artwork   NUMERIC(12,2) DEFAULT 0,
            amount_shipping  NUMERIC(12,2) DEFAULT 0,
            amount_total     NUMERIC(12,2) DEFAULT 0,
            payment_method   VARCHAR(100),
            payment_reference VARCHAR(255),
            payment_note     TEXT,
            paid_by          VARCHAR(255),
            created_at       TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

try {
    $db = getDatabase();
    ensurePayoutsTable($db);

    $method = $_SERVER['REQUEST_METHOD'];

    // ── POST : confirmer versement ──────────────────────────────────
    if ($method === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';

        if ($action === 'confirmer_versement') {
            $order_id      = $body['order_id']         ?? '';
            $payment_method= $body['payment_method']   ?? 'Wave/Orange Money';
            $payment_ref   = $body['payment_reference'] ?? '';
            $payment_note  = $body['payment_note']      ?? '';
            $paid_by       = $body['paid_by']           ?? 'admin';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                exit();
            }

            // Récupérer la commande
            $stmt = $db->prepare("
                SELECT o.*, COALESCE(
                    (SELECT json_agg(json_build_object(
                        'artist_id',   oi2.artist_id,
                        'artist_name', oi2.artist_name,
                        'title',       oi2.title
                    )) FROM order_items oi2 WHERE oi2.order_id = o.id),
                    '[]'
                ) AS items_json
                FROM orders o
                WHERE o.id = :oid OR o.order_number = :onum
                LIMIT 1
            ");
            $stmt->execute([':oid' => $order_id, ':onum' => $order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                exit();
            }

            // Mettre à jour escrow
            $upd = $db->prepare("
                UPDATE orders SET
                    escrow_status = 'fonds_déversés',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :oid OR order_number = :onum
                RETURNING id, order_number
            ");
            $upd->execute([':oid' => $order_id, ':onum' => $order_id]);
            $row = $upd->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Timeline
                $db->exec("
                    CREATE TABLE IF NOT EXISTS order_timeline (
                        id SERIAL PRIMARY KEY, order_id INTEGER, status VARCHAR(100),
                        note TEXT, updated_by_role VARCHAR(50),
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, 'Fonds déversés', :note, 'admin')
                ")->execute([
                    ':oid'  => $row['id'],
                    ':note' => "Versement confirmé par l'admin via {$payment_method}" . ($payment_ref ? " — Réf: {$payment_ref}" : '')
                ]);

                // Enregistrer dans artist_payouts
                $artisanPay  = floatval($order['artist_payout']  ?? 0);
                $shipping    = floatval($order['shipping_cost']   ?? 0);
                $totalVerse  = $artisanPay + $shipping;

                $items = json_decode($order['items_json'] ?? '[]', true) ?: [];
                $artistId   = '';
                $artistName = '';
                if (!empty($items)) {
                    $artistId   = $items[0]['artist_id']   ?? '';
                    $artistName = $items[0]['artist_name'] ?? '';
                }

                $db->prepare("
                    INSERT INTO artist_payouts
                        (order_id, order_number, artist_id, artist_name,
                         amount_artwork, amount_shipping, amount_total,
                         payment_method, payment_reference, payment_note, paid_by)
                    VALUES
                        (:oid, :onum, :artid, :artname,
                         :amt_art, :amt_ship, :amt_total,
                         :method, :ref, :note, :by)
                ")->execute([
                    ':oid'      => $row['id'],
                    ':onum'     => $row['order_number'],
                    ':artid'    => $artistId,
                    ':artname'  => $artistName,
                    ':amt_art'  => $artisanPay,
                    ':amt_ship' => $shipping,
                    ':amt_total'=> $totalVerse,
                    ':method'   => $payment_method,
                    ':ref'      => $payment_ref,
                    ':note'     => $payment_note,
                    ':by'       => $paid_by,
                ]);

                // Notifier l'artiste
                ensureNotificationsTable($db);
                if ($artistId) {
                    $montantFmt = number_format($totalVerse, 0, ',', ' ');
                    $db->prepare("
                        INSERT INTO artist_notifications
                            (artist_id, artist_name, type, order_id, order_number, title, message)
                        VALUES (:aid, :aname, 'payout_confirmed', :oid, :onum, :title, :msg)
                    ")->execute([
                        ':aid'   => $artistId,
                        ':aname' => $artistName,
                        ':oid'   => $row['id'],
                        ':onum'  => $row['order_number'],
                        ':title' => '💰 Fond délivré — Virement confirmé !',
                        ':msg'   => "L'admin ARKYL a confirmé le virement de {$montantFmt} FCFA pour la commande #{$row['order_number']}. Vérifiez votre compte {$payment_method}." . ($payment_ref ? " Référence : {$payment_ref}." : '')
                    ]);
                }
            }

            echo json_encode(['success' => (bool)$row]);
            exit();
        }

        // action inconnue en POST
        echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);
        exit();
    }

    // ── GET : statistiques + paiements urgents + historique ─────────
    $stmtOrders = $db->prepare("
        SELECT order_number, total, commission_amount, artist_payout, shipping_cost, escrow_status, created_at, artist_id,
               COALESCE(
                   (SELECT json_agg(json_build_object(
                       'artist_id',   oi.artist_id,
                       'artist_name', oi.artist_name,
                       'title',       oi.title,
                       'price',       oi.price,
                       'quantity',    oi.quantity,
                       'image_url',   oi.image_url
                   )) FROM order_items oi WHERE oi.order_id = orders.id),
                   '[]'
               ) AS items_json,
               id, user_name, user_email
        FROM orders
        WHERE status NOT IN ('Annulée','Refusée') AND escrow_status NOT IN ('refusée','interrompue')
        ORDER BY created_at DESC
    ");
    $stmtOrders->execute();
    $commandes = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    $total_commission_arkyl      = 0;
    $total_a_payer_artistes      = 0;
    $total_verses_artistes       = 0;
    $total_encaisse              = 0;
    $liste_paiements_en_attente  = [];
    $liste_paiements_verses      = [];

    foreach ($commandes as &$cmd) {
        $cmd['items'] = json_decode($cmd['items_json'] ?? '[]', true) ?: [];

        $total      = floatval($cmd['total']             ?? 0);
        $payout     = floatval($cmd['artist_payout']     ?? 0) ?: $total * 0.65;
        $commission = floatval($cmd['commission_amount'] ?? 0) ?: $total * 0.35;
        $shipping   = floatval($cmd['shipping_cost']     ?? 0);

        $total_encaisse          += $total;
        $total_commission_arkyl  += $commission;

        $montant_artiste = $payout + $shipping;

        if ($cmd['escrow_status'] === 'fonds_déversés') {
            $total_verses_artistes          += $montant_artiste;
            $cmd['payout_total_with_shipping'] = $montant_artiste;
            $liste_paiements_verses[]          = $cmd;
        } elseif (in_array($cmd['escrow_status'], ['fonds_libérés', 'livrée_confirmée'])) {
            $total_a_payer_artistes            += $montant_artiste;
            $cmd['payout_total_with_shipping']  = $montant_artiste;
            $liste_paiements_en_attente[]       = $cmd;
        }
    }
    unset($cmd);

    // Historique des transactions
    $stmtTx = $db->prepare("SELECT * FROM artist_payouts ORDER BY created_at DESC LIMIT 50");
    $stmtTx->execute();
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_encaisse'              => $total_encaisse,
            'chiffre_affaire_arkyl'       => $total_commission_arkyl,
            'argent_a_verser_wave_orange' => $total_a_payer_artistes,
            'total_verses_artistes'       => $total_verses_artistes,
        ],
        'paiements_urgents'   => $liste_paiements_en_attente,
        'paiements_verses'    => $liste_paiements_verses,
        'transactions'        => $transactions,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
