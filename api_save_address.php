<?php
// api_save_address.php — enregistre l'adresse de livraison du compte connecté (PostgreSQL)
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

try {
    $data     = arkylJsonBody();
    $userId   = arkylActorId($data['userId'] ?? ($data['user_id'] ?? ''), false);
    $nom      = trim((string) ($data['nom']      ?? ''));
    $tel      = trim((string) ($data['tel']      ?? ''));
    $quartier = trim((string) ($data['quartier'] ?? ''));
    $ville    = trim((string) ($data['ville']    ?? ''));
    $pays     = trim((string) ($data['pays']     ?? "Côte d'Ivoire"));
    $detail   = trim((string) ($data['detail']   ?? ''));

    if ($nom === '' || $tel === '' || $ville === '') {
        arkylJsonError(400, 'Champs obligatoires manquants (nom, tel, ville)');
    }

    $db = getDatabase();
    $db->prepare("
        INSERT INTO adresses_livraison (user_id, nom, tel, quartier, ville, pays, detail)
        VALUES (:u, :nom, :tel, :q, :v, :p, :d)
        ON CONFLICT (user_id) DO UPDATE
           SET nom = EXCLUDED.nom, tel = EXCLUDED.tel, quartier = EXCLUDED.quartier,
               ville = EXCLUDED.ville, pays = EXCLUDED.pays, detail = EXCLUDED.detail, updated_at = NOW()
    ")->execute([':u' => $userId, ':nom' => $nom, ':tel' => $tel, ':q' => $quartier, ':v' => $ville, ':p' => $pays, ':d' => $detail]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
