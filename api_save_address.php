<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php'; // ta connexion PostgreSQL ($conn)

$data     = json_decode(file_get_contents('php://input'), true);
$userId   = trim($data['userId']   ?? '');
$nom      = trim($data['nom']      ?? '');
$tel      = trim($data['tel']      ?? '');
$quartier = trim($data['quartier'] ?? '');
$ville    = trim($data['ville']    ?? '');
$pays     = trim($data['pays']     ?? "Côte d'Ivoire");
$detail   = trim($data['detail']   ?? '');

if (!$userId || !$nom || !$tel || !$ville) {
    echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants (userId, nom, tel, ville)']);
    exit();
}

// Vérifie si une adresse existe déjà pour cet utilisateur
$check = pg_query_params($conn,
    "SELECT id FROM adresses_livraison WHERE user_id = $1",
    [$userId]
);

if (!$check) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de données : ' . pg_last_error($conn)]);
    exit();
}

if (pg_num_rows($check) > 0) {
    // Mise à jour
    $result = pg_query_params($conn,
        "UPDATE adresses_livraison 
         SET nom=$2, tel=$3, quartier=$4, ville=$5, pays=$6, detail=$7, updated_at=NOW()
         WHERE user_id=$1",
        [$userId, $nom, $tel, $quartier, $ville, $pays, $detail]
    );
} else {
    // Insertion
    $result = pg_query_params($conn,
        "INSERT INTO adresses_livraison (user_id, nom, tel, quartier, ville, pays, detail)
         VALUES ($1, $2, $3, $4, $5, $6, $7)",
        [$userId, $nom, $tel, $quartier, $ville, $pays, $detail]
    );
}

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
}
?>
