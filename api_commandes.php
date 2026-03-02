<?php
/**
 * api_commandes.php
 * CRUD complet pour les commandes ARKYL
 *
 * GET  ?action=list&user_id=X          → commandes d'un acheteur
 * GET  ?action=list&artist_id=X        → commandes pour un artiste
 * GET  ?action=list&admin=1            → toutes les commandes (admin)
 * GET  ?action=get&order_id=X          → détail d'une commande
 * POST action=create                   → créer une commande
 * POST action=update_status            → changer statut + tracking
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db_config.php';

// ─────────────────────────────────────────────────────────────────
// MIGRATIONS — chaque ALTER dans son propre try/catch isolé
// Jamais dans une transaction — DDL PostgreSQL est auto-commit
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
        "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS sold_at TIMESTAMPTZ",
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* déjà présente */ }
    }

    // Tables principales
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
}

// ─────────────────────────────────────────────────────────────────
// Construire les items JSON d'une commande depuis order_items
// Séparé en fonction pour éviter l'interpolation dans prepare()
// ─────────────────────────────────────────────────────────────────
function fetchOrderItems($db, $orderId) {
    $stmt = $db->prepare("
        SELECT
            oi.id,
            oi.artwork_id,
            oi.title,
            oi.artist_name                    AS artist,
            oi.artist_name                    AS artist_name,
            COALESCE(oi.artist_id, '')        AS artist_id,
            oi.price,
            oi.quantity,
            oi.image_url                      AS image,
            oi.image_url
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

    // ── GET list ────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $userId   = $_GET['user_id']   ?? '';
        $artistId = (string)($_GET['artist_id'] ?? '');
        $isAdmin  = isset($_GET['admin']) && $_GET['admin'] == '1';

        if ($isAdmin) {
            $stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($artistId !== '') {
            // Trouver les order_id qui contiennent au moins un item de cet artiste
            $stmt = $db->prepare("
                SELECT DISTINCT order_id
                FROM order_items
                WHERE COALESCE(artist_id, '') = ?
            ");
            $stmt->execute([$artistId]);
            $orderIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'order_id');

            if (empty($orderIds)) {
                echo json_encode(['success' => true, 'orders' => []]);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $stmt = $db->prepare("
                SELECT * FROM orders
                WHERE id IN ($placeholders)
                ORDER BY created_at DESC
            ");
            $stmt->execute($orderIds);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Attacher items + timeline à chaque commande
        foreach ($orders as &$order) {
            $allItems = fetchOrderItems($db, $order['id']);

            // Si vue artiste : ne garder que ses items dans la réponse
            if ($artistId !== '' && !$isAdmin) {
                $order['items'] = array_values(array_filter($allItems, function($i) use ($artistId) {
                    return COALESCE_str($i['artist_id']) === $artistId;
                }));
            } else {
                $order['items'] = $allItems;
            }

            $order['timeline'] = fetchTimeline($db, $order['id']);
        }
        unset($order);

        echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── GET single ──────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $orderId = $_GET['order_id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? OR id::text = ?");
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Commande non trouvée']);
            exit;
        }

        $order['items']    = fetchOrderItems($db, $order['id']);
        $order['timeline'] = fetchTimeline($db, $order['id']);

        echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST create ─────────────────────────────────────────────────────────
    if ($action === 'create') {
        $orderNum = 'ARK-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $stmt = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, user_name, user_email,
                status, escrow_status,
                subtotal, tax, shipping_cost, total,
                shipping_name, shipping_mode, shipping_address, payment_method
            ) VALUES (
                :num, :uid, :uname, :uemail,
                'En préparation', 'payée_en_attente',
                :subtotal, :tax, :shipping_cost, :total,
                :shipping_name, :shipping_mode, :shipping_address, :payment_method
            ) RETURNING id
        ");
        $stmt->execute([
            ':num'             => $orderNum,
            ':uid'             => $body['user_id']        ?? '',
            ':uname'           => $body['user_name']      ?? '',
            ':uemail'          => $body['user_email']     ?? '',
            ':subtotal'        => $body['subtotal']       ?? 0,
            ':tax'             => $body['tax']            ?? 0,
            ':shipping_cost'   => $body['shipping_cost']  ?? 0,
            ':total'           => $body['total']          ?? 0,
            ':shipping_name'   => $body['shipping_name']  ?? '',
            ':shipping_mode'   => $body['shipping_mode']  ?? '',
            ':shipping_address'=> $body['shipping_address'] ?? '',
            ':payment_method'  => $body['payment_method'] ?? '',
        ]);
        $orderId = $stmt->fetchColumn();

        $insertItem = $db->prepare("
            INSERT INTO order_items (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
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
        }

        // ⭐ Marquer les œuvres comme vendues (is_sold + sold_at)
        // Important si le webhook Stripe n'est pas encore actif
        $markSold = $db->prepare("
            UPDATE artworks SET is_sold = TRUE, sold_at = NOW()
            WHERE id = ? AND (is_sold IS NULL OR is_sold = FALSE)
        ");
        foreach (($body['items'] ?? []) as $item) {
            $artworkId = intval($item['artwork_id'] ?? $item['id'] ?? 0);
            if ($artworkId > 0) {
                $markSold->execute([$artworkId]);
            }
        }

        $db->prepare("
            INSERT INTO order_timeline (order_id, status, note, updated_by_role)
            VALUES (?, 'En préparation', 'Commande créée et paiement validé', 'system')
        ")->execute([$orderId]);

        echo json_encode(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNum]);
        exit;
    }

    // ── POST update_status ──────────────────────────────────────────────────
    if ($action === 'update_status') {
        $orderId        = $body['order_id']        ?? '';
        $newStatus      = $body['status']          ?? '';
        $trackingNumber = $body['tracking_number'] ?? null;
        $trackingUrl    = $body['tracking_url']    ?? null;
        $carrier        = $body['carrier']         ?? null;
        $note           = $body['note']            ?? null;
        $updatedBy      = $body['updated_by']      ?? '';
        $updatedByRole  = $body['updated_by_role'] ?? 'admin';
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

        if ($trackingNumber) { $setFields .= ", tracking_number = :tn";    $params[':tn']    = $trackingNumber; }
        if ($trackingUrl)    { $setFields .= ", tracking_url = :tu";        $params[':tu']    = $trackingUrl; }
        if ($carrier)        { $setFields .= ", carrier = :carrier";        $params[':carrier'] = $carrier; }
        if ($proofUrl)       { $setFields .= ", shipping_proof_url = :proof"; $params[':proof'] = $proofUrl; }

        if ($newStatus === 'Expédiée' || $newStatus === 'En transit') {
            $setFields .= ", shipped_at = NOW()";
            $autoRelease = date('Y-m-d H:i:s', strtotime('+21 days'));
            $setFields .= ", escrow_auto_release_date = '$autoRelease'";
        }
        if ($newStatus === 'Livrée') {
            $setFields .= ", delivered_at = NOW()";
        }

        $db->prepare("UPDATE orders SET $setFields WHERE order_number = :id OR id::text = :id")
           ->execute($params);

        $tlNote = $note ?: "Statut mis à jour : $newStatus";
        if ($trackingNumber) $tlNote .= " | Tracking: $trackingNumber";
        if ($carrier)        $tlNote .= " ($carrier)";

        // Récupérer l'id numérique de la commande pour order_timeline
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

    // ── POST confirm_reception (acheteur) ───────────────────────────────────
    if ($action === 'confirm_reception') {
        $orderId = $body['order_id'] ?? '';

        $db->prepare("
            UPDATE orders SET
                status = 'Livrée',
                escrow_status = 'livrée_confirmée',
                confirmed_at = NOW(),
                updated_at = NOW()
            WHERE order_number = ? OR id::text = ?
        ")->execute([$orderId, (string)$orderId]);

        $row = $db->prepare("SELECT id FROM orders WHERE order_number = ? OR id::text = ? LIMIT 1");
        $row->execute([$orderId, $orderId]);
        $dbOrderId = $row->fetchColumn();

        if ($dbOrderId) {
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                VALUES (?, 'Livrée', 'Réception confirmée par l''acheteur', 'buyer')
            ")->execute([$dbOrderId]);

            // Libération immédiate des fonds
            $db->prepare("
                UPDATE orders SET escrow_status = 'fonds_libérés', escrow_released_at = NOW()
                WHERE id = ?
            ")->execute([$dbOrderId]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ── POST delete_order (admin) ───────────────────────────────────────────
    if ($action === 'delete_order') {
        $orderId = $body['order_id'] ?? '';
        if (!$orderId) {
            echo json_encode(['success' => false, 'error' => 'order_id manquant']);
            exit;
        }
        $db->prepare("DELETE FROM orders WHERE id::text = ? OR order_number = ?")
           ->execute([$orderId, $orderId]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);

} catch (Exception $e) {
    error_log("❌ api_commandes.php — " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Helper PHP pour COALESCE sur string
function COALESCE_str($val) {
    return $val === null ? '' : (string)$val;
}
?>
