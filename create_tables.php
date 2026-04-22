<?php
// ==================== CRÉATION DE TOUTES LES TABLES ARKYL (PostgreSQL) ====================
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";
echo "<h1 style='color: #f90;'>🗄️ Initialisation de la base de données ARKYL</h1>";

try {
    $db = getDatabase();
    $errors = [];
    $success = [];

    $tables = [

        // ─────────────────────────────────────────────────
        // 1. ARTISTES
        // ─────────────────────────────────────────────────
        'artists' => "
            CREATE TABLE IF NOT EXISTS artists (
                id            SERIAL PRIMARY KEY,
                name          TEXT            NOT NULL,
                artist_name   TEXT,
                email         VARCHAR(255)    UNIQUE NOT NULL,
                password      TEXT,
                country       VARCHAR(100)    DEFAULT 'Côte d''Ivoire',
                avatar        TEXT,
                avatar_style  VARCHAR(20)     DEFAULT 'slices',
                phone         VARCHAR(50),
                specialty     TEXT,
                bio           TEXT,
                website       VARCHAR(500),
                social        VARCHAR(500),
                created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
            )
        ",

        // ─────────────────────────────────────────────────
        // 2. ŒUVRES
        // ─────────────────────────────────────────────────
        'artworks' => "
            CREATE TABLE IF NOT EXISTS artworks (
                id               SERIAL PRIMARY KEY,
                artist_id        TEXT,
                artist_name      TEXT,
                artist_email     TEXT,
                artist_country   TEXT,
                title            TEXT            NOT NULL,
                category         TEXT,
                price            DECIMAL(12,2)   DEFAULT 0,
                image_url        TEXT,
                description      TEXT,
                photos           TEXT            DEFAULT '[]',
                dimensions       TEXT,
                width            DECIMAL(10,2),
                height           DECIMAL(10,2),
                depth            DECIMAL(10,2),
                technique        TEXT,
                technique_custom TEXT,
                badge            VARCHAR(50)     DEFAULT 'Disponible',
                status           VARCHAR(50)     DEFAULT 'publiée',
                is_sold          BOOLEAN         DEFAULT FALSE,
                country          TEXT,
                city             TEXT,
                poids            DECIMAL(10,2),
                created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
            )
        ",

        // ─────────────────────────────────────────────────
        // 3. PANIER
        // ─────────────────────────────────────────────────
        'cart' => "
            CREATE TABLE IF NOT EXISTS cart (
                id          SERIAL PRIMARY KEY,
                user_id     VARCHAR(255)    NOT NULL,
                artwork_id  INTEGER         NOT NULL,
                quantity    INTEGER         DEFAULT 1,
                added_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, artwork_id),
                FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
            )
        ",

        // ─────────────────────────────────────────────────
        // 4. FAVORIS
        // ─────────────────────────────────────────────────
        'favorites' => "
            CREATE TABLE IF NOT EXISTS favorites (
                id          SERIAL PRIMARY KEY,
                user_id     VARCHAR(255)    NOT NULL,
                artwork_id  INTEGER         NOT NULL,
                added_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, artwork_id),
                FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
            )
        ",

        // ─────────────────────────────────────────────────
        // 5. ACTUALITÉS (NEWS)
        // ─────────────────────────────────────────────────
        'news' => "
            CREATE TABLE IF NOT EXISTS news (
                id          SERIAL PRIMARY KEY,
                icon        TEXT            NOT NULL DEFAULT '📢',
                gradient    VARCHAR(50)     NOT NULL DEFAULT 'gradient-1',
                text        TEXT            NOT NULL,
                is_image    SMALLINT        NOT NULL DEFAULT 0
            )
        ",

        // ─────────────────────────────────────────────────
        // 6. COMMANDES
        // ─────────────────────────────────────────────────
        'orders' => "
            CREATE TABLE IF NOT EXISTS orders (
                id                SERIAL PRIMARY KEY,
                order_number      VARCHAR(50)     UNIQUE,
                user_id           TEXT,
                artist_id         TEXT,
                total             DECIMAL(12,2)   DEFAULT 0,
                commission_amount DECIMAL(12,2)   DEFAULT 0,
                artist_payout     DECIMAL(12,2)   DEFAULT 0,
                shipping_cost     DECIMAL(12,2)   DEFAULT 0,
                shipping_mode     VARCHAR(50),
                escrow_status     VARCHAR(50)     DEFAULT 'en_attente',
                stripe_session_id TEXT,
                created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
            )
        ",

        // ─────────────────────────────────────────────────
        // 7. POSTS ARTISTES
        // ─────────────────────────────────────────────────
        'artist_posts' => "
            CREATE TABLE IF NOT EXISTS artist_posts (
                id              VARCHAR(64)     PRIMARY KEY,
                artist_id       TEXT            NOT NULL,
                artist_name     TEXT            NOT NULL,
                artist_avatar   TEXT            DEFAULT '',
                media_url       TEXT            NOT NULL,
                media_type      VARCHAR(10)     DEFAULT 'image',
                photos          TEXT            DEFAULT '[]',
                caption         TEXT            DEFAULT '',
                likes           INTEGER         DEFAULT 0,
                comments        TEXT            DEFAULT '[]',
                created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
            )
        ",

        // ─────────────────────────────────────────────────
        // 8. ABONNEMENTS (FOLLOWERS)
        // ─────────────────────────────────────────────────
        'followers' => "
            CREATE TABLE IF NOT EXISTS followers (
                id          SERIAL PRIMARY KEY,
                user_id     TEXT            NOT NULL,
                artist_id   TEXT            NOT NULL,
                created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, artist_id)
            )
        ",

        // ─────────────────────────────────────────────────
        // 9. LIKES
        // ─────────────────────────────────────────────────
        'likes' => "
            CREATE TABLE IF NOT EXISTS likes (
                id          SERIAL PRIMARY KEY,
                user_id     TEXT            NOT NULL,
                artwork_id  INTEGER         NOT NULL,
                UNIQUE(user_id, artwork_id),
                FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
            )
        ",

        // ─────────────────────────────────────────────────
        // 10. ADRESSES DE LIVRAISON
        // ─────────────────────────────────────────────────
        'adresses_livraison' => "
            CREATE TABLE IF NOT EXISTS adresses_livraison (
                id          SERIAL PRIMARY KEY,
                user_id     TEXT            NOT NULL UNIQUE,
                nom         TEXT            NOT NULL,
                tel         TEXT            NOT NULL,
                quartier    TEXT,
                ville       TEXT            NOT NULL,
                pays        TEXT            DEFAULT 'Côte d''Ivoire',
                detail      TEXT,
                updated_at  TIMESTAMP,
                created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
            )
        ",
    ];

    // Colonnes supplémentaires à ajouter si elles n'existent pas encore (ALTER TABLE)
    $alterations = [
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS poids            DECIMAL(10,2)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS width            DECIMAL(10,2)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS height           DECIMAL(10,2)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS depth            DECIMAL(10,2)",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS artist_country   TEXT",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS artist_email     TEXT",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS city             TEXT",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS country          TEXT",
        "ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold          BOOLEAN DEFAULT FALSE",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS avatar           TEXT",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS avatar_style     VARCHAR(20) DEFAULT 'slices'",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS phone            VARCHAR(50)",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS specialty        TEXT",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS bio              TEXT",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS website          VARCHAR(500)",
        "ALTER TABLE artists  ADD COLUMN IF NOT EXISTS social           VARCHAR(500)",
        "ALTER TABLE orders   ADD COLUMN IF NOT EXISTS stripe_session_id TEXT",
    ];

    // ── Créer les tables ─────────────────────────────────
    echo "<h2 style='color:#0af;'>📋 Création des tables</h2><ul>";
    foreach ($tables as $tableName => $sql) {
        try {
            $db->exec($sql);
            echo "<li>✅ <strong>$tableName</strong> — créée (ou déjà existante)</li>";
            $success[] = $tableName;
        } catch (Exception $e) {
            echo "<li style='color:#f55;'>❌ <strong>$tableName</strong> — " . htmlspecialchars($e->getMessage()) . "</li>";
            $errors[] = $tableName;
        }
    }
    echo "</ul>";

    // ── Ajouter les colonnes manquantes ───────────────────
    echo "<h2 style='color:#0af;'>🔧 Ajout des colonnes manquantes</h2><ul>";
    foreach ($alterations as $sql) {
        try {
            $db->exec($sql);
            // Extraire le nom de colonne pour l'affichage
            preg_match('/ADD COLUMN IF NOT EXISTS\s+(\w+)/i', $sql, $m);
            $col = $m[1] ?? $sql;
            echo "<li>✅ Colonne <strong>$col</strong> — OK</li>";
        } catch (Exception $e) {
            echo "<li style='color:#f55;'>❌ " . htmlspecialchars($e->getMessage()) . "</li>";
        }
    }
    echo "</ul>";

    // ── Résumé ────────────────────────────────────────────
    if (empty($errors)) {
        echo "<h2 style='color:#0f0;'>🎉 Tout est prêt ! " . count($success) . " tables créées avec succès.</h2>";
        echo "<p style='color:#aaa;'>Tu peux maintenant utiliser toutes les APIs de la galerie ARKYL.</p>";
    } else {
        echo "<h2 style='color:#f90;'>⚠️ " . count($success) . " tables OK — " . count($errors) . " erreur(s).</h2>";
        echo "<p style='color:#aaa;'>Vérifie les erreurs ci-dessus et assure-toi que db_config.php est correct.</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:#f00;'>❌ Erreur de connexion à la base de données</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:#aaa;'>Vérifie que le fichier <strong>db_config.php</strong> est présent et que la variable DATABASE_URL est configurée sur Render.</p>";
}

echo "</body></html>";
?>
