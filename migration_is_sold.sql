-- Migration : ajout colonne weight_g (poids en grammes) pour calcul frais de port La Poste
ALTER TABLE artworks ADD COLUMN IF NOT EXISTS weight_g INTEGER DEFAULT 0;

-- Vérification
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'artworks' AND column_name = 'weight_g';
