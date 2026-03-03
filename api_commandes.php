<?php
/**
 * api_commandes.php
 * CRUD complet pour les commandes ARKYL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

// ─────────────────────────────────────────────────────────────────
// MIGRATIONS
// ─────────────────────────────────────────────────────────────────
function runMigrations($db) {
    $cols = [
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_url VARCHAR(500)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier VARCHAR(100)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes TEXT",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMPTZ",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMPTZ",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS confirmed_at TIMESTAMPTZ",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_released_at TIMESTAMPTZ",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_auto_release_date TIMESTAMPTZ",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_by VARCHAR(50)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_status VARCHAR(50) DEFAULT 'payée_en_attente'",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_mode VARCHAR(50)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_name VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_email VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS commission_amount NUMERIC(12,2) DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_payout NUMERIC(12,2) DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255)",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost NUMERIC(12,2) DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_name VARCHAR(255)",
        "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS sold_at TIMESTAMPTZ",
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id SERIAL PRIMARY KEY,
        order_number VARCHAR(20) UNIQUE,
        user_id VARCHAR(255) NOT NULL,
        user_name VARCHAR(255),
        user_email VARCHAR(255),
        status VARCHAR(50) DEFAULT 'En préparation',
        escrow_status VARCHAR(50) DEFAULT 'payée_en_attente',
        subtotal NUMERIC(12,2) DEFAULT 0,
        tax NUMERIC(12,2) DEFAULT 0,
        shipping_cost NUMERIC(12,2) DEFAULT 0,
        total NUMERIC(12,2) DEFAULT 0,
        shipping_name VARCHAR(255),
        shipping_mode VARCHAR(50),
        shipping_address TEXT,
        payment_method VARCHAR(100),
        tracking_number VARCHAR(255),
        tracking_url VARCHAR(500),
        shipping_proof_url TEXT,
        carrier VARCHAR(100),
        notes TEXT,
        commission_amount NUMERIC(12,2) DEFAULT 0,
        artist_payout NUMERIC(12,2) DEFAULT 0,
        stripe_session_id VARCHAR(255),
        shipped_at TIMESTAMPTZ,
        delivered_at TIMESTAMPTZ,
        confirmed_at TIMESTAMPTZ,
        escrow_released_at TIMESTAMPTZ,
        escrow_auto_release_date TIMESTAMPTZ,
        updated_by VARCHAR(50),
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW()
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id SERIAL PRIMARY KEY,
        order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
        artwork_id INTEGER,
        title VARCHAR(255),
        artist_name VARCHAR(255),
        artist_id VARCHAR(255),
        price NUMERIC(12,2),
        quantity INTEGER DEFAULT 1,
        image_url TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS order_timeline (
        id SERIAL PRIMARY KEY,
        order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
        status VARCHAR(50),
        note TEXT,
        updated_by VARCHAR(255),
        updated_by_role VARCHAR(50),
        created_at TIMESTAMPTZ DEFAULT NOW()
    )");

    // ⭐ NOUVEAU : Table notifications artiste
    $db->exec("CREATE TABLE IF NOT EXISTS artist_notifications (
        id SERIAL PRIMARY KEY,
        artist_id VARCHAR(255) NOT NULL,
        type VARCHAR(50) DEFAULT 'new_order',
        title VARCHAR(255),
        message TEXT,
        order_id INTEGER,
        order_number VARCHAR(20),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMPTZ DEFAULT NOW()
    )");
}

// ─────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────
function fetchOrderItems($db, $orderId) {
    $stmt = $db->prepare("
        SELECT oi.id, oi.artwork_id, oi.title,
               oi.artist_name AS artist, oi.artist_name,
               COALESCE(oi.artist_id, '') AS artist_id,
               oi.price, oi.quantity,
               oi.image_url AS image, oi.image_url
        FROM order_items oi
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchTimeline($db, $orderId) {
    $stmt = $db->prepare("SELECT * FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ─────────────────────────────────────────────────────────────────
// ⭐ NOUVEAU : Alertes artiste — email + notification BDD
// ─────────────────────────────────────────────────────────────────
function notifyArtists($db, $orderId, $orderNumber, $items, $buyerName, $total) {

    // Regrouper les items par artist_id
    $byArtist = [];
    foreach ($items as $item) {
        $aid = COALESCE_str($item['artist_id'] ?? $item['artist_id'] ?? '');
        if (!$aid) continue;
        if (!isset($byArtist[$aid])) $byArtist[$aid] = [];
        $byArtist[$aid][] = $item;
    }

    foreach ($byArtist as $artistId => $artistItems) {

        // 1. Récupérer email + nom de l'artiste depuis la table artists
        $stmt = $db->prepare("SELECT email, artist_name, name FROM artists WHERE id::text = ? LIMIT 1");
        $stmt->execute([$artistId]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        $artistEmail = $artist['email'] ?? null;
        $artistName  = $artist['artist_name'] ?? $artist['name'] ?? 'Artiste';

        // Construire le résumé des œuvres vendues
        $titlesArr = array_map(fn($i) => '"' . ($i['title'] ?? 'Œuvre') . '"', $artistItems);
        $titlesStr = implode(', ', $titlesArr);
        $itemsTotal = array_sum(array_map(fn($i) => ($i['price'] ?? 0) * ($i['quantity'] ?? 1), $artistItems));

        $notifTitle   = "🎉 Nouvelle commande — {$orderNumber}";
        $notifMessage = "Bonne nouvelle {$artistName} ! {$buyerName} vient de commander {$titlesStr} pour un montant de " . number_format($itemsTotal, 0, ',', ' ') . " FCFA.";

        // ── 2. Notification en base de données ──────────────────
        try {
            $db->prepare("
                INSERT INTO artist_notifications (artist_id, type, title, message, order_id, order_number)
                VALUES (?, 'new_order', ?, ?, ?, ?)
            ")->execute([$artistId, $notifTitle, $notifMessage, $orderId, $orderNumber]);
        } catch (Exception $e) {
            error_log("⚠️ Notification BDD artiste {$artistId} : " . $e->getMessage());
        }

        // ── 3. Email à l'artiste ─────────────────────────────────
        if ($artistEmail) {
            try {
                $subject = "=?UTF-8?B?" . base64_encode("🎉 ARKYL — Nouvelle commande {$orderNumber}") . "?=";

                // Tableau HTML des œuvres
                $itemsHtml = '';
                foreach ($artistItems as $item) {
                    $itemsHtml .= '<tr>
                        <td style="padding:8px;border-bottom:1px solid #2a2a2a;">' . htmlspecialchars($item['title'] ?? '') . '</td>
                        <td style="padding:8px;border-bottom:1px solid #2a2a2a;text-align:right;">' . number_format($item['price'] ?? 0, 0, ',', ' ') . ' FCFA</td>
                    </tr>';
                }

                $htmlBody = '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f0f0f;font-family:Montserrat,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f0f;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#1a1a1a;border-radius:16px;overflow:hidden;border:1px solid #9333ea44;">
      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#9333ea,#c026d3);padding:32px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:28px;letter-spacing:2px;">ARKYL</h1>
        <p style="margin:8px 0 0;color:#fff;opacity:.85;font-size:14px;">Galerie d\'Art Contemporain</p>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:32px;">
        <h2 style="color:#9333ea;margin:0 0 8px;">🎉 Nouvelle commande !</h2>
        <p style="color:#ccc;font-size:15px;line-height:1.6;">
          Bonjour <strong style="color:#fff;">' . htmlspecialchars($artistName) . '</strong>,<br>
          Excellente nouvelle ! Une de vos œuvres vient d\'être commandée.
        </p>
        <!-- Détails commande -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#111;border-radius:12px;padding:20px;margin:20px 0;">
          <tr>
            <td style="color:#888;font-size:13px;padding:4px 0;">Numéro de commande</td>
            <td style="color:#9333ea;font-size:13px;text-align:right;font-weight:700;">' . htmlspecialchars($orderNumber) . '</td>
          </tr>
          <tr>
            <td style="color:#888;font-size:13px;padding:4px 0;">Acheteur</td>
            <td style="color:#fff;font-size:13px;text-align:right;">' . htmlspecialchars($buyerName) . '</td>
          </tr>
        </table>
        <!-- Œuvres -->
        <p style="color:#aaa;font-size:13px;margin:0 0 8px;">Œuvres commandées :</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#111;border-radius:12px;overflow:hidden;">
          <tr style="background:#1f1f1f;">
            <th style="padding:10px 8px;color:#888;font-size:12px;text-align:left;">Titre</th>
            <th style="padding:10px 8px;color:#888;font-size:12px;text-align:right;">Prix</th>
          </tr>
          ' . $itemsHtml . '
          <tr>
            <td style="padding:10px 8px;color:#fff;font-weight:700;">Total artiste</td>
            <td style="padding:10px 8px;color:#9333ea;font-weight:700;text-align:right;">' . number_format($itemsTotal, 0, ',', ' ') . ' FCFA</td>
          </tr>
        </table>
        <p style="color:#aaa;font-size:13px;margin:24px 0 0;line-height:1.6;">
          Connectez-vous à votre espace artiste pour suivre l\'évolution de cette commande.
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#111;padding:20px;text-align:center;border-top:1px solid #222;">
        <p style="color:#555;font-size:12px;margin:0;">© ARKYL — Galerie d\'Art Contemporain</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ARKYL Galerie <noreply@arkyl-galerie.com>\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                mail($artistEmail, $subject, $htmlBody, $headers);
                error_log("✅ Email commande envoyé à {$artistEmail} pour commande {$orderNumber}");

            } catch (Exception $e) {
                error_log("❌ Email artiste {$artistEmail} : " . $e->getMessage());
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// MAIN
// ─────────────────────────────────────────────────────────────────
try {
    $db = getDatabase();
    runMigrations($db);

    $action = $_GET['action'] ?? '';
    if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';
    } else {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // ── GET list ──────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $userId   = $_GET['user_id']   ?? '';
        $artistId = (string)($_GET['artist_id'] ?? '');
        $isAdmin  = isset($_GET['admin']) && $_GET['admin'] == '1';

        if ($isAdmin) {
            $stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($artistId !== '') {
            $stmt = $db->prepare("SELECT DISTINCT order_id FROM order_items WHERE COALESCE(artist_id, '') = ?");
            $stmt->execute([$artistId]);
            $orderIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'order_id');
            if (empty($orderIds)) { echo json_encode(['success' => true, 'orders' => []]); exit; }
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $stmt = $db->prepare("SELECT * FROM orders WHERE id IN ($placeholders) ORDER BY created_at DESC");
            $stmt->execute($orderIds);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($orders as &$order) {
            $allItems = fetchOrderItems($db, $order['id']);
            if ($artistId !== '' && !$isAdmin) {
                $order['items'] = array_values(array_filter($allItems, fn($i) => COALESCE_str($i['artist_id']) === $artistId));
            } else {
                $order['items'] = $allItems;
            }
            $order['timeline'] = fetchTimeline($db, $order['id']);
        }
        unset($order);
        echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── GET single ────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $orderId = $_GET['order_id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? OR id::text = ?");
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) { echo json_encode(['success' => false, 'error' => 'Commande non trouvée']); exit; }
        $order['items']    = fetchOrderItems($db, $order['id']);
        $order['timeline'] = fetchTimeline($db, $order['id']);
        echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── GET notifications artiste ─────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_notifications') {
        $artistId = $_GET['artist_id'] ?? '';
        if (!$artistId) { echo json_encode(['success' => false, 'error' => 'artist_id manquant']); exit; }
        $stmt = $db->prepare("
            SELECT * FROM artist_notifications
            WHERE artist_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$artistId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unread = array_filter($notifs, fn($n) => !$n['is_read']);
        echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => count($unread)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST mark_notifications_read ──────────────────────────────
    if ($action === 'mark_notifications_read') {
        $artistId = $body['artist_id'] ?? '';
        if (!$artistId) { echo json_encode(['success' => false, 'error' => 'artist_id manquant']); exit; }
        $db->prepare("UPDATE artist_notifications SET is_read = TRUE WHERE artist_id = ?")->execute([$artistId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── POST create ───────────────────────────────────────────────
    if ($action === 'create') {
        $orderNum = 'ARK-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $stmt = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, user_name, user_email,
                status, escrow_status,
                subtotal, tax, shipping_cost, shipping_name, total,
                shipping_mode, shipping_address, payment_method
            ) VALUES (
                :num, :uid, :uname, :uemail,
                'En préparation', 'payée_en_attente',
                :subtotal, :tax, :shipping_cost, :shipping_name, :total,
                :shipping_mode, :shipping_address, :payment_method
            ) RETURNING id
        ");
        $stmt->execute([
            ':num'              => $orderNum,
            ':uid'              => $body['user_id']           ?? '',
            ':uname'            => $body['user_name']         ?? '',
            ':uemail'           => $body['user_email']        ?? '',
            ':subtotal'         => $body['subtotal']          ?? 0,
            ':tax'              => $body['tax']               ?? 0,
            ':shipping_cost'    => $body['shipping_cost']     ?? 0,
            ':shipping_name'    => $body['shipping_name']     ?? '',
            ':total'            => $body['total']             ?? 0,
            ':shipping_mode'    => $body['shipping_mode']     ?? '',
            ':shipping_address' => $body['shipping_address']  ?? '',
            ':payment_method'   => $body['payment_method']    ?? '',
        ]);
        $orderId = $stmt->fetchColumn();

        $insertItem = $db->prepare("
            INSERT INTO order_items (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $itemsInserted = [];
        foreach (($body['items'] ?? []) as $item) {
            $insertItem->execute([
                $orderId,
                $item['artwork_id'] ?? $item['id'] ?? null,
                $item['title']      ?? '',
                $item['artist']     ?? $item['artist_name'] ?? '',
                (string)($item['artist_id'] ?? ''),
                $item['price']      ?? 0,
                $item['quantity']   ?? 1,
                $item['image']      ?? $item['image_url'] ?? '',
            ]);
            $itemsInserted[] = $item;
        }

        // Marquer les œuvres comme vendues
        $markSold = $db->prepare("UPDATE artworks SET is_sold = TRUE, sold_at = NOW() WHERE id = ? AND (is_sold IS NULL OR is_sold = FALSE)");
        foreach (($body['items'] ?? []) as $item) {
            $artworkId = intval($item['artwork_id'] ?? $item['id'] ?? 0);
            if ($artworkId > 0) $markSold->execute([$artworkId]);
        }

        $db->prepare("
            INSERT INTO order_timeline (order_id, status, note, updated_by_role)
            VALUES (?, 'En préparation', 'Commande créée et paiement validé', 'system')
        ")->execute([$orderId]);

        // ⭐ Envoyer alertes aux artistes concernés
        $buyerName = $body['user_name'] ?? $body['user_email'] ?? 'Un acheteur';
        $total     = $body['total'] ?? 0;
        notifyArtists($db, $orderId, $orderNum, $itemsInserted, $buyerName, $total);

        echo json_encode(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNum]);
        exit;
    }

    // ── POST update_status ────────────────────────────────────────
    if ($action === 'update_status') {
        $orderId        = $body['order_id']           ?? '';
        $newStatus      = $body['status']             ?? '';
        $trackingNumber = $body['tracking_number']    ?? null;
        $trackingUrl    = $body['tracking_url']       ?? null;
        $carrier        = $body['carrier']            ?? null;
        $note           = $body['note']               ?? null;
        $updatedBy      = $body['updated_by']         ?? '';
        $updatedByRole  = $body['updated_by_role']    ?? 'admin';
        $proofUrl       = $body['shipping_proof_url'] ?? null;

        $escrowMap = [
            'En préparation' => 'payée_en_attente',
            'Préparée'       => 'payée_en_attente',
            'Expédiée'       => 'expédiée',
            'En transit'     => 'expédiée',
            'Livrée'         => 'livrée_confirmée',
            'Annulée'        => 'annulée',
        ];
        $escrowStatus = $escrowMap[$newStatus] ?? 'payée_en_attente';

        $setFields = "status = :status, escrow_status = :escrow, updated_at = NOW(), updated_by = :by";
        $params    = [':status' => $newStatus, ':escrow' => $escrowStatus, ':by' => $updatedByRole, ':id' => $orderId];

        if ($trackingNumber) { $setFields .= ", tracking_number = :tn";      $params[':tn']      = $trackingNumber; }
        if ($trackingUrl)    { $setFields .= ", tracking_url = :tu";          $params[':tu']      = $trackingUrl; }
        if ($carrier)        { $setFields .= ", carrier = :carrier";          $params[':carrier'] = $carrier; }
        if ($proofUrl)       { $setFields .= ", shipping_proof_url = :proof"; $params[':proof']   = $proofUrl; }

        if ($newStatus === 'Expédiée' || $newStatus === 'En transit') {
            $setFields .= ", shipped_at = NOW()";
            $autoRelease = date('Y-m-d H:i:s', strtotime('+21 days'));
            $setFields .= ", escrow_auto_release_date = '$autoRelease'";
        }
        if ($newStatus === 'Livrée') { $setFields .= ", delivered_at = NOW()"; }

        $db->prepare("UPDATE orders SET $setFields WHERE order_number = :id OR id::text = :id")->execute($params);

        $tlNote = $note ?: "Statut mis à jour : $newStatus";
        if ($trackingNumber) $tlNote .= " | Tracking: $trackingNumber";
        if ($carrier)        $tlNote .= " ($carrier)";

        $row = $db->prepare("SELECT id FROM orders WHERE order_number = ? OR id::text = ? LIMIT 1");
        $row->execute([$orderId, $orderId]);
        $dbOrderId = $row->fetchColumn();

        if ($dbOrderId) {
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by, updated_by_role)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$dbOrderId, $newStatus, $tlNote, $updatedBy, $updatedByRole]);
        }

        echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
        exit;
    }

    // ── POST confirm_reception ────────────────────────────────────
    if ($action === 'confirm_reception') {
        $orderId = $body['order_id'] ?? '';
        $db->prepare("
            UPDATE orders SET status = 'Livrée', escrow_status = 'livrée_confirmée',
            confirmed_at = NOW(), updated_at = NOW()
            WHERE order_number = ? OR id::text = ?
        ")->execute([$orderId, (string)$orderId]);

        $row = $db->prepare("SELECT id FROM orders WHERE order_number = ? OR id::text = ? LIMIT 1");
        $row->execute([$orderId, $orderId]);
        $dbOrderId = $row->fetchColumn();
        if ($dbOrderId) {
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                VALUES (?, 'Livrée', 'Réception confirmée par l''acheteur — en attente de virement artiste', 'buyer')
            ")->execute([$dbOrderId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // ── POST liberer_fonds ────────────────────────────────────────
    if ($action === 'liberer_fonds') {
        $orderId = $body['order_id'] ?? '';
        if (!$orderId) { echo json_encode(['success' => false, 'error' => 'order_id manquant']); exit; }
        $db->prepare("
            UPDATE orders SET escrow_status = 'fonds_libérés', escrow_released_at = NOW(), updated_at = NOW()
            WHERE id::text = ? OR order_number = ?
        ")->execute([(string)$orderId, (string)$orderId]);

        $row = $db->prepare("SELECT id FROM orders WHERE id::text = ? OR order_number = ? LIMIT 1");
        $row->execute([(string)$orderId, (string)$orderId]);
        $dbOrderId = $row->fetchColumn();
        if ($dbOrderId) {
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                VALUES (?, 'Fonds libérés', 'Virement artiste confirmé par l''admin', 'admin')
            ")->execute([$dbOrderId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // ── POST delete_order ─────────────────────────────────────────
    if ($action === 'delete_order') {
        $orderId = $body['order_id'] ?? '';
        if (!$orderId) { echo json_encode(['success' => false, 'error' => 'order_id manquant']); exit; }
        $db->prepare("DELETE FROM orders WHERE id::text = ? OR order_number = ?")->execute([$orderId, $orderId]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);

} catch (Exception $e) {
    error_log("❌ api_commandes.php — " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function COALESCE_str($val) {
    return $val === null ? '' : (string)$val;
}
?>
