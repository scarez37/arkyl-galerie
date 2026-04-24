<?php
/**
 * notify_helpers.php
 * Fonctions partagées de notification artiste.
 * Inclus par api_commandes.php ET webhook_stripe.php
 */

if (!function_exists('COALESCE_str')) {
    function COALESCE_str($val) {
        return $val === null ? '' : (string)$val;
    }
}

function notifyArtists($db, $orderId, $orderNumber, $items, $buyerName, $total, $shippingAddress = '', $shippingName = '') {

    // Regrouper les items par artist_id
    $byArtist = [];
    foreach ($items as $item) {
        $aid = COALESCE_str($item['artist_id'] ?? '');
        if (!$aid) continue;
        if (!isset($byArtist[$aid])) $byArtist[$aid] = [];
        $byArtist[$aid][] = $item;
    }

    foreach ($byArtist as $artistId => $artistItems) {

        // 1. Récupérer email + nom de l'artiste depuis la table artists
        $stmt = $db->prepare("SELECT email, artist_name, name FROM artists WHERE id::text = ? LIMIT 1");
        $stmt->execute([$artistId]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        $artistEmail = $artist['email'] ?? null;
        $artistName  = $artist['artist_name'] ?? $artist['name'] ?? 'Artiste';

        // Construire le résumé des œuvres vendues
        $titlesArr = array_map(fn($i) => '"' . ($i['title'] ?? 'Œuvre') . '"', $artistItems);
        $titlesStr = implode(', ', $titlesArr);
        $itemsTotal = array_sum(array_map(fn($i) => ($i['price'] ?? 0) * ($i['quantity'] ?? 1), $artistItems));

        $notifTitle   = "🎉 Nouvelle commande — {$orderNumber}";
        $adresseStr   = $shippingAddress ? " | 📍 Adresse : {$shippingAddress}" : '';
        $notifMessage = "Bonne nouvelle {$artistName} ! {$buyerName} vient de commander {$titlesStr} pour un montant de " . number_format($itemsTotal, 0, ',', ' ') . " FCFA.{$adresseStr}";

        // ── 2. Notification en base de données ──────────────────
        try {
            $db->prepare("
                INSERT INTO artist_notifications (artist_id, type, title, message, order_id, order_number)
                VALUES (?, 'new_order', ?, ?, ?, ?)
            ")->execute([$artistId, $notifTitle, $notifMessage, $orderId, $orderNumber]);
        } catch (Exception $e) {
            error_log("⚠️ Notification BDD artiste {$artistId} : " . $e->getMessage());
        }

        // ── 3. Email à l'artiste ─────────────────────────────────
        if ($artistEmail) {
            try {
                $subject = "=?UTF-8?B?" . base64_encode("🎉 ARKYL — Nouvelle commande {$orderNumber}") . "?=";

                // Tableau HTML des œuvres
                $itemsHtml = '';
                foreach ($artistItems as $item) {
                    $itemsHtml .= '<tr>
                        <td style="padding:8px;border-bottom:1px solid #2a2a2a;">' . htmlspecialchars($item['title'] ?? '') . '</td>
                        <td style="padding:8px;border-bottom:1px solid #2a2a2a;text-align:right;">' . number_format($item['price'] ?? 0, 0, ',', ' ') . ' FCFA</td>
                    </tr>';
                }

                $htmlBody = '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f0f0f;font-family:Montserrat,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f0f;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#1a1a1a;border-radius:16px;overflow:hidden;border:1px solid #9333ea44;">
      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#9333ea,#c026d3);padding:32px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:28px;letter-spacing:2px;">ARKYL</h1>
        <p style="margin:8px 0 0;color:#fff;opacity:.85;font-size:14px;">Galerie d\'Art Contemporain</p>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:32px;">
        <h2 style="color:#9333ea;margin:0 0 8px;">🎉 Nouvelle commande !</h2>
        <p style="color:#ccc;font-size:15px;line-height:1.6;">
          Bonjour <strong style="color:#fff;">' . htmlspecialchars($artistName) . '</strong>,<br>
          Excellente nouvelle ! Une de vos œuvres vient d\'être commandée.
        </p>
        <!-- Détails commande -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#111;border-radius:12px;padding:20px;margin:20px 0;">
          <tr>
            <td style="color:#888;font-size:13px;padding:4px 0;">Numéro de commande</td>
            <td style="color:#9333ea;font-size:13px;text-align:right;font-weight:700;">' . htmlspecialchars($orderNumber) . '</td>
          </tr>
          <tr>
            <td style="color:#888;font-size:13px;padding:4px 0;">Acheteur</td>
            <td style="color:#fff;font-size:13px;text-align:right;">' . htmlspecialchars($buyerName) . '</td>
          </tr>
          ' . ($shippingAddress ? '
          <tr>
            <td style="color:#888;font-size:13px;padding:4px 0;">Mode de livraison</td>
            <td style="color:#fff;font-size:13px;text-align:right;">' . htmlspecialchars($shippingName) . '</td>
          </tr>' : '') . '
        </table>
        ' . ($shippingAddress ? '
        <!-- Adresse de livraison -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#1a0a2e;border:1.5px solid #d4af3766;border-radius:12px;padding:20px;margin:0 0 20px;">
          <tr>
            <td>
              <p style="color:#d4af37;font-size:12px;font-weight:700;letter-spacing:1px;margin:0 0 10px;text-transform:uppercase;">📍 Adresse de livraison du client</p>
              <p style="color:#fff;font-size:14px;font-weight:600;line-height:1.8;margin:0;">' . nl2br(htmlspecialchars(str_replace(', ', "\n", $shippingAddress))) . '</p>
              <p style="color:#aaa;font-size:12px;margin:10px 0 0;">Utilisez cette adresse pour préparer et expédier votre œuvre.</p>
            </td>
          </tr>
        </table>' : '') . '
        <!-- Œuvres -->
        <p style="color:#aaa;font-size:13px;margin:0 0 8px;">Œuvres commandées :</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#111;border-radius:12px;overflow:hidden;">
          <tr style="background:#1f1f1f;">
            <th style="padding:10px 8px;color:#888;font-size:12px;text-align:left;">Titre</th>
            <th style="padding:10px 8px;color:#888;font-size:12px;text-align:right;">Prix</th>
          </tr>
          ' . $itemsHtml . '
          <tr>
            <td style="padding:10px 8px;color:#fff;font-weight:700;">Total artiste</td>
            <td style="padding:10px 8px;color:#9333ea;font-weight:700;text-align:right;">' . number_format($itemsTotal, 0, ',', ' ') . ' FCFA</td>
          </tr>
        </table>
        <p style="color:#aaa;font-size:13px;margin:24px 0 0;line-height:1.6;">
          Connectez-vous à votre espace artiste pour suivre l\'évolution de cette commande.
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#111;padding:20px;text-align:center;border-top:1px solid #222;">
        <p style="color:#555;font-size:12px;margin:0;">© ARKYL — Galerie d\'Art Contemporain</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ARKYL Galerie <noreply@arkyl-galerie.com>\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                mail($artistEmail, $subject, $htmlBody, $headers);
                error_log("✅ Email commande envoyé à {$artistEmail} pour commande {$orderNumber}");

            } catch (Exception $e) {
                error_log("❌ Email artiste {$artistEmail} : " . $e->getMessage());
            }
        }
    }
}
?>
