-- Ajouter la colonne is_sold à la table artworks
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS is_sold BOOLEAN DEFAULT FALSE;

-- Vérification
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'artworks' AND column_name = 'is_sold';
