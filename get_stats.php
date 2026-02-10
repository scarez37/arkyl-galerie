<?php
/**
 * get_stats.php - Récupère les VRAIES statistiques (Ventes, Revenus, Vues)
 */
session_start();
require_once 'config_mysql.php';
header('Content-Type: application/json');

$artist_id = $_GET['artist_id'] ?? $_SESSION['user_id'] ?? 0;

if (!$artist_id) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

try {
    $db = getDB(); // Utilise la connexion définie dans config_mysql.php
    $stats = [];

    // 1. Ventes et Revenus (Seulement les ventes terminées)
    // Note: On utilise try/catch au cas où la table sales n'existe pas encore
    try {
        $stmt = $db->prepare("SELECT COUNT(id) as nb, SUM(artist_revenue) as rev 
                              FROM sales WHERE artist_id = ? AND status IN ('completed', 'shipped')");
        $stmt->execute([$artist_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_sales'] = $res['nb'] ?? 0;
        $stats['revenue'] = number_format($res['rev'] ?? 0, 2, '.', ' ');
    } catch (Exception $e) {
        $stats['total_sales'] = 0;
        $stats['revenue'] = "0.00";
    }

    // 2. Vues totales
    try {
        $stmt = $db->prepare("SELECT COUNT(v.id) as vues FROM artwork_views v 
                              JOIN artworks a ON v.artwork_id = a.id WHERE a.user_id = ?");
        $stmt->execute([$artist_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_views'] = $res['vues'] ?? 0;
    } catch (Exception $e) {
        $stats['total_views'] = 0;
    }

    echo json_encode(['success' => true, 'stats' => $stats]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
