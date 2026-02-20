<?php
// ==================== API STRIPE CHECKOUT ====================
// Ce fichier prépare le panier et redirige le client vers le paiement sécurisé Stripe

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. On charge la configuration de ta base de données
require_once __DIR__ . '/db_config.php';
// 2. On charge les outils Stripe téléchargés par Composer (Render va créer ce dossier tout seul)
require_once __DIR__ . '/vendor/autoload.php';

// 🛑 METS TA CLÉ SECRÈTE STRIPE ICI (commence par sk_test_...)
\Stripe\Stripe::setApiKey('sk_test_51T2gpFF55lBdracChUzrVSa166Skh4ob49dtF3j0pa27zcWMk1YLnvt5Wz788K7O0CpIMJPMZcaKDqG241vgQ8tj00EY87nxyZ');

try {
    $db = getDatabase();
    
    // On récupère l'identifiant du client (envoyé par ton index.html)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $user_id = $data['user_id'] ?? '';

    if (empty($user_id)) {
        throw new Exception("Identifiant utilisateur manquant.");
    }

    // 3. On va chercher le contenu du panier du client dans ta base PostgreSQL
    $stmt = $db->prepare("
        SELECT c.quantity, a.title, a.price, a.image_url 
        FROM cart c 
        INNER JOIN artworks a ON c.artwork_id = a.id 
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        throw new Exception("Le panier est vide.");
    }

    // 4. On prépare la facture pour Stripe
    $line_items = [];
    foreach ($cartItems as $item) {
        $line_items[] = [
            'price_data' => [
                'currency' => 'eur', // Devise (Euro pour commencer, on pourra changer)
                'product_data' => [
                    'name' => $item['title'],
                ],
                // Stripe demande le prix en centimes ! (ex: 1500€ devient 150000)
                'unit_amount' => intval($item['price'] * 100), 
            ],
            'quantity' => intval($item['quantity']),
        ];
    }

    // 5. On crée la session de paiement sur les serveurs de Stripe
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        // L'URL où le client atterrit si le paiement réussit
        'success_url' => 'https://arkyl-galerie.onrender.com/succes.html', 
        // L'URL où le client atterrit s'il annule
        'cancel_url' => 'https://arkyl-galerie.onrender.com/index.html',
    ]);

    // 6. On renvoie le lien de paiement à ton index.html pour faire la redirection
    echo json_encode([
        'success' => true, 
        'url' => $checkout_session->url
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
