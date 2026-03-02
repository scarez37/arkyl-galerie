<?php
// ==================== TRÉSORERIE ARKYL ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();

    $stmt = $db->prepare("
        SELECT order_number, total, commission_amount, artist_payout, shipping_cost, escrow_status, created_at, artist_id 
        FROM orders 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_commission_arkyl     = 0;
    $total_a_payer_artistes     = 0;
    $liste_paiements_en_attente = [];

    foreach ($commandes as $cmd) {
        // Commission ARKYL : 35% du montant des œuvres (hors livraison)
        $total_commission_arkyl += floatval($cmd['commission_amount']);

        // Reversement artiste : 65% des œuvres + frais de port (non taxés)
        $montant_complet_artiste = floatval($cmd['artist_payout']) + floatval($cmd['shipping_cost'] ?? 0);

        if ($cmd['escrow_status'] === 'fonds_debloques') {
            $total_a_payer_artistes += $montant_complet_artiste;
            $cmd['payout_total_with_shipping'] = $montant_complet_artiste;
            $liste_paiements_en_attente[] = $cmd;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'chiffre_affaire_arkyl'      => $total_commission_arkyl,   // 35% des œuvres
            'argent_a_verser_wave_orange' => $total_a_payer_artistes    // 65% des œuvres + livraison
        ],
        'paiements_urgents' => $liste_paiements_en_attente
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
