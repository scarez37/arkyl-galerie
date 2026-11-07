<?php
// ==================== CONNEXION ARTISTE (e-mail + mot de passe) ====================
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    arkylJsonError(405, 'Méthode non autorisée.');
}

try {
    $data     = arkylJsonBody();
    $email    = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        arkylJsonError(400, 'Veuillez remplir tous les champs.');
    }

    $db = getDatabase();
    arkylThrottleCheck($db, $email);

    $artist = arkylPasswordLogin($db, $email, $password);
    if (!$artist) {
        arkylThrottleFail($db, $email);
        // Même message que le compte existe ou non : pas d'énumération des comptes.
        arkylJsonError(401, 'Email ou mot de passe incorrect.');
    }
    arkylThrottleClear($db, $email);

    $claims   = arkylClaimsFromArtist($artist, 'password');
    $artistId = (int) $artist['id'];

    echo json_encode([
        'success'    => true,
        'message'    => 'Connexion réussie !',
        'user_id'    => $artistId,
        'artist_id'  => $artistId,
        'id'         => $artistId,
        'user_name'  => !empty($artist['artist_name']) ? $artist['artist_name'] : $artist['name'],
        'artist_name'=> $artist['artist_name'] ?? $artist['name'],
        'user_email' => $artist['email'],
        'avatar'     => $artist['avatar'] ?? '',
        'country'    => $artist['country'] ?? "Côte d'Ivoire",
        'role'       => $claims['role'],
        'token'      => arkylIssueToken($claims),
        'expires_in' => ARKYL_TOKEN_TTL,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    arkylJsonError(500, arkylSafeError($e));
}
