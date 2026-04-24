<?php
// ==================== API COMMANDES — ROUTER ====================
// Ce fichier gère deux types de requêtes :
//   • GET  → API REST (list commandes, notifications)
//   • POST + HTTP_STRIPE_SIGNATURE → Webhook Stripe (création commande)
//   • POST sans signature → Actions app (update_status, expédition, etc.)

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/notify_helpers.php';

// ── CORS ──────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$method = $_SERVER['REQUEST_METHOD'];

// ─── ROUTER ──────────────────────────────────────────────────────
if ($method === 'GET') {
    header('Content-Type: application/json');
    handleApiGet();
    exit();
}

if ($method === 'POST') {
    // Webhook Stripe = présence du header de signature
    if (!empty($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
        handleStripeWebhook();
        exit();
    }
    // Sinon → appel depuis l'app
    header('Content-Type: application/json');
    handleApiPost();
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit();

// ═══════════════════════════════════════════════════════════════════
// GET — API REST
// ═══════════════════════════════════════════════════════════════════
function handleApiGet() {
    $action = $_GET['action'] ?? '';

    try {
        $db = getDatabase();

        // ── action=list ──────────────────────────────────────────
        if ($action === 'list') {
            $isAdmin     = !empty($_GET['admin']);
            $artist_id   = trim($_GET['artist_id']   ?? '');
            $artist_name = trim($_GET['artist_name'] ?? '');

            // Récupérer toutes les commandes avec leurs items (JSON agrégé)
            $sql = "
                SELECT
                    o.id, o.order_number, o.user_id, o.user_name, o.user_email,
                    o.status, o.escrow_status, o.escrow_auto_release_date,
                    o.subtotal, o.tax, o.shipping_cost, o.shipping_name,
                    o.shipping_address, o.payment_method, o.total,
                    o.commission_amount, o.artist_payout,
                    o.tracking_number, o.carrier, o.shipping_proof_url,
                    o.created_at, o.updated_at,
                    COALESCE(
                        json_agg(
                            json_build_object(
                                'id',          oi.id,
                                'artwork_id',  oi.artwork_id,
                                'title',       oi.title,
                                'artist',      oi.artist_name,
                                'artist_name', oi.artist_name,
                                'artist_id',   oi.artist_id,
                                'price',       oi.price,
                                'quantity',    oi.quantity,
                                'image',       oi.image_url,
                                'image_url',   oi.image_url
                            )
                        ) FILTER (WHERE oi.id IS NOT NULL),
                        '[]'
                    ) AS items
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ";

            $stmt = $db->query($sql);
            $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Décoder les items JSON
            foreach ($allOrders as &$order) {
                $order['items'] = json_decode($order['items'] ?? '[]', true) ?: [];
            }
            unset($order);

            // ── Filtre artiste ──────────────────────────────────
            // ✅ FIX : on filtre par artist_id (BDD) OU artist_name
            // car le front envoie tantôt l'un, tantôt l'autre
            if (!$isAdmin && ($artist_id !== '' || $artist_name !== '')) {
                $allOrders = array_values(array_filter($allOrders, function($order) use ($artist_id, $artist_name) {
                    foreach ($order['items'] as $item) {
                        $itemArtistId   = (string)($item['artist_id']   ?? '');
                        $itemArtistName = mb_strtolower(trim($item['artist_name'] ?? $item['artist'] ?? ''));
                        $queryName      = mb_strtolower(trim($artist_name));

                        $matchById   = $artist_id !== '' && $itemArtistId === $artist_id;
                        $matchByName = $queryName  !== '' && $itemArtistName === $queryName;

                        if ($matchById || $matchByName) return true;
                    }
                    return false;
                }));
            }

            echo json_encode(['success' => true, 'orders' => $allOrders]);
            return;
        }

        // ── action=get_notifications ─────────────────────────────
        if ($action === 'get_notifications') {
            $artist_id   = trim($_GET['artist_id']   ?? '');
            $artist_name = trim($_GET['artist_name'] ?? '');

            // Créer la table notifications si elle n'existe pas encore
            $db->exec("
                CREATE TABLE IF NOT EXISTS artist_notifications (
                    id           SERIAL PRIMARY KEY,
                    artist_id    VARCHAR(255),
                    artist_name  VARCHAR(255),
                    order_id     INTEGER,
                    order_number VARCHAR(255),
                    title        VARCHAR(255),
                    message      TEXT,
                    is_read      BOOLEAN DEFAULT FALSE,
                    created_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // ✅ FIX : chercher par artist_id OU artist_name
            $where  = [];
            $params = [];
            if ($artist_id !== '') {
                $where[]  = 'artist_id = :artist_id';
                $params[':artist_id'] = $artist_id;
            }
            if ($artist_name !== '') {
                $where[]  = 'LOWER(artist_name) = LOWER(:artist_name)';
                $params[':artist_name'] = $artist_name;
            }
            $whereSQL = $where ? 'WHERE (' . implode(' OR ', $where) . ')' : 'WHERE 1=0';

            $stmt = $db->prepare("
                SELECT id, order_id, order_number, title, message, is_read, created_at
                FROM artist_notifications
                $whereSQL
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute($params);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $unread = count(array_filter($notifs, fn($n) => !$n['is_read']));

            echo json_encode([
                'success'      => true,
                'notifications' => $notifs,
                'unread_count' => $unread
            ]);
            return;
        }

        echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);

    } catch (Exception $e) {
        error_log('❌ api_commandes GET : ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// POST — Actions depuis l'app (non-webhook)
// ═══════════════════════════════════════════════════════════════════
function handleApiPost() {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true) ?: [];
    $action = $body['action'] ?? '';

    try {
        $db = getDatabase();

        // ── Migrations minimales ──────────────────────────────────
        $migrations = [
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_status VARCHAR(50) DEFAULT 'payée_en_attente'",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(255)",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier VARCHAR(100)",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_by VARCHAR(255)",
            "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
            "CREATE TABLE IF NOT EXISTS order_timeline (
                id SERIAL PRIMARY KEY, order_id INTEGER, status VARCHAR(100),
                note TEXT, updated_by_role VARCHAR(50), created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS artist_notifications (
                id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
                order_id INTEGER, order_number VARCHAR(255), title VARCHAR(255),
                message TEXT, is_read BOOLEAN DEFAULT FALSE, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )",
        ];
        foreach ($migrations as $sql) {
            try { $db->exec($sql); } catch (Exception $e) {}
        }

        // ── update_status (admin) ─────────────────────────────────
        if ($action === 'update_status') {
            $order_id     = $body['order_id'] ?? '';
            $status       = $body['status'] ?? '';
            $escrow       = $body['escrow_status'] ?? null;
            $tracking     = $body['tracking_number'] ?? null;
            $carrier      = $body['carrier'] ?? null;
            $note         = $body['note'] ?? null;
            $updated_by   = $body['updated_by'] ?? 'admin';
            $updated_role = $body['updated_by_role'] ?? 'admin';
            $proof_url    = $body['shipping_proof_url'] ?? null;

            if (!$order_id || !$status) {
                echo json_encode(['success' => false, 'error' => 'order_id et status requis']);
                return;
            }

            $stmt = $db->prepare("
                UPDATE orders SET
                    status = :status,
                    escrow_status = COALESCE(:escrow, escrow_status),
                    tracking_number = COALESCE(:tracking, tracking_number),
                    carrier = COALESCE(:carrier, carrier),
                    shipping_proof_url = COALESCE(:proof, shipping_proof_url),
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :order_id OR order_number = :order_number
                RETURNING id, order_number
            ");
            $stmt->execute([
                ':status'   => $status,
                ':escrow'   => $escrow,
                ':tracking' => $tracking,
                ':carrier'  => $carrier,
                ':proof'    => $proof_url,
                ':updated_by' => $updated_by,
                ':order_id' => $order_id,
                ':order_number' => $order_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $note) {
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, :status, :note, :role)
                ")->execute([':oid' => $row['id'], ':status' => $status, ':note' => $note, ':role' => $updated_role]);
            }

            echo json_encode(['success' => (bool)$row]);
            return;
        }

        // ── update_shipping (artiste confirme expédition) ─────────
        if ($action === 'update_shipping') {
            $order_id = $body['order_id'] ?? '';
            $tracking = $body['tracking_number'] ?? null;
            $carrier  = $body['carrier'] ?? null;
            $note     = $body['note'] ?? 'Commande expédiée par l\'artiste';
            $proof    = $body['shipping_proof_url'] ?? null;

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            $stmt = $db->prepare("
                UPDATE orders SET
                    status = 'Expédiée',
                    escrow_status = 'expédiée',
                    tracking_number = COALESCE(:tracking, tracking_number),
                    carrier = COALESCE(:carrier, carrier),
                    shipping_proof_url = COALESCE(:proof, shipping_proof_url),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :oid OR order_number = :onum
                RETURNING id, order_number, user_id
            ");
            $stmt->execute([
                ':tracking' => $tracking,
                ':carrier'  => $carrier,
                ':proof'    => $proof,
                ':oid'      => $order_id,
                ':onum'     => $order_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, 'Expédiée', :note, 'artist')
                ")->execute([':oid' => $row['id'], ':note' => $note]);
            }

            echo json_encode(['success' => (bool)$row, 'order' => $row]);
            return;
        }

        // ── confirm_reception (client confirme réception) ─────────
        if ($action === 'confirm_reception') {
            $order_id = $body['order_id'] ?? '';
            $user_id  = $body['user_id']  ?? '';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            $stmt = $db->prepare("
                UPDATE orders SET
                    status = 'Livrée',
                    escrow_status = 'fonds_libérés',
                    updated_at = CURRENT_TIMESTAMP
                WHERE (id = :oid OR order_number = :onum)
                  AND (user_id = :uid OR :uid = '')
                RETURNING id, order_number
            ");
            $stmt->execute([':oid' => $order_id, ':onum' => $order_id, ':uid' => $user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, 'Livrée', 'Réception confirmée par le client — fonds libérés', 'client')
                ")->execute([':oid' => $row['id']]);
            }

            echo json_encode(['success' => (bool)$row]);
            return;
        }

        // ── refuser_commande (artiste refuse) ─────────────────────
        if ($action === 'refuser_commande') {
            $order_id    = $body['order_id'] ?? '';
            $raison      = $body['raison'] ?? 'Refus sans motif';
            $artist_name = $body['artist_name'] ?? '';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            $stmt = $db->prepare("
                UPDATE orders SET
                    status = 'Annulée',
                    escrow_status = 'refusée',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :oid OR order_number = :onum
                RETURNING id, order_number, user_id, user_email
            ");
            $stmt->execute([':oid' => $order_id, ':onum' => $order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, 'Annulée', :note, 'artist')
                ")->execute([':oid' => $row['id'], ':note' => "Refus artiste : $raison"]);
            }

            echo json_encode(['success' => (bool)$row]);
            return;
        }

        // ── mark_notifications_read ───────────────────────────────
        if ($action === 'mark_notifications_read') {
            $artist_id   = $body['artist_id']   ?? '';
            $artist_name = $body['artist_name'] ?? '';

            $db->prepare("
                UPDATE artist_notifications
                SET is_read = TRUE
                WHERE artist_id = :aid OR LOWER(artist_name) = LOWER(:aname)
            ")->execute([':aid' => $artist_id, ':aname' => $artist_name]);

            echo json_encode(['success' => true]);
            return;
        }

        // ── create (fallback manuel, hors webhook Stripe) ─────────
        if ($action === 'create') {
            // Traité uniquement si l'ordre n'existe pas encore (idempotence)
            $order_number = $body['order_number'] ?? $body['order_id'] ?? '';
            if ($order_number) {
                $check = $db->prepare("SELECT id FROM orders WHERE order_number = :on LIMIT 1");
                $check->execute([':on' => $order_number]);
                if ($check->fetch()) {
                    echo json_encode(['success' => true, 'info' => 'already_exists']);
                    return;
                }
            }
            // Création simplifiée (le webhook Stripe est la voie principale)
            $stmt = $db->prepare("
                INSERT INTO orders
                    (order_number, user_id, user_name, user_email, status, escrow_status,
                     subtotal, tax, shipping_cost, shipping_name, shipping_address,
                     payment_method, total, created_at)
                VALUES
                    (:on, :uid, :uname, :uemail, 'En préparation', 'payée_en_attente',
                     :sub, :tax, :ship, :sname, :saddr, :pm, :total, CURRENT_TIMESTAMP)
                RETURNING id, order_number
            ");
            $stmt->execute([
                ':on'    => $order_number ?: ('ARKYL-' . strtoupper(substr(uniqid(), -8))),
                ':uid'   => $body['user_id']   ?? '',
                ':uname' => $body['user_name']  ?? '',
                ':uemail'=> $body['user_email'] ?? '',
                ':sub'   => $body['subtotal']   ?? 0,
                ':tax'   => $body['tax']        ?? 0,
                ':ship'  => $body['shipping_cost'] ?? 0,
                ':sname' => $body['shipping_name'] ?? '',
                ':saddr' => $body['shipping_address'] ?? '',
                ':pm'    => $body['payment_method'] ?? 'Stripe',
                ':total' => $body['total'] ?? 0,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insérer les items
            if ($row && !empty($body['items'])) {
                $ins = $db->prepare("
                    INSERT INTO order_items
                        (order_id, artwork_id, title, artist_name, artist_id, price, quantity, image_url)
                    VALUES (:oid, :aid, :title, :aname, :artid, :price, :qty, :img)
                ");
                foreach ($body['items'] as $item) {
                    $ins->execute([
                        ':oid'   => $row['id'],
                        ':aid'   => $item['artwork_id'] ?? $item['id'] ?? null,
                        ':title' => $item['title'] ?? '',
                        ':aname' => $item['artist_name'] ?? $item['artist'] ?? '',
                        ':artid' => (string)($item['artist_id'] ?? ''),
                        ':price' => $item['price'] ?? 0,
                        ':qty'   => $item['quantity'] ?? 1,
                        ':img'   => $item['image_url'] ?? $item['image'] ?? '',
                    ]);
                }
            }

            echo json_encode([
                'success'      => (bool)$row,
                'order_id'     => $row['id'] ?? null,
                'order_number' => $row['order_number'] ?? null,
            ]);
            return;
        }

        echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);

    } catch (Exception $e) {
        error_log('❌ api_commandes POST : ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// POST — Webhook Stripe (signature vérifiée)
// ═══════════════════════════════════════════════════════════════════
function handleStripeWebhook() {
    \Stripe\Stripe::setApiKey('sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ');
    $endpoint_secret = 'whsec_yjPEMxUgwPmuDWvS48z4fFQz7PpqcLaP';

    $payload    = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\UnexpectedValueException $e) {
        http_response_code(400); exit();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400); exit();
    }

    if ($event->type !== 'checkout.session.completed') {
        http_response_code(200);
        echo json_encode(['received' => true]);
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

    // ─────────────────────────────────────────────────────────────────
    // ÉTAPE 7 — Notifier chaque artiste via notifyArtists() (notify_helpers.php)
    // Email + notification BDD avec les mêmes templates que api_commandes.php
    // ─────────────────────────────────────────────────────────────────
    try {
        notifyArtists(
            $db,
            $new_order_db_id,  // INTEGER id de la commande en BDD
            $order_id,         // numéro lisible ex: ARKYL-XXXXXXXX
            $cartItems,        // items avec artist_id, title, price, quantity
            $user_name ?: $user_email,
            $subtotal_fcfa + $shipping_cost,
            $shipping_address,
            $shipping_mode
        );
    } catch (Exception $e) {
        error_log("⚠️ Webhook ARKYL — notifyArtists échouée : " . $e->getMessage());
    }

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

    // Toujours répondre 200 à Stripe
    http_response_code(200);
    echo json_encode(['received' => true]);
} // end handleStripeWebhook
?>
