-- ==================== MIGRATION ARKYL — TABLES COMMANDES ====================
-- À exécuter UNE SEULE FOIS dans ta base PostgreSQL sur Render
-- Via l'onglet "Shell" ou "Query" de ton dashboard Render/Supabase

-- ─────────────────────────────────────────────────────────────────
-- TABLE orders — Une ligne par commande
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id                       SERIAL PRIMARY KEY,
    order_number             VARCHAR(30)    UNIQUE NOT NULL,   -- ex: ARKYL-A3F2B1C0
    user_id                  VARCHAR(255)   NOT NULL,          -- ID Google ou guest_session_id
    status                   VARCHAR(50)    NOT NULL DEFAULT 'En préparation',
    escrow_status            VARCHAR(50)    NOT NULL DEFAULT 'payée_en_attente',
    escrow_auto_release_date TIMESTAMP,                        -- Libération auto à J+21
    subtotal                 INTEGER        NOT NULL DEFAULT 0, -- en FCFA
    tax                      INTEGER        NOT NULL DEFAULT 0, -- TVA 18%
    shipping                 INTEGER        NOT NULL DEFAULT 3000,
    shipping_name            VARCHAR(100)   DEFAULT 'La Poste',
    payment_method           VARCHAR(100)   DEFAULT 'Carte bancaire (Stripe)',
    total                    INTEGER        NOT NULL DEFAULT 0,
    stripe_session_id        VARCHAR(255),
    tracking_number          VARCHAR(100),
    shipping_proof_url       TEXT,
    created_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────────────────────────
-- TABLE order_items — Les articles de chaque commande
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id          SERIAL PRIMARY KEY,
    order_id    INTEGER      NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    artwork_id  INTEGER,
    title       VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255),
    price       INTEGER      NOT NULL,  -- Prix unitaire en FCFA au moment de l'achat
    quantity    INTEGER      NOT NULL DEFAULT 1,
    image_url   TEXT
);

-- ─────────────────────────────────────────────────────────────────
-- INDEX pour accélérer les recherches fréquentes
-- ─────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_orders_user_id      ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status        ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_escrow_status ON orders(escrow_status);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);

-- ─────────────────────────────────────────────────────────────────
-- TABLE cart — Si elle n'existe pas encore
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart (
    id         SERIAL PRIMARY KEY,
    user_id    VARCHAR(255) NOT NULL,
    artwork_id INTEGER      NOT NULL REFERENCES artworks(id) ON DELETE CASCADE,
    quantity   INTEGER      NOT NULL DEFAULT 1,
    added_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, artwork_id)  -- Évite les doublons, permet le ON CONFLICT
);

CREATE INDEX IF NOT EXISTS idx_cart_user_id ON cart(user_id);
