-- Créer la table des adresses de livraison
CREATE TABLE IF NOT EXISTS adresses_livraison (
    id         SERIAL PRIMARY KEY,
    user_id    VARCHAR(255) NOT NULL UNIQUE,
    nom        VARCHAR(255),
    tel        VARCHAR(50),
    quartier   VARCHAR(255),
    ville      VARCHAR(255),
    pays       VARCHAR(255) DEFAULT 'Côte d''Ivoire',
    detail     TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Index pour accélérer les recherches par user_id
CREATE INDEX IF NOT EXISTS idx_adresses_user_id ON adresses_livraison(user_id);
