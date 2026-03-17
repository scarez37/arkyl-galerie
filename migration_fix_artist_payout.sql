-- ============================================================
-- MIGRATION : Recalculer artist_payout pour les commandes existantes
-- À exécuter une seule fois sur la base de données Render/PostgreSQL
-- ============================================================

-- 1. Corriger artist_payout = 65% du sous-total pour toutes les commandes où c'est 0
UPDATE orders 
SET artist_payout = ROUND(((total - COALESCE(shipping_cost, 0)) * 0.65)::numeric, 2)
WHERE (artist_payout IS NULL OR artist_payout = 0)
  AND total > 0;

-- 2. Vérification : afficher les commandes mises à jour
SELECT 
    order_number,
    total,
    shipping_cost,
    ROUND(((total - COALESCE(shipping_cost, 0)) * 0.65)::numeric, 2) AS artist_payout_calcule,
    artist_payout,
    escrow_status
FROM orders
WHERE escrow_status IN ('livrée_confirmée', 'payée_en_attente', 'expédiée')
ORDER BY created_at DESC
LIMIT 20;
