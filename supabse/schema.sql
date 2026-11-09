-- ====================================================================
-- ARKYL — schéma PostgreSQL complet (Supabase)
--
-- • Remplace create_tables.php, setup_db.php, fix_orders_table.php et
--   creer_table_adresses.php (supprimés : ils étaient accessibles depuis Internet).
-- • IDEMPOTENT : peut être exécuté sur une base vide (nouvelle installation)
--   OU sur une base déjà migrée (il ajoute seulement ce qui manque).
--
-- Comment l'exécuter : Supabase > SQL Editor > New query > coller > Run.
-- ====================================================================

-- ─── 1. ARTISTES ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS artists (
    id            SERIAL PRIMARY KEY,
    google_id     VARCHAR(255),
    name          TEXT,
    artist_name   TEXT,
    email         VARCHAR(255) UNIQUE,
    phone         VARCHAR(50),
    password_hash VARCHAR(255),
    avatar        TEXT,
    avatar_style  VARCHAR(20)  DEFAULT 'slices',
    bio           TEXT,
    specialty     TEXT,
    country       VARCHAR(100) DEFAULT 'Côte d''Ivoire',
    website       VARCHAR(500),
    social        VARCHAR(500),
    role          VARCHAR(50)  DEFAULT 'artist',
    created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS google_id     VARCHAR(255);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS phone         VARCHAR(50);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS avatar        TEXT;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS avatar_style  VARCHAR(20) DEFAULT 'slices';
ALTER TABLE artists ADD COLUMN IF NOT EXISTS bio           TEXT;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS specialty     TEXT;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS website       VARCHAR(500);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS social        VARCHAR(500);
ALTER TABLE artists ADD COLUMN IF NOT EXISTS role          VARCHAR(50) DEFAULT 'artist';

-- Ancienne colonne « password » (create_tables.php) → recopiée dans password_hash
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = 'public' AND table_name = 'artists' AND column_name = 'password') THEN
        UPDATE artists SET password_hash = password
         WHERE (password_hash IS NULL OR password_hash = '') AND password IS NOT NULL AND password <> '';
    END IF;
END $$;

-- Un compte Google = au plus un compte artiste
DO $$
BEGIN
    CREATE UNIQUE INDEX IF NOT EXISTS uq_artists_google_id ON artists (google_id) WHERE google_id IS NOT NULL;
EXCEPTION WHEN unique_violation THEN
    RAISE NOTICE 'Doublons de google_id dans artists : index unique non créé (à nettoyer manuellement).';
END $$;

-- ─── 2. ŒUVRES ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS artworks (
    id               SERIAL PRIMARY KEY,
    artist_id        TEXT,
    artist_name      TEXT,
    artist_email     TEXT,
    artist_country   TEXT,
    title            TEXT NOT NULL,
    category         TEXT,
    price            NUMERIC(12,2) DEFAULT 0,
    image_url        TEXT,
    description      TEXT,
    photos           TEXT DEFAULT '[]',
    dimensions       TEXT,
    width            NUMERIC(10,2),
    height           NUMERIC(10,2),
    depth            NUMERIC(10,2),
    technique        TEXT,
    technique_custom TEXT,
    badge            VARCHAR(100) DEFAULT 'Disponible',
    status           VARCHAR(50)  DEFAULT 'publiée',
    is_sold          BOOLEAN      DEFAULT FALSE,
    sold_at          TIMESTAMPTZ,
    country          TEXT,
    city             TEXT,
    poids            NUMERIC(10,2),
    weight_g         INTEGER DEFAULT 0,
    created_at       TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS artist_email   TEXT;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS artist_country TEXT;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS technique_custom TEXT;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS width          NUMERIC(10,2);
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS height         NUMERIC(10,2);
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS depth          NUMERIC(10,2);
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold        BOOLEAN DEFAULT FALSE;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS sold_at        TIMESTAMPTZ;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS country        TEXT;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS city           TEXT;
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS poids          NUMERIC(10,2);
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS weight_g       INTEGER DEFAULT 0;

-- ─── 3. PANIER ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart (
    id         SERIAL PRIMARY KEY,
    user_id    VARCHAR(255) NOT NULL,
    artwork_id INTEGER NOT NULL REFERENCES artworks(id) ON DELETE CASCADE,
    quantity   INTEGER DEFAULT 1,
    added_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, artwork_id)
);
ALTER TABLE cart ADD COLUMN IF NOT EXISTS added_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE cart ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP;

-- ─── 4. FAVORIS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favorites (
    id         SERIAL PRIMARY KEY,
    user_id    TEXT NOT NULL,
    artwork_id INTEGER NOT NULL REFERENCES artworks(id) ON DELETE CASCADE,
    added_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, artwork_id)
);

-- ─── 5. LIKES ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS likes (
    id         SERIAL PRIMARY KEY,
    user_id    TEXT NOT NULL,
    artwork_id INTEGER NOT NULL REFERENCES artworks(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, artwork_id)
);
ALTER TABLE likes ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP;

-- ─── 6. ABONNEMENTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS followers (
    id         SERIAL PRIMARY KEY,
    user_id    TEXT NOT NULL,
    artist_id  TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, artist_id)
);

-- ─── 7. ACTUALITÉS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS news (
    id       SERIAL PRIMARY KEY,
    icon     TEXT NOT NULL DEFAULT '📢',
    gradient VARCHAR(50) NOT NULL DEFAULT 'gradient-1',
    text     TEXT NOT NULL,
    is_image SMALLINT NOT NULL DEFAULT 0
);

