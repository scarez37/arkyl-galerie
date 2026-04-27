<?php
// ==================== SETUP BASE DE DONNÉES ARKYL ====================
// ⚠️  Appeler UNE SEULE FOIS après une perte/recréation de BDD sur Render
// URL : https://arkyl-galerie-nvwn.onrender.com/setup_db.php?secret=arkyl2026
// Après utilisation : supprimer ou renommer ce fichier

require_once __DIR__ . '/db_config.php';

header('Content-Type: text/html; charset=utf-8');

// Sécurité minimale — clé secrète
if (($_GET['secret'] ?? '') !== 'arkyl2026') {
    http_response_code(403);
    die('<h2>❌ Accès refusé — clé secrète manquante</h2>');
}

$results = [];

function run($db, $label, $sql) {
    global $results;
    try {
        $db->exec($sql);
        $results[] = "✅ $label";
    } catch (Exception $e) {
        $results[] = "⚠️ $label — " . $e->getMessage();
    }
}

try {
    $db = getDatabase();

    // ── TABLE ARTWORKS ────────────────────────────────────────────
    run($db, 'Table artworks', "
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

    // ── TABLE ORDERS ──────────────────────────────────────────────
    run($db, 'Table orders', "
        CREATE TABLE IF NOT EXISTS orders (
            id                       SERIAL PRIMARY KEY,
            order_number             VARCHAR(255) UNIQUE,
            user_id                  VARCHAR(255),
            user_name                VARCHAR(255),
            user_email               VARCHAR(255),
            artist_id                VARCHAR(255),
            status                   VARCHAR(100) DEFAULT 'En préparation',
            escrow_status            VARCHAR(50)  DEFAULT 'payée_en_attente',
            escrow_auto_release_date TIMESTAMPTZ,
            subtotal                 NUMERIC(12,2) DEFAULT 0,
            tax                      NUMERIC(12,2) DEFAULT 0,
            shipping_cost            NUMERIC(12,2) DEFAULT 0,
            shipping_name            VARCHAR(255),
            shipping_address         TEXT,
            payment_method           VARCHAR(100),
            total                    NUMERIC(12,2) DEFAULT 0,
            commission_amount        NUMERIC(12,2) DEFAULT 0,
            artist_payout            NUMERIC(12,2) DEFAULT 0,
            stripe_session_id        VARCHAR(255),
            tracking_number          VARCHAR(255),
            carrier                  VARCHAR(100),
            shipping_proof_url       TEXT,
            updated_by               VARCHAR(255),
            updated_at               TIMESTAMPTZ,
            created_at               TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ── TABLE ORDER_ITEMS ─────────────────────────────────────────
    run($db, 'Table order_items', "
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

    // ── TABLE CART ────────────────────────────────────────────────
    run($db, 'Table cart', "
        CREATE TABLE IF NOT EXISTS cart (
            id         SERIAL PRIMARY KEY,
            user_id    VARCHAR(255),
            artwork_id INTEGER,
            quantity   INTEGER DEFAULT 1,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, artwork_id)
        )
    ");

    // ── TABLE ORDER_TIMELINE ──────────────────────────────────────
    run($db, 'Table order_timeline', "
        CREATE TABLE IF NOT EXISTS order_timeline (
            id             SERIAL PRIMARY KEY,
            order_id       INTEGER REFERENCES orders(id) ON DELETE CASCADE,
            status         VARCHAR(100),
            note           TEXT,
            updated_by_role VARCHAR(50),
            created_at     TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ── TABLE ARTIST_NOTIFICATIONS ────────────────────────────────
    run($db, 'Table artist_notifications', "
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

    // ── TABLE USERS (optionnelle) ─────────────────────────────────
    run($db, 'Table users', "
        CREATE TABLE IF NOT EXISTS users (
            id         SERIAL PRIMARY KEY,
            google_id  VARCHAR(255) UNIQUE,
            email      VARCHAR(255),
            name       VARCHAR(255),
            picture    TEXT,
            role       VARCHAR(50) DEFAULT 'client',
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ── INDEX pour performances ───────────────────────────────────
    run($db, 'Index orders.user_id',     "CREATE INDEX IF NOT EXISTS idx_orders_user_id     ON orders(user_id)");
    run($db, 'Index orders.order_number',"CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number)");
    run($db, 'Index order_items.order_id',"CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id)");
    run($db, 'Index artworks.artist_id', "CREATE INDEX IF NOT EXISTS idx_artworks_artist_id  ON artworks(artist_id)");
    run($db, 'Index artworks.is_sold',   "CREATE INDEX IF NOT EXISTS idx_artworks_is_sold    ON artworks(is_sold)");
    run($db, 'Index cart.user_id',       "CREATE INDEX IF NOT EXISTS idx_cart_user_id        ON cart(user_id)");
    run($db, 'Index notif.artist_id',    "CREATE INDEX IF NOT EXISTS idx_notif_artist_id     ON artist_notifications(artist_id)");

    // ── Vérification finale ───────────────────────────────────────
    $tables = $db->query("
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public' ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo '<html><head><meta charset="utf-8"><style>
        body{font-family:monospace;padding:30px;background:#1a1a1a;color:#eee;}
        h1{color:#d4af37;} .ok{color:#4caf50;} .warn{color:#ff9800;}
        pre{background:#111;padding:20px;border-radius:8px;line-height:1.8;}
        .tables{background:#0d3a1a;padding:15px;border-radius:8px;margin-top:20px;}
    </style></head><body>';
    echo '<h1>🗄️ ARKYL — Setup Base de Données</h1>';
    echo '<pre>';
    foreach ($results as $r) {
        $class = str_starts_with($r, '✅') ? 'ok' : 'warn';
        echo "<span class='$class'>$r</span>\n";
    }
    echo '</pre>';
    echo '<div class="tables"><strong>📋 Tables présentes :</strong><br><br>';
    foreach ($tables as $t) echo "• $t<br>";
    echo '</div>';
    echo '<p style="margin-top:20px;color:#ff5722;"><strong>⚠️ Supprime ou renomme ce fichier après utilisation !</strong></p>';
    echo '</body></html>';

} catch (Exception $e) {
    echo '<h2 style="color:red;">❌ Erreur connexion BDD : ' . htmlspecialchars($e->getMessage()) . '</h2>';
}
?>
