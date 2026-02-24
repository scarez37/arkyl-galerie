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

function ensureTables($db) {
    // Ajouter colonnes manquantes si la table existe déjà (migration safe)
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(255)");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_url VARCHAR(500)");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier VARCHAR(100)");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes TEXT");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMPTZ");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMPTZ");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS confirmed_at TIMESTAMPTZ");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_released_at TIMESTAMPTZ");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_auto_release_date TIMESTAMPTZ");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_by VARCHAR(50)");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_status VARCHAR(50) DEFAULT 'payée_en_attente'");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_mode VARCHAR(50)");
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT");
    } catch (Exception $e) { /* colonnes déjà présentes */ }

    // Table orders
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
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
            shipped_at TIMESTAMPTZ,
            delivered_at TIMESTAMPTZ,
            confirmed_at TIMESTAMPTZ,
            escrow_released_at TIMESTAMPTZ,
            escrow_auto_release_date TIMESTAMPTZ,
            updated_by VARCHAR(50),
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    // Table order_items
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id SERIAL PRIMARY KEY,
            order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
            artwork_id INTEGER,
            title VARCHAR(255),
            artist_name VARCHAR(255),
            artist_id VARCHAR(255),
            price NUMERIC(12,2),
            quantity INTEGER DEFAULT 1,
            image_url TEXT
        )
    ");
    // Migration : ajouter artist_id si la colonne n'existe pas encore
    $db->exec("
        DO \$\$ BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name='order_items' AND column_name='artist_id'
            ) THEN
                ALTER TABLE order_items ADD COLUMN artist_id VARCHAR(255);
            END IF;
        END \$\$;
    ");

    // Table order_timeline (historique des changements de statut)
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_timeline (
            id SERIAL PRIMARY KEY,
            order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
            status VARCHAR(50),
            note TEXT,
            updated_by VARCHAR(255),
            updated_by_role VARCHAR(50),
            created_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");
}

