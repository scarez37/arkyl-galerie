<?php
// ==================== TRÉSORERIE ARKYL (ADMIN) ====================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db_config.php';

try {
    $db = getDatabase();

    // On récupère toutes les commandes avec leurs détails financiers
    $stmt = $db->prepare("
        SELECT order_number, total, commission_amount, artist_payout, escrow_status, created_at, artist_id 
        FROM orders 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_commission_arkyl = 0;
    $total_a_payer_artistes = 0;
    $liste_paiements_en_attente = [];

    foreach ($commandes as $cmd) {
        // On additionne tous les gains de la plateforme (les 31%)
        $total_commission_arkyl += floatval($cmd['commission_amount']);

        // Si l'acheteur a cliqué sur "Colis bien reçu", on isole l'argent pour l'artiste
        if ($cmd['escrow_status'] === 'fonds_debloques') {
            $total_a_payer_artistes += floatval($cmd['artist_payout']);
            $liste_paiements_en_attente[] = $cmd;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'chiffre_affaire_arkyl' => $total_commission_arkyl,
            'argent_a_verser_wave_orange' => $total_a_payer_artistes
        ],
        'paiements_urgents' => $liste_paiements_en_attente,
        'toutes_les_commandes' => $commandes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
