<?php
require_once __DIR__ . '/auth_helper.php';
// ==================== API COMMANDES — ROUTER ====================

// CORS, erreurs génériques et authentification : voir bootstrap.php / auth_helper.php
function sendCorsHeaders() { arkylSendCors(); } // conservé pour compatibilité

// ── REQUIRES ─────────────────────────────────────────────────────
require_once __DIR__ . '/db_config.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/notify_helpers.php';
require_once __DIR__ . '/stripe_order_helper.php';

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

            // ── Contrôle d'accès SERVEUR : ?admin=1 n'est honoré que pour un vrai administrateur,
            //    et chaque filtre doit correspondre au compte connecté.
            $auth    = arkylRequireAuth();
            $isAdmin = $isAdmin && arkylIsAdmin($auth);
            if (!$isAdmin) {
                if (($user_id !== '' && !arkylOwnsId($auth, $user_id))
                 || ($artist_id !== '' && !arkylOwnsId($auth, $artist_id))
                 || ($artist_name !== '' && !arkylOwnsArtistName($auth, $artist_name))) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
                    return;
                }
            }

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
                                'id',           oi.id,
                                'artwork_id',   oi.artwork_id,
                                'title',        oi.title,
                                'artist',       oi.artist_name,
                                'artist_name',  oi.artist_name,
                                'artist_id',    oi.artist_id,
                                'price',        oi.price,
                                'quantity',     oi.quantity,
                                'image',        oi.image_url,
                                'image_url',    oi.image_url,
                                'artist_email', a.email,
                                'artist_phone', a.phone
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
                LEFT JOIN artists a ON a.id::text = oi.artist_id
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
            $auth = arkylRequireAuth();
            if (!arkylIsAdmin($auth)
                && (($artist_id !== '' && !arkylOwnsId($auth, $artist_id))
                 || ($artist_name !== '' && !arkylOwnsArtistName($auth, $artist_name)))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
                return;
            }

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
            $authTl       = arkylRequireAuth();
            $isAdminTl    = !empty($_GET['admin']) && arkylIsAdmin($authTl);

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
            $isOwner     = arkylOwnsId($authTl, $order['user_id']);
            // On n'accepte que les identités artiste qui appartiennent au compte connecté
            if ($req_artist_id !== '' && !arkylOwnsId($authTl, $req_artist_id)) { $req_artist_id = ''; }
            if ($req_artist_name !== '' && !arkylOwnsArtistName($authTl, $req_artist_name)) { $req_artist_name = ''; }
            $isArtistTl  = false;
            if ($req_artist_id !== '' || $req_artist_name !== '') {
                // Vérifier qu'un item de la commande appartient bien à cet artiste
                $aCheck = $db->prepare("
                    SELECT 1 FROM order_items oi
                    WHERE oi.order_id = :oid
                      AND (oi.artist_id = NULLIF(:aid, '') OR LOWER(oi.artist_name) = LOWER(NULLIF(:aname, '')))
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
            arkylRequireAdmin();
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

        // ── action=get_client_notifications ──────────────────────
        // Notifs pour l'acheteur (expédition, fonds libérés)
        if ($action === 'get_client_notifications') {
            $req_uid = arkylActorId(trim($_GET['user_id'] ?? ''), false);
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS client_notifications (
                        id SERIAL PRIMARY KEY, user_id VARCHAR(255),
                        type VARCHAR(100) DEFAULT 'shipped',
                        order_id INTEGER, order_number VARCHAR(255),
                        title VARCHAR(255), message TEXT,
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $cn = $db->prepare("
                    SELECT * FROM client_notifications
                    WHERE user_id::text = :uid
                    ORDER BY created_at DESC LIMIT 20
                ");
                $cn->execute([':uid' => $req_uid]);
                $notifs = $cn->fetchAll(PDO::FETCH_ASSOC);
                $unread = count(array_filter($notifs, fn($n) => !$n['is_read']));
                echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => $unread]);
            } catch (Exception $cnEx) {
                echo json_encode(['success' => true, 'notifications' => [], 'unread_count' => 0]);
            }
            return;
        }

        // ── action=mark_client_notifications_read ─────────────────
        if ($action === 'mark_client_notifications_read') {
            $req_uid = arkylActorId(trim($_GET['user_id'] ?? ''), false);
            if ($req_uid) {
                try {
                    $db->prepare("UPDATE client_notifications SET is_read = TRUE WHERE user_id::text = :uid")
                       ->execute([':uid' => $req_uid]);
                } catch (Exception $e) {}
            }
            echo json_encode(['success' => true]);
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
        echo json_encode(['success' => false, 'error' => arkylSafeError($e)]);
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
    $auth   = arkylRequireAuth();   // toute action POST exige un jeton valide

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
            if (!arkylIsAdmin($auth)) {
                if (!arkylArtistOwnsOrder($db, $body['order_id'] ?? '', $auth)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Accès non autorisé à cette commande']);
                    return;
                }
                $body['escrow_status'] = null; // un artiste ne peut pas modifier l'état des fonds
            }
            // Le rôle et l'auteur inscrits dans l'historique viennent du jeton, pas du navigateur
            $body['updated_by_role'] = arkylIsAdmin($auth) ? 'admin' : 'artist';
            $body['updated_by']      = $auth['name'] ?? $auth['email'] ?? 'admin';
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
            if (!arkylIsAdmin($auth) && !arkylArtistOwnsOrder($db, $body['order_id'] ?? '', $auth)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé à cette commande']);
                return;
            }
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

            // ✅ FIX BUG 1+2 : notifier le client acheteur que son colis est parti
            if ($row) {
                // Notification BDD pour le client (dans artist_notifications réutilisée)
                // On crée une table client_notifications si besoin
                try {
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS client_notifications (
                            id           SERIAL PRIMARY KEY,
                            user_id      VARCHAR(255),
                            type         VARCHAR(100) DEFAULT 'shipped',
                            order_id     INTEGER,
                            order_number VARCHAR(255),
                            title        VARCHAR(255),
                            message      TEXT,
                            is_read      BOOLEAN DEFAULT FALSE,
                            created_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $clientNotif = $db->prepare("
                        INSERT INTO client_notifications (user_id, type, order_id, order_number, title, message)
                        VALUES (:uid, 'shipped', :oid, :onum, :title, :msg)
                    ");
                    $clientNotif->execute([
                        ':uid'   => $fullOrder['user_id'] ?? '',
                        ':oid'   => $row['id'],
                        ':onum'  => $row['order_number'] ?? '',
                        ':title' => '🚚 Votre commande est en route !',
                        ':msg'   => 'Bonne nouvelle ! Votre commande ' . ($row['order_number'] ?? '') . ' a été expédiée.'
                            . ($tracking ? ' N° de suivi : ' . $tracking : '')
                            . ' Vous pouvez confirmer la réception une fois le colis arrivé.',
                    ]);
                } catch (Exception $clientEx) {
                    error_log('⚠️ Notification client expédition : ' . $clientEx->getMessage());
                }
            }

            echo json_encode(['success' => (bool)$row, 'order' => $fullOrder ?? $row]);
            return;
        }

        // ── confirm_reception (client confirme réception) ─────────
        if ($action === 'confirm_reception') {
            if (!arkylIsAdmin($auth) && !arkylBuyerOwnsOrder($db, $body['order_id'] ?? '', $auth)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Seul l\'acheteur peut confirmer la réception']);
                return;
            }
            $order_id = $body['order_id'] ?? '';
            $user_id  = $body['user_id']  ?? '';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            // ✅ FIX BUG 3 : user_id peut être un Google Sub (string) ou un entier BDD
            // On cast les deux côtés en TEXT pour la comparaison
            $stmt = $db->prepare("
                UPDATE orders SET
                    status = 'Livrée',
                    escrow_status = 'livrée_confirmée',
                    updated_at = CURRENT_TIMESTAMP
                WHERE (id::text = :oid OR order_number = :onum)
                  AND (:uid = '' OR user_id::text = :uid2)
                RETURNING id, order_number, user_id, user_email, user_name
            ");
            $stmt->execute([
                ':oid'  => (string)$order_id,
                ':onum' => (string)$order_id,
                ':uid'  => (string)$user_id,
                ':uid2' => (string)$user_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $db->prepare("
                    INSERT INTO order_timeline (order_id, status, note, updated_by_role)
                    VALUES (:oid, 'Livrée', 'Réception confirmée par le client — fonds libérés', 'client')
                ")->execute([':oid' => $row['id']]);

                // ── Notifier l'artiste que le client a confirmé la réception ──
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
            if (!arkylIsAdmin($auth) && !arkylArtistOwnsOrder($db, $body['order_id'] ?? '', $auth)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé à cette commande']);
                return;
            }
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
            if (!arkylIsAdmin($auth)
                && (($artist_id !== '' && !arkylOwnsId($auth, $artist_id))
                 || ($artist_name !== '' && !arkylOwnsArtistName($auth, $artist_name)))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
                return;
            }
            if ($artist_id === '' && $artist_name === '') {
                echo json_encode(['success' => true]);
                return;
            }

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
            arkylRequireAdmin();
            $order_id          = $body['order_id']          ?? '';
            $payment_method    = $body['payment_method']    ?? 'Virement manuel';
            $payment_reference = $body['payment_reference'] ?? null;
            $payment_note      = $body['payment_note']      ?? null;
            $paid_by           = $body['paid_by']           ?? 'admin';

            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'order_id requis']);
                return;
            }

            // ✅ FIX BUG 1 : récupérer la commande + TOUS les artistes impliqués
            $orderStmt = $db->prepare("
                SELECT o.id, o.order_number, o.user_name, o.user_id,
                       o.artist_payout, o.shipping_cost
                FROM orders o
                WHERE o.id::text = :oid OR o.order_number = :onum
                LIMIT 1
            ");
            $orderStmt->execute([':oid' => (string)$order_id, ':onum' => (string)$order_id]);
            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderData) {
                echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                return;
            }

            $dbOrderId = $orderData['id'];
            $orderNum  = $orderData['order_number'];
            $payout    = floatval($orderData['artist_payout'] ?? 0);
            $shipping  = floatval($orderData['shipping_cost'] ?? 0);
            $totalVerse = $payout + $shipping;

            // Récupérer TOUS les artistes distincts de la commande + leur contact
            $artistsStmt = $db->prepare("
                SELECT DISTINCT oi.artist_id, oi.artist_name,
                       a.email AS artist_email, a.phone AS artist_phone,
                       SUM(oi.price * oi.quantity) AS items_total
                FROM order_items oi
                LEFT JOIN artists a ON a.id::text = oi.artist_id
                WHERE oi.order_id = :oid AND oi.artist_id IS NOT NULL
                GROUP BY oi.artist_id, oi.artist_name, a.email, a.phone
            ");
            $artistsStmt->execute([':oid' => $dbOrderId]);
            $allArtists = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Pour la compatibilité avec le code suivant : prendre le 1er artiste comme référence
            $artistId   = $allArtists[0]['artist_id']   ?? null;
            $artistName = $allArtists[0]['artist_name']  ?? null;

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

            // 3+4. Enregistrer UN paiement PAR artiste + notifier chacun ─────────
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
                // S'assurer que artist_notifications est à jour
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
                $nbArtists   = count($allArtists);

                foreach ($allArtists as $art) {
                    $aId       = $art['artist_id'];
                    $aName     = $art['artist_name'];
                    // Payout proportionnel : si plusieurs artistes, on divise équitablement
                    // (cas rare — en général 1 artiste par commande)
                    $artItems  = floatval($art['items_total'] ?? 0);
                    $artPayout = $nbArtists > 1
                        ? round($payout * ($artItems / max(1, $artItems)) )
                        : $payout;
                    $artShip   = $nbArtists > 1
                        ? round($shipping / $nbArtists)
                        : $shipping;
                    $artTotal  = $artPayout + $artShip;

                    // Enregistrer dans artist_payments
                    $db->prepare("
                        INSERT INTO artist_payments
                            (order_id, order_number, artist_id, artist_name,
                             amount_artwork, amount_shipping, amount_total,
                             payment_method, payment_reference, payment_note, paid_by)
                        VALUES
                            (:oid, :onum, :aid, :aname,
                             :amt_art, :amt_ship, :amt_total,
                             :method, :ref, :note, :by)
                    ")->execute([
                        ':oid' => $dbOrderId, ':onum' => $orderNum,
                        ':aid' => $aId, ':aname' => $aName,
                        ':amt_art' => $artPayout, ':amt_ship' => $artShip, ':amt_total' => $artTotal,
                        ':method' => $payment_method, ':ref' => $payment_reference,
                        ':note' => $payment_note, ':by' => $paid_by,
                    ]);

                    // Notifier l'artiste
                    $db->prepare("
                        INSERT INTO artist_notifications
                            (artist_id, artist_name, type, order_id, order_number, title, message)
                        VALUES (:aid, :aname, 'fonds_liberes', :oid, :onum, :title, :message)
                    ")->execute([
                        ':aid'   => $aId, ':aname' => $aName,
                        ':oid'   => $dbOrderId, ':onum' => $orderNum,
                        ':title' => '💰 Fonds virés — ' . number_format($artTotal, 0, ',', ' ') . ' FCFA',
                        ':message' => "L'admin a confirmé le virement de " . number_format($artTotal, 0, ',', ' ')
                            . " FCFA pour la commande #{$orderNum} via {$methodLabel}{$refLabel}.",
                    ]);
                }
            } catch (Exception $e) {
                error_log("⚠️ artist_payments/notif liberer_fonds: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'order_number' => $orderNum, 'amount' => $totalVerse]);
            return;
        }

        // ── list_transactions (historique des virements admin) ───────
        if ($action === 'list_transactions') {
            arkylRequireAdmin();
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
                echo json_encode(['success' => false, 'transactions' => [], 'error' => arkylSafeError($e)]);
            }
            return;
        }

        // ── interrompre_livraison (admin bloque la procédure) ───────
        if ($action === 'interrompre_livraison') {
            arkylRequireAdmin();
            $order_id   = $body['order_id']  ?? '';
            $raison     = $body['raison']    ?? "Litige en cours — procédure bloquée par l'admin";
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

        // ── create : rattrapage APRÈS paiement (le webhook Stripe est la voie principale) ──
        // Une commande n'est créée que si Stripe confirme que la session est PAYÉE.
        if ($action === 'create') {
            $sid = trim((string) ($body['stripe_session_id'] ?? ''));
            if ($sid === '' || strpos($sid, 'cs_') !== 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Commande impossible sans paiement Stripe confirmé (stripe_session_id requis).']);
                return;
            }
            if (!class_exists('\\Stripe\\Stripe')) {
                throw new Exception('Service de paiement indisponible.');
            }
            arkylConfigureStripe();
            try {
                $cs = \Stripe\Checkout\Session::retrieve($sid);
            } catch (\Throwable $se) {
                error_log('ARKYL create — session Stripe introuvable : ' . $se->getMessage());
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Session de paiement introuvable.']);
                return;
            }
            if (($cs->payment_status ?? '') !== 'paid') {
                http_response_code(402);
                echo json_encode(['success' => false, 'error' => 'Paiement non confirmé.']);
                return;
            }
            $buyer = (string) ($cs->metadata->user_id ?? $cs->client_reference_id ?? '');
            if (!arkylIsAdmin($auth) && !arkylOwnsId($auth, $buyer)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Cette commande ne vous appartient pas.']);
                return;
            }
            $res = arkylCreateOrderFromSession($db, $cs);
            if ($res['status'] === 'skipped') {
                echo json_encode(['success' => false, 'error' => 'Commande non créée (' . ($res['reason'] ?? 'inconnu') . ').']);
                return;
            }
            echo json_encode([
                'success'      => true,
                'order_id'     => $res['order_id'],
                'order_number' => $res['order_number'],
                'info'         => $res['status'] === 'exists' ? 'already_exists' : 'created',
            ]);
            return;
        }

        echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);

    } catch (Exception $e) {
        error_log('❌ api_commandes POST : ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => arkylSafeError($e)]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// POST — Webhook Stripe (signature vérifiée)
// ═══════════════════════════════════════════════════════════════════
function handleStripeWebhook() {
    arkylConfigureStripe();
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

    try {
        $db  = getDatabase();
        $res = arkylCreateOrderFromSession($db, $session);
        error_log('ARKYL webhook (api_commandes) : ' . json_encode($res));
    } catch (Throwable $e) {
        error_log('❌ Webhook ARKYL — erreur : ' . $e->getMessage());
    }
    // Toujours répondre 200 à Stripe (sinon il rejoue l'événement en boucle)
    http_response_code(200);
    echo json_encode(['received' => true]);
}

// ═══════════════════════════════════════════════════════════════════
// Configuration Stripe + contrôles d'appartenance d'une commande
// ═══════════════════════════════════════════════════════════════════
function arkylConfigureStripe() {
    \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ');
}

/** L'utilisateur connecté est-il l'un des artistes de cette commande ? */
function arkylArtistOwnsOrder(PDO $db, $orderRef, array $auth): bool {
    $orderRef = (string) $orderRef;
    if ($orderRef === '') {
        return false;
    }
    $conds  = [];
    $params = [':ref1' => $orderRef, ':ref2' => $orderRef];
    foreach (array_values($auth['ids']) as $i => $id) {
        $conds[] = "oi.artist_id = :a$i";
        $params[":a$i"] = (string) $id;
    }
    foreach (array_values($auth['names']) as $i => $n) {
        $conds[] = "LOWER(oi.artist_name) = LOWER(:n$i)";
        $params[":n$i"] = (string) $n;
    }
    if (!$conds) {
        return false;
    }
    $st = $db->prepare("SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                        WHERE (o.id::text = :ref1 OR o.order_number = :ref2) AND (" . implode(' OR ', $conds) . ") LIMIT 1");
    $st->execute($params);
    return (bool) $st->fetchColumn();
}

/** L'utilisateur connecté est-il l'acheteur de cette commande ? */
function arkylBuyerOwnsOrder(PDO $db, $orderRef, array $auth): bool {
    $orderRef = (string) $orderRef;
    if ($orderRef === '') {
        return false;
    }
    $st = $db->prepare('SELECT user_id FROM orders WHERE id::text = :ref1 OR order_number = :ref2 LIMIT 1');
    $st->execute([':ref1' => $orderRef, ':ref2' => $orderRef]);
    $uid = $st->fetchColumn();
    return $uid !== false && arkylOwnsId($auth, $uid);
}