try {
    $db = getDatabase();
    ensureTables($db);

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $action = $body['action'] ?? '';
    } else {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // ── GET list ────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $userId   = $_GET['user_id']   ?? '';
        $artistId = $_GET['artist_id'] ?? '';
        $isAdmin  = isset($_GET['admin']) && $_GET['admin'] == '1';

        if ($isAdmin) {
            $stmt = $db->query("
                SELECT o.*, 
                    json_agg(json_build_object(
                        'id', oi.id, 'artwork_id', oi.artwork_id, 'title', oi.title,
                        'artist', oi.artist_name, 'artist_id', oi.artist_id,
                        'price', oi.price, 'quantity', oi.quantity, 'image', oi.image_url
                    ) ORDER BY oi.id) as items
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
        } elseif ($artistId) {
            $stmt = $db->prepare("
                SELECT DISTINCT o.*,
                    json_agg(json_build_object(
                        'id', oi.id, 'artwork_id', oi.artwork_id, 'title', oi.title,
                        'artist', oi.artist_name, 'artist_id', oi.artist_id,
                        'price', oi.price, 'quantity', oi.quantity, 'image', oi.image_url
                    ) ORDER BY oi.id) as items
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id AND oi.artist_id = :aid
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([':aid' => $artistId]);
        } else {
            $stmt = $db->prepare("
                SELECT o.*,
                    json_agg(json_build_object(
                        'id', oi.id, 'artwork_id', oi.artwork_id, 'title', oi.title,
                        'artist', oi.artist_name, 'artist_id', oi.artist_id,
                        'price', oi.price, 'quantity', oi.quantity, 'image', oi.image_url
                    ) ORDER BY oi.id) as items
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = :uid
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([':uid' => $userId]);
        }

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Décoder items JSON + timeline
        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items'] ?? '[]', true) ?: [];

            // Timeline de cette commande
            $tl = $db->prepare("SELECT * FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC");
            $tl->execute([$order['id']]);
            $order['timeline'] = $tl->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }

    // ── GET single ──────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $orderId = $_GET['order_id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? OR order_number = ?");
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) { echo json_encode(['success' => false, 'error' => 'Commande non trouvée']); exit; }

        $items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);

        $tl = $db->prepare("SELECT * FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC");
        $tl->execute([$order['id']]);
        $order['timeline'] = $tl->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'order' => $order]);
        exit;
    }

    // ── POST create ─────────────────────────────────────────────────────────
    if ($action === 'create') {
        $orderNum = 'ARK-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $stmt = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, user_name, user_email,
                status, escrow_status, subtotal, tax, shipping_cost, total,
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
            ':uid'             => $body['user_id'] ?? '',
            ':uname'           => $body['user_name'] ?? '',
            ':uemail'          => $body['user_email'] ?? '',
            ':subtotal'        => $body['subtotal'] ?? 0,
            ':tax'             => $body['tax'] ?? 0,
            ':shipping_cost'   => $body['shipping_cost'] ?? 0,
            ':total'           => $body['total'] ?? 0,
            ':shipping_name'   => $body['shipping_name'] ?? '',
            ':shipping_mode'   => $body['shipping_mode'] ?? '',
            ':shipping_address'=> $body['shipping_address'] ?? '',
            ':payment_method'  => $body['payment_method'] ?? '',
        ]);
        $orderId = $stmt->fetchColumn();

        // Insérer les articles
        foreach (($body['items'] ?? []) as $item) {
            $db->prepare("
                INSERT INTO order_items (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $orderId,
                $item['artwork_id'] ?? $item['id'] ?? null,
                $item['title'] ?? '',
                $item['artist'] ?? '',
                $item['artist_id'] ?? '',
                $item['price'] ?? 0,
                $item['quantity'] ?? 1,
                $item['image'] ?? $item['image_url'] ?? '',
            ]);
        }

        // Premier event timeline
        $db->prepare("INSERT INTO order_timeline (order_id, status, note, updated_by_role) VALUES (?, 'En préparation', 'Commande créée et paiement validé', 'system')")
           ->execute([$orderId]);

        echo json_encode(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNum]);
        exit;
    }

    // ── POST update_status ──────────────────────────────────────────────────
    if ($action === 'update_status') {
        $orderId        = $body['order_id'] ?? '';
        $newStatus      = $body['status'] ?? '';
        $trackingNumber = $body['tracking_number'] ?? null;
        $trackingUrl    = $body['tracking_url'] ?? null;
        $carrier        = $body['carrier'] ?? null;
        $note           = $body['note'] ?? null;
        $updatedBy      = $body['updated_by'] ?? '';
        $updatedByRole  = $body['updated_by_role'] ?? 'admin'; // 'admin' | 'artist'
        $proofUrl       = $body['shipping_proof_url'] ?? null;

        // Mapping statut → escrow_status
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
        $params = [':status' => $newStatus, ':escrow' => $escrowStatus, ':by' => $updatedByRole, ':id' => $orderId];

        if ($trackingNumber) { $setFields .= ", tracking_number = :tn"; $params[':tn'] = $trackingNumber; }
        if ($trackingUrl)    { $setFields .= ", tracking_url = :tu";    $params[':tu'] = $trackingUrl; }
        if ($carrier)        { $setFields .= ", carrier = :carrier";    $params[':carrier'] = $carrier; }
        if ($proofUrl)       { $setFields .= ", shipping_proof_url = :proof"; $params[':proof'] = $proofUrl; }

        // Timestamps automatiques
        if ($newStatus === 'Expédiée' || $newStatus === 'En transit') {
            $setFields .= ", shipped_at = NOW()";
            $autoRelease = date('Y-m-d H:i:s', strtotime('+21 days'));
            $setFields .= ", escrow_auto_release_date = '$autoRelease'";
        }
        if ($newStatus === 'Livrée') {
            $setFields .= ", delivered_at = NOW()";
        }

        $db->prepare("UPDATE orders SET $setFields WHERE id = :id OR order_number = :id")
           ->execute($params);

        // Ajouter à la timeline
        $tlNote = $note ?: "Statut mis à jour : $newStatus";
        if ($trackingNumber) $tlNote .= " | Tracking: $trackingNumber";
        if ($carrier) $tlNote .= " ($carrier)";

        $db->prepare("INSERT INTO order_timeline (order_id, status, note, updated_by, updated_by_role) VALUES (
            (SELECT id FROM orders WHERE id = ? OR order_number = ? LIMIT 1), ?, ?, ?, ?
        )")->execute([$orderId, $orderId, $newStatus, $tlNote, $updatedByRole]);

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
            WHERE id = ? OR order_number = ?
        ")->execute([$orderId, $orderId]);

        $db->prepare("INSERT INTO order_timeline (order_id, status, note, updated_by_role) VALUES (
            (SELECT id FROM orders WHERE id = ? OR order_number = ? LIMIT 1),
            'Livrée', 'Réception confirmée par l\\'acheteur', 'buyer'
        )")->execute([$orderId, $orderId]);

        // Libération fonds après 2 secondes (simulé par flag)
        $db->prepare("
            UPDATE orders SET escrow_status = 'fonds_libérés', escrow_released_at = NOW()
            WHERE id = ? OR order_number = ?
        ")->execute([$orderId, $orderId]);

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
        // ON DELETE CASCADE supprime automatiquement order_items et order_timeline
        $stmt = $db->prepare("DELETE FROM orders WHERE id = ? OR order_number = ?");
        $stmt->execute([$orderId, $orderId]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
