<?php
// ==================== FIX URGENCE — TABLE ORDERS ====================
// Appeler UNE SEULE FOIS : https://ton-domaine.onrender.com/fix_orders_table.php?secret=arkyl2026
// Supprime ce fichier après utilisation.

require_once __DIR__ . '/db_config.php';
header('Content-Type: text/html; charset=utf-8');

if (($_GET['secret'] ?? '') !== 'arkyl2026') {
    http_response_code(403); die('❌ Accès refusé');
}

try {
    $db = getDatabase();
    $log = [];

    // 1. Lire les colonnes existantes
    $cols = $db->query("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'orders' AND table_schema = 'public'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $log[] = "Colonnes actuelles : " . (count($cols) ? implode(', ', $cols) : '⚠️ TABLE VIDE OU ABSENTE');

    // 2. Créer la table si elle n'existe pas du tout
    $db->exec("
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
    $log[] = "✅ Table orders : CREATE IF NOT EXISTS OK";

    // 3. Ajouter les colonnes manquantes une par une
    $needed = [
        'status'                   => "VARCHAR(100) DEFAULT 'En préparation'",
        'escrow_status'            => "VARCHAR(50) DEFAULT 'payée_en_attente'",
        'escrow_auto_release_date' => "TIMESTAMPTZ",
        'subtotal'                 => "NUMERIC(12,2) DEFAULT 0",
        'tax'                      => "NUMERIC(12,2) DEFAULT 0",
        'shipping_cost'            => "NUMERIC(12,2) DEFAULT 0",
        'shipping_name'            => "VARCHAR(255)",
        'shipping_address'         => "TEXT",
        'payment_method'           => "VARCHAR(100)",
        'total'                    => "NUMERIC(12,2) DEFAULT 0",
        'commission_amount'        => "NUMERIC(12,2) DEFAULT 0",
        'artist_payout'            => "NUMERIC(12,2) DEFAULT 0",
        'stripe_session_id'        => "VARCHAR(255)",
        'tracking_number'          => "VARCHAR(255)",
        'carrier'                  => "VARCHAR(100)",
        'shipping_proof_url'       => "TEXT",
        'user_name'                => "VARCHAR(255)",
        'user_email'               => "VARCHAR(255)",
        'artist_id'                => "VARCHAR(255)",
        'updated_by'               => "VARCHAR(255)",
        'updated_at'               => "TIMESTAMPTZ",
    ];

    foreach ($needed as $col => $def) {
        if (!in_array($col, $cols)) {
            try {
                $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS $col $def");
                $log[] = "✅ Colonne ajoutée : $col $def";
            } catch (Exception $e) {
                $log[] = "⚠️ $col : " . $e->getMessage();
            }
        } else {
            $log[] = "→ $col : déjà présente";
        }
    }

    // 4. Même chose pour order_items
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id SERIAL PRIMARY KEY,
            order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
            artwork_id INTEGER, title VARCHAR(255),
            artist_name VARCHAR(255), artist_id VARCHAR(255),
            price NUMERIC(12,2) DEFAULT 0, quantity INTEGER DEFAULT 1,
            image_url TEXT, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255)");
    $log[] = "✅ Table order_items : OK";

    // 5. Créer tables annexes
    $db->exec("
        CREATE TABLE IF NOT EXISTS cart (
            id SERIAL PRIMARY KEY, user_id VARCHAR(255), artwork_id INTEGER,
            quantity INTEGER DEFAULT 1, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, artwork_id)
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
            order_id INTEGER, order_number VARCHAR(255), title VARCHAR(255),
            message TEXT, is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $log[] = "✅ Tables cart, order_timeline, artist_notifications : OK";

    // 6. Vérification finale
    $finalCols = $db->query("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'orders' AND table_schema = 'public'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo '<html><head><meta charset="utf-8"><style>
        body{font-family:monospace;padding:30px;background:#0d0d0d;color:#eee;line-height:1.8;}
        h2{color:#d4af37;} .ok{color:#4caf50;} .warn{color:#ff9800;} .info{color:#888;}
        pre{background:#111;padding:20px;border-radius:8px;}
        .success{background:#0d2a0d;border:1px solid #4caf50;padding:20px;border-radius:8px;margin-top:20px;}
    </style></head><body>';
    echo '<h2>🔧 Fix urgence — Table orders</h2><pre>';
    foreach ($log as $l) {
        $class = str_starts_with($l, '✅') ? 'ok' : (str_starts_with($l, '⚠️') ? 'warn' : 'info');
        echo "<span class='$class'>" . htmlspecialchars($l) . "</span>\n";
    }
    echo '</pre>';
    echo '<div class="success">✅ Colonnes finales dans <strong>orders</strong> (' . count($finalCols) . ') :<br><br>';
    echo implode(' · ', array_map('htmlspecialchars', $finalCols));
    echo '</div>';
    echo '<p style="color:#ff5722;margin-top:20px;">⚠️ <strong>Supprime ce fichier de ton serveur maintenant.</strong></p>';
    echo '</body></html>';

} catch (Exception $e) {
    echo '<h2 style="color:red;">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</h2>';
}
?>