-- ─── 8. POSTS ARTISTES ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS artist_posts (
    id            VARCHAR(64) PRIMARY KEY,
    artist_id     TEXT NOT NULL,
    artist_name   TEXT NOT NULL,
    artist_avatar TEXT DEFAULT '',
    media_url     TEXT NOT NULL,
    media_type    VARCHAR(10) DEFAULT 'image',
    photos        TEXT DEFAULT '[]',
    caption       TEXT DEFAULT '',
    likes         INTEGER DEFAULT 0,
    comments      TEXT DEFAULT '[]',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── 9. COMMANDES ───────────────────────────────────────────────────
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
    shipping_mode            VARCHAR(100),
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
);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_name        VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_email       VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_id        VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS status           VARCHAR(100) DEFAULT 'En préparation';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_status    VARCHAR(50)  DEFAULT 'payée_en_attente';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_auto_release_date TIMESTAMPTZ;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal         NUMERIC(12,2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax              NUMERIC(12,2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_mode    VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_name    VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method   VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS commission_amount NUMERIC(12,2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS artist_payout    NUMERIC(12,2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number  VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS carrier          VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_proof_url TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_by       VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at       TIMESTAMPTZ;

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
);
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS artist_id VARCHAR(255);

CREATE TABLE IF NOT EXISTS order_timeline (
    id              SERIAL PRIMARY KEY,
    order_id        INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    status          VARCHAR(100),
    note            TEXT,
    updated_by_role VARCHAR(50),
    created_at      TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── 10. NOTIFICATIONS ──────────────────────────────────────────────
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
);
ALTER TABLE artist_notifications ADD COLUMN IF NOT EXISTS type VARCHAR(100) DEFAULT 'new_order';

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
);

-- ─── 11. VERSEMENTS AUX ARTISTES ────────────────────────────────────
CREATE TABLE IF NOT EXISTS artist_payouts (
    id                SERIAL PRIMARY KEY,
    order_id          INTEGER,
    order_number      VARCHAR(255),
    artist_id         VARCHAR(255),
    artist_name       VARCHAR(255),
    amount_artwork    NUMERIC(12,2) DEFAULT 0,
    amount_shipping   NUMERIC(12,2) DEFAULT 0,
    amount_total      NUMERIC(12,2) DEFAULT 0,
    payment_method    VARCHAR(255),
    payment_reference VARCHAR(255),
    payment_note      TEXT,
    paid_by           VARCHAR(255),
    created_at        TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS artist_payments (
    id                SERIAL PRIMARY KEY,
    order_id          INTEGER,
    order_number      VARCHAR(255),
    artist_id         VARCHAR(255),
    artist_name       VARCHAR(255),
    amount_artwork    NUMERIC(12,2) DEFAULT 0,
    amount_shipping   NUMERIC(12,2) DEFAULT 0,
    amount_total      NUMERIC(12,2) DEFAULT 0,
    payment_method    VARCHAR(255),
    payment_reference VARCHAR(255),
    payment_note      TEXT,
    paid_by           VARCHAR(255),
    created_at        TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── 12. ADRESSES DE LIVRAISON ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adresses_livraison (
    id         SERIAL PRIMARY KEY,
    user_id    TEXT NOT NULL UNIQUE,
    nom        TEXT NOT NULL,
    tel        TEXT NOT NULL,
    quartier   TEXT,
    ville      TEXT NOT NULL,
    pays       TEXT DEFAULT 'Côte d''Ivoire',
    detail     TEXT,
    updated_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── 13. ANTI FORCE BRUTE (connexions) ──────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id  SERIAL PRIMARY KEY,
    k   VARCHAR(80) NOT NULL,
    at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── INDEX ──────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_artworks_artist_id   ON artworks (artist_id);
CREATE INDEX IF NOT EXISTS idx_artworks_is_sold     ON artworks (is_sold);
CREATE INDEX IF NOT EXISTS idx_cart_user_id         ON cart (user_id);
CREATE INDEX IF NOT EXISTS idx_favorites_user_id    ON favorites (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_user_id       ON orders (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_created_at    ON orders (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items (order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_artist   ON order_items (artist_id);
CREATE INDEX IF NOT EXISTS idx_order_timeline_order ON order_timeline (order_id);
CREATE INDEX IF NOT EXISTS idx_notif_artist_id      ON artist_notifications (artist_id);
CREATE INDEX IF NOT EXISTS idx_cnotif_user_id       ON client_notifications (user_id);
CREATE INDEX IF NOT EXISTS idx_login_attempts_k     ON login_attempts (k, at);

-- ====================================================================
-- SÉCURITÉ SUPABASE — à NE PAS retirer
--
-- Supabase expose automatiquement toute table du schéma « public » via son
-- API REST publique (clé « anon »). ARKYL n'utilise PAS cette API : le site
-- parle à la base uniquement depuis le serveur PHP, avec le rôle « postgres ».
-- On verrouille donc tout :
--   1) Row Level Security activée sur TOUTES les tables, sans aucune règle
--      (= aucun accès via l'API REST ; le rôle postgres du serveur PHP
--      contourne la RLS et continue de fonctionner normalement) ;
--   2) droits retirés aux rôles « anon » et « authenticated » ;
--   3) idem pour les tables créées plus tard.
-- ====================================================================
DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', r.tablename);
    END LOOP;

    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
        REVOKE ALL ON ALL TABLES    IN SCHEMA public FROM anon;
        REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES    FROM anon;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        REVOKE ALL ON ALL TABLES    IN SCHEMA public FROM authenticated;
        REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM authenticated;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES    FROM authenticated;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM authenticated;
    END IF;
END $$;
