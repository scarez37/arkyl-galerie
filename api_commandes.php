<?php
// ==================== API COMMANDES — ROUTER ====================

// ═══════════════════════════════════════════════════════════════
// CORS ABSOLUMENT EN PREMIER — ob_start() pour capturer toute
// sortie parasite (notices PHP, BOM, whitespace) qui empêcherait
// les header() de s'exécuter. Le shutdown handler garantit les
// headers CORS même en cas de fatal error dans les require_once.
// ═══════════════════════════════════════════════════════════════
ob_start();

// Supprimer TOUTE sortie parasite avant les headers
function sendCorsHeaders() {
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Cross-Origin-Embedder-Policy: unsafe-none');
        header('Cross-Origin-Opener-Policy: unsafe-none');
        header('Cross-Origin-Resource-Policy: cross-origin');
    }
}

// Envoyer les headers CORS en cas de fatal error aussi
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Fatal error — vider le buffer et renvoyer CORS + JSON d'erreur
        if (ob_get_level() > 0) ob_end_clean();
        sendCorsHeaders();
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $error['message']]);
    } else {
        // Fin normale — flush le buffer si encore actif
        if (ob_get_level() > 0) ob_end_flush();
    }
});

sendCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); ob_end_clean(); exit(); }

// ── REQUIRES ─────────────────────────────────────────────────────
require_once __DIR__ . '/db_config.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/notify_helpers.php';

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
    // ✅ FIX CORS : envoyer Content-Type immédiatement — AVANT tout appel DB
    // Ainsi même si getDatabase() plante, le navigateur reçoit les headers CORS
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $action = $_GET['action'] ?? '';

    try {
        $db = getDatabase();

        // ── action=list ──────────────────────────────────────────
        if ($action === 'list') {
            $isAdmin     = !empty($_GET['admin']);
            $artist_id   = trim($_GET['artist_id']   ?? '');
            $artist_name = trim($_GET['artist_name'] ?? '');
            $user_id     = trim($_GET['user_id']     ?? ''); // ✅ filtre acheteur

            // ── Contrôle d'accès : refuser si aucun contexte valide ────
            // Un appel sans admin=1, sans user_id et sans artist_id/name n'a pas de sens
            if (!$isAdmin && $user_id === '' && $artist_id === '' && $artist_name === '') {
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
                return;
            }

            // ── Migration de sécurité : s'assurer que les colonnes existent ──
            // (au cas où fix_orders_table.php n'a pas encore été exécuté en production)
            try {
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_name VARCHAR(255)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_email VARCHAR(255)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_name VARCHAR(255)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(255)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier VARCHAR(100)");
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT");
                $db->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)");
            } catch (Exception $migEx) {
                error_log('Migration auto orders: ' . $migEx->getMessage());
            }

            // ── Construction de la requête SQL avec filtrage direct en BDD ──
            // Pour éviter de tout charger puis filtrer en PHP (fuite de données)
            $whereClauses = [];
            $sqlParams    = [];

            if (!$isAdmin) {
                if ($user_id !== '') {
                    // Acheteur : uniquement SES commandes
                    $whereClauses[] = 'o.user_id = :user_id';
                    $sqlParams[':user_id'] = $user_id;
                } elseif ($artist_id !== '' || $artist_name !== '') {
                    // Artiste : uniquement les commandes qui contiennent SES œuvres
                    // On filtre via une sous-requête sur order_items
                    $artistSub = [];
                    if ($artist_id !== '') {
                        $artistSub[] = 'oi2.artist_id = :artist_id';
                        $sqlParams[':artist_id'] = $artist_id;
                    }
                    if ($artist_name !== '') {
                        $artistSub[] = 'LOWER(oi2.artist_name) = LOWER(:artist_name)';
                        $sqlParams[':artist_name'] = $artist_name;
                    }
                    $artistSubSQL = implode(' OR ', $artistSub);
                    $whereClauses[] = "EXISTS (
                        SELECT 1 FROM order_items oi2
                        WHERE oi2.order_id = o.id AND ($artistSubSQL)
                    )";
                }
            }

            $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            $cols = "
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
                    ) AS items,
                    COALESCE(
                        (
                            SELECT json_agg(
                                json_build_object(
                                    'status',          tl.status,
                                    'note',            tl.note,
                                    'updated_by_role', tl.updated_by_role,
                                    'created_at',      tl.created_at
                                ) ORDER BY tl.created_at ASC
                            )
                            FROM order_timeline tl
                            WHERE tl.order_id = o.id
                        ),
                        '[]'
                    ) AS timeline
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                $whereSQL
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ";

            $stmt = $db->prepare($cols);
            $stmt->execute($sqlParams);
            $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Décoder les items JSON
            foreach ($allOrders as &$order) {
                $order['items']    = json_decode($order['items']    ?? '[]', true) ?: [];
                $order['timeline'] = json_decode($order['timeline'] ?? '[]', true) ?: [];
                // ── Pour un artiste : masquer l'adresse de livraison complète si pas admin
                // L'artiste voit l'adresse de livraison (il doit expédier)
                // Mais on retire les infos de paiement sensibles
                if (!$isAdmin && $user_id !== '') {
                    // Acheteur : masquer les infos artiste/commission
                    unset($order['commission_amount'], $order['artist_payout']);
                }
            }
            unset($order);

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
                    type         VARCHAR(100) DEFAULT 'new_order',
                    order_id     INTEGER,
                    order_number VARCHAR(255),
                    title        VARCHAR(255),
                    message      TEXT,
                    is_read      BOOLEAN DEFAULT FALSE,
                    created_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                )
            ");
            // Migration: ajouter colonne type si absente (tables créées avant ce fix)
            try { $db->exec("ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'"); } catch (Exception $e) {}

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

        // ── action=get_timeline ──────────────────────────────
        // Retourne la timeline d'une commande : accessible uniquement à
        // l'acheteur (user_id) OU à l'artiste concerné (artist_id/name) OU admin
        if ($action === 'get_timeline') {
            $order_number = trim($_GET['order_number'] ?? '');
            $order_id_q   = trim($_GET['order_id']     ?? '');
            $req_user_id  = trim($_GET['user_id']      ?? '');
            $req_artist_id = trim($_GET['artist_id']   ?? '');
            $req_artist_name = trim($_GET['artist_name'] ?? '');
            $isAdminTl    = !empty($_GET['admin']);

            if (!$order_number && !$order_id_q) {
                echo json_encode(['success' => false, 'error' => 'order_number ou order_id requis']);
                return;
            }

            // Récupérer la commande
            $q = $db->prepare("
                SELECT o.id, o.order_number, o.user_id, o.artist_id, o.status, o.escrow_status,
                       o.tracking_number, o.carrier, o.shipping_address, o.shipping_name
                FROM orders o
                WHERE o.order_number = :on OR o.id::text = :oid
                LIMIT 1
            ");
            $q->execute([':on' => $order_number, ':oid' => $order_id_q ?: '-1']);
            $order = $q->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                return;
            }

            // ── Vérification accès ──────────────────────────────────────
            $isOwner     = $req_user_id !== '' && (string)$order['user_id'] === $req_user_id;
            $isArtistTl  = false;
            if ($req_artist_id !== '' || $req_artist_name !== '') {
                // Vérifier qu'un item de la commande appartient bien à cet artiste
                $aCheck = $db->prepare("
                    SELECT 1 FROM order_items oi
                    WHERE oi.order_id = :oid
                      AND (oi.artist_id = :aid OR LOWER(oi.artist_name) = LOWER(:aname))
                    LIMIT 1
                ");
                $aCheck->execute([':oid' => $order['id'], ':aid' => $req_artist_id, ':aname' => $req_artist_name]);
                $isArtistTl = (bool)$aCheck->fetch();
            }

            if (!$isAdminTl && !$isOwner && !$isArtistTl) {
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé à cette commande']);
                return;
            }

            // Récupérer la timeline
            $tlStmt = $db->prepare("
                SELECT status, note, updated_by_role, created_at
                FROM order_timeline
                WHERE order_id = :oid
                ORDER BY created_at ASC
            ");
            $tlStmt->execute([':oid' => $order['id']]);
            $timeline = $tlStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'timeline' => $timeline,
                'order'    => [
                    'id'              => $order['id'],
                    'order_number'    => $order['order_number'],
                    'status'          => $order['status'],
                    'escrow_status'   => $order['escrow_status'],
                    'tracking_number' => $order['tracking_number'],
                    'carrier'         => $order['carrier'],
                    // Adresse visible uniquement par artiste et admin (pas client)
                    'shipping_address' => ($isArtistTl || $isAdminTl) ? $order['shipping_address'] : null,
                    'shipping_name'    => $order['shipping_name'],
                ],
            ]);
            return;
        }

        // ── action=list_transactions ──────────────────────────────
        if ($action === 'list_transactions') {
            $limit = intval($_GET['limit'] ?? 50);
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS artist_payouts (
                        id SERIAL PRIMARY KEY, order_id INTEGER, order_number VARCHAR(255),
                        artist_id VARCHAR(255), artist_name VARCHAR(255),
                        amount_artwork NUMERIC(12,2) DEFAULT 0, amount_shipping NUMERIC(12,2) DEFAULT 0,
                        amount_total NUMERIC(12,2) DEFAULT 0, payment_method VARCHAR(100),
                        payment_reference VARCHAR(255), payment_note TEXT, paid_by VARCHAR(255),
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $stmt = $db->prepare('SELECT * FROM artist_payouts ORDER BY created_at DESC LIMIT :lim');
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                echo json_encode(['success' => true, 'transactions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (Exception $e2) {
                echo json_encode(['success' => true, 'transactions' => []]);
            }
            return;
        }

        echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);

    } catch (Exception $e) {
        error_log('❌ api_commandes GET : ' . $e->getMessage());
        // Garantir les headers CORS même en cas d'exception DB
        if (!headers_sent()) {
            sendCorsHeaders();
            header('Content-Type: application/json; charset=utf-8');
        }
        if (ob_get_level() > 0) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// POST — Actions depuis l'app (non-webhook)
// ═══════════════════════════════════════════════════════════════════
function handleApiPost() {
    // ✅ FIX CORS : Content-Type immédiatement avant tout traitement
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true) ?: [];
    $action = $body['action'] ?? '';

    try {
        $db = getDatabase();

        // ── Création des tables (si BDD vierge après perte Render) ────
        $db->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id                  SERIAL PRIMARY KEY,
                order_number        VARCHAR(255) UNIQUE,
                user_id             VARCHAR(255),
                user_name           VARCHAR(255),
                user_email          VARCHAR(255),
                artist_id           VARCHAR(255),
                status              VARCHAR(100) DEFAULT 'En préparation',
                escrow_status       VARCHAR(50)  DEFAULT 'payée_en_attente',
                escrow_auto_release_date TIMESTAMPTZ,
                subtotal            NUMERIC(12,2) DEFAULT 0,
                tax                 NUMERIC(12,2) DEFAULT 0,
                shipping_cost       NUMERIC(12,2) DEFAULT 0,
                shipping_name       VARCHAR(255),
                shipping_address    TEXT,
                payment_method      VARCHAR(100),
                total               NUMERIC(12,2) DEFAULT 0,
                commission_amount   NUMERIC(12,2) DEFAULT 0,
                artist_payout       NUMERIC(12,2) DEFAULT 0,
                stripe_session_id   VARCHAR(255),
                tracking_number     VARCHAR(255),
                carrier             VARCHAR(100),
                shipping_proof_url  TEXT,
                updated_by          VARCHAR(255),
                updated_at          TIMESTAMPTZ,
                created_at          TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_items (
                id          SERIAL PRIMARY KEY,
                order_id    INTEGER REFERENCES orders(id) ON DELETE CASCADE,
                artwork_id  INTEGER,
                title       VARCHAR(255),
                artist_name VARCHAR(255),
                artist_id   VARCHAR(255),
                price       NUMERIC(12,2) DEFAULT 0,
                quantity    INTEGER DEFAULT 1,
                image_url   TEXT,
                created_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS cart (
                id         SERIAL PRIMARY KEY,
                user_id    VARCHAR(255),
                artwork_id INTEGER,
                quantity   INTEGER DEFAULT 1,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, artwork_id)
            )
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS artworks (
                id               SERIAL PRIMARY KEY,
                title            VARCHAR(255),
                price            NUMERIC(12,2),
                category         VARCHAR(100),
                technique        VARCHAR(100),
                technique_custom VARCHAR(100),
                width            NUMERIC(8,2),
                height           NUMERIC(8,2),
                depth            NUMERIC(8,2),
                dimensions       TEXT,
                description      TEXT,
                artist_id        VARCHAR(255),
                artist_name      VARCHAR(255),
                artist_country   VARCHAR(100),
                badge            VARCHAR(100) DEFAULT 'Disponible',
                status           VARCHAR(50)  DEFAULT 'publiée',
                image_url        TEXT,
                photos           TEXT,
                weight_g         INTEGER DEFAULT 0,
                is_sold          BOOLEAN DEFAULT FALSE,
                sold_at          TIMESTAMPTZ,
                created_at       TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // ── Migrations additionnelles (colonnes ajoutées après création initiale) ─
        $migrations = [
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_status VARCHAR(50) DEFAULT 'payée_en_attente'",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(255)",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier VARCHAR(100)",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_by VARCHAR(255)",
            "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)",
            "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE",
            "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS sold_at TIMESTAMPTZ",
            "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS weight_g INTEGER DEFAULT 0",
            "CREATE TABLE IF NOT EXISTS order_timeline (
                id SERIAL PRIMARY KEY, order_id INTEGER, status VARCHAR(100),
                note TEXT, updated_by_role VARCHAR(50), created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS artist_notifications (
                id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
                type VARCHAR(100) DEFAULT 'new_order',
                order_id INTEGER, order_number VARCHAR(255), title VARCHAR(255),
                message TEXT, is_read BOOLEAN DEFAULT FALSE, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )",
            "ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'",
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

            if ($row) {
                // Retourner les données complètes de la commande avec timeline
                $fullStmt = $db->prepare("
                    SELECT o.*, COALESCE(
                        (SELECT json_agg(json_build_object('status',tl.status,'note',tl.note,'updated_by_role',tl.updated_by_role,'created_at',tl.created_at) ORDER BY tl.created_at ASC)
                         FROM order_timeline tl WHERE tl.order_id = o.id), '[]'
                    ) AS timeline
                    FROM orders o WHERE o.id = :oid
                ");
                $fullStmt->execute([':oid' => $row['id']]);
                $fullOrder = $fullStmt->fetch(PDO::FETCH_ASSOC);
                if ($fullOrder) $fullOrder['timeline'] = json_decode($fullOrder['timeline'] ?? '[]', true) ?: [];
            }

            echo json_encode(['success' => (bool)$row, 'order' => $fullOrder ?? $row]);
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

                // ── Notifier l'artiste que le client a confirmé la réception ──
                // Récupérer les infos commande + artiste pour la notification
                try {
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS artist_notifications (
                            id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
                            type VARCHAR(100) DEFAULT 'new_order', order_id INTEGER, order_number VARCHAR(255),
                            title VARCHAR(255), message TEXT, is_read BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    try { $db->exec("ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'"); } catch (Exception $ex) {}

                    // Récupérer les artistes liés à cette commande
                    $artStmt = $db->prepare("
                        SELECT DISTINCT oi.artist_id, oi.artist_name, o.order_number, o.user_name, o.artist_payout
                        FROM order_items oi
                        JOIN orders o ON o.id = oi.order_id
                        WHERE oi.order_id = :oid AND oi.artist_id IS NOT NULL
                    ");
                    $artStmt->execute([':oid' => $row['id']]);
                    $artRows = $artStmt->fetchAll(PDO::FETCH_ASSOC);

                    $orderNum   = $row['order_number'];
                    $notifStmt  = $db->prepare("
                        INSERT INTO artist_notifications (artist_id, artist_name, type, order_id, order_number, title, message)
                        VALUES (:artist_id, :artist_name, 'reception_confirmed', :order_id, :order_number, :title, :message)
                    ");

                    foreach ($artRows as $artRow) {
                        $buyer = $user_id ?: 'Le client';
                        $notifStmt->execute([
                            ':artist_id'    => $artRow['artist_id'],
                            ':artist_name'  => $artRow['artist_name'],
                            ':order_id'     => $row['id'],
                            ':order_number' => $orderNum,
                            ':title'        => '🎉 Réception confirmée — fonds libérés !',
                            ':message'      => "Le client a confirmé la réception de la commande #{$orderNum}. Vos fonds sont maintenant libérés. Merci pour cette belle vente !",
                        ]);
                    }
                } catch (Exception $notifEx) {
                    error_log("⚠️ Notification artiste échouée (confirm_reception) : " . $notifEx->getMessage());
                }
            }

            echo json_encode(['success' => (bool)$row, 'order_number' => $row['order_number'] ?? '']);
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


        // ── liberer_fonds (admin confirme le virement à l'artiste) ───
        if ($action === 'liberer_fonds') {
            $order_id          = $body['order_id']          ?? '';
            $payment_method    = $body['payment_method']    ?? 'Virement manuel';
            $payment_reference = $body['payment_reference'] ?? null;
            $payment_note      = $body['payment_note']      ?? null;
            $paid_by           = $body['paid_by']           ?? 'admin';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            // Récupérer la commande pour notifier l'artiste
            $orderStmt = $db->prepare("
                SELECT o.id, o.order_number, o.user_name, o.artist_payout, o.shipping_cost,
                       oi.artist_id, oi.artist_name
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.id = :oid OR o.order_number = :onum
                LIMIT 1
            ");
            $orderStmt->execute([':oid' => $order_id, ':onum' => $order_id]);
            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderData) {
                echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                return;
            }

            $dbOrderId  = $orderData['id'];
            $orderNum   = $orderData['order_number'];
            $artistId   = $orderData['artist_id'];
            $artistName = $orderData['artist_name'];
            $payout     = floatval($orderData['artist_payout'] ?? 0);
            $shipping   = floatval($orderData['shipping_cost'] ?? 0);
            $totalVerse = $payout + $shipping;

            // 1. Mettre à jour le statut de la commande
            $db->prepare("
                UPDATE orders SET
                    escrow_status = 'fonds_libérés',
                    status = 'Livrée',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :oid
            ")->execute([':oid' => $dbOrderId]);

            // 2. Enregistrer dans order_timeline
            $noteTimeline = "Fonds versés à l'artiste par l'admin ($paid_by)"
                . ($payment_method ? " via $payment_method" : '')
                . ($payment_reference ? " — Réf: $payment_reference" : '');
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                VALUES (:oid, 'Fonds libérés', :note, 'admin')
            ")->execute([':oid' => $dbOrderId, ':note' => $noteTimeline]);

            // 3. Enregistrer dans artist_payments (table de traçabilité)
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS artist_payments (
                        id SERIAL PRIMARY KEY,
                        order_id INTEGER,
                        order_number VARCHAR(255),
                        artist_id VARCHAR(255),
                        artist_name VARCHAR(255),
                        amount_artwork NUMERIC(12,2) DEFAULT 0,
                        amount_shipping NUMERIC(12,2) DEFAULT 0,
                        amount_total NUMERIC(12,2) DEFAULT 0,
                        payment_method VARCHAR(255),
                        payment_reference VARCHAR(255),
                        payment_note TEXT,
                        paid_by VARCHAR(255),
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $db->prepare("
                    INSERT INTO artist_payments
                        (order_id, order_number, artist_id, artist_name, amount_artwork, amount_shipping, amount_total, payment_method, payment_reference, payment_note, paid_by)
                    VALUES
                        (:oid, :onum, :aid, :aname, :amt_art, :amt_ship, :amt_total, :method, :ref, :note, :by)
                ")->execute([
                    ':oid'      => $dbOrderId,
                    ':onum'     => $orderNum,
                    ':aid'      => $artistId,
                    ':aname'    => $artistName,
                    ':amt_art'  => $payout,
                    ':amt_ship' => $shipping,
                    ':amt_total'=> $totalVerse,
                    ':method'   => $payment_method,
                    ':ref'      => $payment_reference,
                    ':note'     => $payment_note,
                    ':by'       => $paid_by,
                ]);
            } catch (Exception $e) {
                error_log("⚠️ artist_payments insert: " . $e->getMessage());
            }

            // 4. Notifier l'artiste via artist_notifications
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS artist_notifications (
                        id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
                        type VARCHAR(100) DEFAULT 'new_order', order_id INTEGER, order_number VARCHAR(255),
                        title VARCHAR(255), message TEXT, is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                try { $db->exec("ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'"); } catch (Exception $ex) {}
                $methodLabel = $payment_method ?: 'virement';
                $refLabel    = $payment_reference ? " (Réf: $payment_reference)" : '';
                $db->prepare("
                    INSERT INTO artist_notifications (artist_id, artist_name, type, order_id, order_number, title, message)
                    VALUES (:aid, :aname, 'fonds_liberes', :oid, :onum, :title, :message)
                ")->execute([
                    ':aid'     => $artistId,
                    ':aname'   => $artistName,
                    ':oid'     => $dbOrderId,
                    ':onum'    => $orderNum,
                    ':title'   => '💰 Fonds virés — ' . number_format($totalVerse, 0, ',', ' ') . ' FCFA',
                    ':message' => "L'admin a confirmé le virement de " . number_format($totalVerse, 0, ',', ' ') . " FCFA pour la commande #{$orderNum} via {$methodLabel}{$refLabel}.",
                ]);
            } catch (Exception $e) {
                error_log("⚠️ Notification artiste (liberer_fonds): " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'order_number' => $orderNum, 'amount' => $totalVerse]);
            return;
        }

        // ── list_transactions (historique des virements admin) ───────
        if ($action === 'list_transactions') {
            $limit = max(1, min(100, intval($_GET['limit'] ?? 30)));
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS artist_payments (
                        id SERIAL PRIMARY KEY, order_id INTEGER, order_number VARCHAR(255),
                        artist_id VARCHAR(255), artist_name VARCHAR(255),
                        amount_artwork NUMERIC(12,2) DEFAULT 0, amount_shipping NUMERIC(12,2) DEFAULT 0,
                        amount_total NUMERIC(12,2) DEFAULT 0, payment_method VARCHAR(255),
                        payment_reference VARCHAR(255), payment_note TEXT, paid_by VARCHAR(255),
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $stmt = $db->prepare("
                    SELECT ap.*, o.order_number AS ord_num
                    FROM artist_payments ap
                    LEFT JOIN orders o ON o.id = ap.order_id
                    ORDER BY ap.created_at DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $txs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'transactions' => $txs]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'transactions' => [], 'error' => $e->getMessage()]);
            }
            return;
        }

        // ── interrompre_livraison (admin bloque la procédure) ───────
        if ($action === 'interrompre_livraison') {
            $order_id   = $body['order_id']  ?? '';
            $raison     = $body['raison']    ?? 'Litige en cours — procédure bloquée par l'admin';
            $admin_name = $body['admin_name'] ?? 'admin';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            // Récupérer les infos de la commande pour notifier tous les intervenants
            $orderStmt = $db->prepare("
                SELECT o.id, o.order_number, o.user_id, o.user_name, o.user_email,
                       o.escrow_status, o.status,
                       oi.artist_id, oi.artist_name
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.id = :oid OR o.order_number = :onum
                LIMIT 1
            ");
            $orderStmt->execute([':oid' => $order_id, ':onum' => $order_id]);
            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderData) {
                echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                return;
            }

            $dbOrderId  = $orderData['id'];
            $orderNum   = $orderData['order_number'];
            $artistId   = $orderData['artist_id'];
            $artistName = $orderData['artist_name'];

            // 1. Bloquer la commande
            $db->prepare("
                UPDATE orders SET
                    escrow_status = 'litige',
                    status = 'En litige',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :oid
            ")->execute([':oid' => $dbOrderId]);

            // 2. Timeline
            $db->prepare("
                INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                VALUES (:oid, 'En litige', :note, 'admin')
            ")->execute([
                ':oid'  => $dbOrderId,
                ':note' => "Livraison interrompue par l'admin ($admin_name) : $raison",
            ]);

            // 3. Notifier l'artiste
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS artist_notifications (
                    id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
                    type VARCHAR(100) DEFAULT 'new_order', order_id INTEGER, order_number VARCHAR(255),
                    title VARCHAR(255), message TEXT, is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)");
                try { $db->exec("ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'"); } catch(Exception $ex) {}
                $db->prepare("
                    INSERT INTO artist_notifications (artist_id, artist_name, type, order_id, order_number, title, message)
                    VALUES (:aid, :aname, 'litige', :oid, :onum, :title, :msg)
                ")->execute([
                    ':aid'   => $artistId,
                    ':aname' => $artistName,
                    ':oid'   => $dbOrderId,
                    ':onum'  => $orderNum,
                    ':title' => '⚠️ Livraison interrompue — Commande #' . $orderNum,
                    ':msg'   => "L'admin a interrompu la procédure de la commande #{$orderNum}. Raison : {$raison}. Les fonds restent bloqués jusqu'à résolution. Contactez l'admin ARKYL pour plus d'informations.",
                ]);
            } catch (Exception $e) {
                error_log("⚠️ Notification artiste (interrompre): " . $e->getMessage());
            }

            echo json_encode([
                'success'      => true,
                'order_number' => $orderNum,
                'artist_id'    => $artistId,
                'artist_name'  => $artistName,
                'user_email'   => $orderData['user_email'],
            ]);
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

            // ✅ FIX : Marquer les œuvres comme vendues + notifier les artistes
            if ($row && !empty($body['items'])) {
                // Marquer chaque œuvre vendue
                $markSold = $db->prepare("
                    UPDATE artworks SET is_sold = TRUE, sold_at = NOW(), badge = 'Vendu', status = 'vendue'
                    WHERE id = :artwork_id
                ");
                foreach ($body['items'] as $item) {
                    $aid = $item['artwork_id'] ?? $item['id'] ?? null;
                    if ($aid) $markSold->execute([':artwork_id' => intval($aid)]);
                }

                // Notifier les artistes via notify_helpers
                require_once __DIR__ . '/notify_helpers.php';
                $orderNum   = $row['order_number'] ?? $order_number;
                $buyerName  = $body['user_name'] ?? $body['user_email'] ?? 'Client';
                $totalOrder = $body['total'] ?? 0;
                $shipAddr   = $body['shipping_address'] ?? '';
                $shipName   = $body['shipping_name'] ?? '';
                try {
                    notifyArtists($db, (int)$row['id'], $orderNum, $body['items'], $buyerName, $totalOrder, $shipAddr, $shipName);
                } catch (Exception $e) {
                    error_log('⚠️ notifyArtists (create fallback) : ' . $e->getMessage());
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
    \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ');
    $endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_yjPEMxUgwPmuDWvS48z4fFQz7PpqcLaP';

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

    // ── Création des tables si BDD vierge (perte Render) ─────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id SERIAL PRIMARY KEY, order_number VARCHAR(255) UNIQUE,
            user_id VARCHAR(255), user_name VARCHAR(255), user_email VARCHAR(255),
            artist_id VARCHAR(255), status VARCHAR(100) DEFAULT 'En préparation',
            escrow_status VARCHAR(50) DEFAULT 'payée_en_attente',
            escrow_auto_release_date TIMESTAMPTZ,
            subtotal NUMERIC(12,2) DEFAULT 0, tax NUMERIC(12,2) DEFAULT 0,
            shipping_cost NUMERIC(12,2) DEFAULT 0, shipping_name VARCHAR(255),
            shipping_address TEXT, payment_method VARCHAR(100),
            total NUMERIC(12,2) DEFAULT 0, commission_amount NUMERIC(12,2) DEFAULT 0,
            artist_payout NUMERIC(12,2) DEFAULT 0, stripe_session_id VARCHAR(255),
            tracking_number VARCHAR(255), carrier VARCHAR(100),
            shipping_proof_url TEXT, updated_by VARCHAR(255),
            updated_at TIMESTAMPTZ, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id SERIAL PRIMARY KEY, order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
            artwork_id INTEGER, title VARCHAR(255), artist_name VARCHAR(255),
            artist_id VARCHAR(255), price NUMERIC(12,2) DEFAULT 0,
            quantity INTEGER DEFAULT 1, image_url TEXT,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS cart (
            id SERIAL PRIMARY KEY, user_id VARCHAR(255), artwork_id INTEGER,
            quantity INTEGER DEFAULT 1, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, artwork_id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS artworks (
            id SERIAL PRIMARY KEY, title VARCHAR(255), price NUMERIC(12,2),
            category VARCHAR(100), technique VARCHAR(100), technique_custom VARCHAR(100),
            width NUMERIC(8,2), height NUMERIC(8,2), depth NUMERIC(8,2),
            dimensions TEXT, description TEXT, artist_id VARCHAR(255),
            artist_name VARCHAR(255), artist_country VARCHAR(100),
            badge VARCHAR(100) DEFAULT 'Disponible', status VARCHAR(50) DEFAULT 'publiée',
            image_url TEXT, photos TEXT, weight_g INTEGER DEFAULT 0,
            is_sold BOOLEAN DEFAULT FALSE, sold_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_timeline (
            id SERIAL PRIMARY KEY, order_id INTEGER, status VARCHAR(100),
            note TEXT, updated_by_role VARCHAR(50),
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS artist_notifications (
            id SERIAL PRIMARY KEY, artist_id VARCHAR(255), artist_name VARCHAR(255),
            type VARCHAR(100) DEFAULT 'new_order',
            order_id INTEGER, order_number VARCHAR(255), title VARCHAR(255),
            message TEXT, is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    try { $db->exec("ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order'"); } catch (Exception $e) {}

    // ─── MIGRATIONS — colonnes ajoutées après création initiale ──────
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



