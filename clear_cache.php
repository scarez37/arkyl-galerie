<?php
// ==================== NETTOYAGE DU CACHE ET DES DONNÉES ARKYL ====================
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html lang='fr'><body style='font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff;'>";
echo "<h1 style='color: #f90;'>🧹 Nettoyage du cache et des données ARKYL</h1>";

// ── Sécurité : token requis dans l'URL (?token=ARKYL_CLEAR_2025) ──────────────
$token = $_GET['token'] ?? '';
if ($token !== 'ARKYL_CLEAR_2025') {
    echo "<h2 style='color:#f00;'>🔒 Accès refusé</h2>";
    echo "<p>Ajoute <code>?token=ARKYL_CLEAR_2025</code> à l'URL pour accéder à cet outil.</p>";
    echo "</body></html>";
    exit;
}

// ── Lecture des paramètres GET pour choisir ce qu'on efface ───────────────────
$mode = $_GET['mode'] ?? 'all'; // all | cache | sessions | orders | artworks | artists | full

$actions = [
    'cache'    => isset($_GET['cache'])    || $mode === 'all' || $mode === 'cache',
    'sessions' => isset($_GET['sessions']) || $mode === 'all' || $mode === 'sessions',
    'cart'     => isset($_GET['cart'])     || $mode === 'all',
    'notifs'   => isset($_GET['notifs'])   || $mode === 'all',
    'orders'   => isset($_GET['orders'])   || $mode === 'orders'  || $mode === 'full',
    'artworks' => isset($_GET['artworks']) || $mode === 'artworks' || $mode === 'full',
    'artists'  => isset($_GET['artists'])  || $mode === 'artists'  || $mode === 'full',
    'news'     => isset($_GET['news'])     || $mode === 'full',
    'posts'    => isset($_GET['posts'])    || $mode === 'full',
    'full'     => $mode === 'full',
];

// ── Interface de sélection si aucun mode précisé ──────────────────────────────
echo "<div style='background:#2a2a2a;padding:20px;border-radius:12px;margin-bottom:30px;'>";
echo "<h2 style='color:#0af;margin-top:0;'>🎛️ Choisir ce qu'on efface</h2>";
echo "<p style='color:#aaa;'>Clique sur un bouton ou ajoute <code>?token=ARKYL_CLEAR_2025&mode=XXX</code> à l'URL</p>";
$url = strtok($_SERVER['REQUEST_URI'], '?');
$modes = [
    'all'      => ['🧹 Cache + Panier + Notifs (SAFE)',       '#0af'],
    'orders'   => ['🧾 Vider les commandes',                  '#f90'],
    'artworks' => ['🎨 Vider les œuvres',                     '#f90'],
    'artists'  => ['👤 Vider les comptes artistes',           '#f55'],
    'full'     => ['💥 TOUT EFFACER (commandes+œuvres+comptes)','#f00'],
];
foreach ($modes as $m => [$label, $color]) {
    $active = $mode === $m ? "border:2px solid {$color};" : '';
    echo "<a href='{$url}?token=ARKYL_CLEAR_2025&mode={$m}' style='display:inline-block;margin:6px;padding:10px 18px;background:#333;color:{$color};border-radius:8px;text-decoration:none;font-weight:bold;{$active}'>{$label}</a>";
}
echo "</div>";

try {
    $db = getDatabase();

    // ════════════════════════════════════════════════════
    // 1. CACHE PHP (fichiers /tmp)
    // ════════════════════════════════════════════════════
    if ($actions['cache']) {
        echo "<h2 style='color:#0af;'>🗂️ Cache fichiers PHP (/tmp)</h2><ul>";
        $tmpFiles = glob(sys_get_temp_dir() . '/arkyl_*') ?: [];
        $tmpFiles = array_merge($tmpFiles, glob(sys_get_temp_dir() . '/sess_*') ?: []);
        if (empty($tmpFiles)) {
            echo "<li style='color:#aaa;'>Aucun fichier cache trouvé.</li>";
        }
        foreach ($tmpFiles as $file) {
            if (is_file($file) && unlink($file)) {
                echo "<li>✅ Supprimé : <code>" . basename($file) . "</code></li>";
            }
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 2. SESSIONS PHP actives
    // ════════════════════════════════════════════════════
    if ($actions['sessions']) {
        echo "<h2 style='color:#0af;'>🔑 Sessions PHP</h2><ul>";
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        session_destroy();
        echo "<li>✅ Session courante détruite.</li>";
        // Supprimer les fichiers de session
        $sessPath = session_save_path() ?: sys_get_temp_dir();
        $sessFiles = glob($sessPath . '/sess_*') ?: [];
        foreach ($sessFiles as $f) {
            if (is_file($f)) { unlink($f); }
        }
        echo "<li>✅ " . count($sessFiles) . " fichier(s) de session supprimé(s) dans <code>{$sessPath}</code>.</li>";
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 3. PANIER (cart)
    // ════════════════════════════════════════════════════
    if ($actions['cart']) {
        echo "<h2 style='color:#0af;'>🛒 Panier</h2><ul>";
        try {
            $count = $db->query("SELECT COUNT(*) FROM cart")->fetchColumn();
            $db->exec("DELETE FROM cart");
            echo "<li>✅ <strong>{$count}</strong> entrée(s) de panier supprimée(s).</li>";
        } catch (Exception $e) {
            echo "<li style='color:#f55;'>❌ " . htmlspecialchars($e->getMessage()) . "</li>";
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 4. NOTIFICATIONS ARTISTE
    // ════════════════════════════════════════════════════
    if ($actions['notifs']) {
        echo "<h2 style='color:#0af;'>🔔 Notifications</h2><ul>";
        $notifTables = ['artist_notifications', 'notifications'];
        foreach ($notifTables as $t) {
            try {
                $count = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
                $db->exec("DELETE FROM {$t}");
                echo "<li>✅ <strong>{$count}</strong> notification(s) supprimée(s) de <code>{$t}</code>.</li>";
            } catch (Exception $e) {
                echo "<li style='color:#aaa;'>⏭️ Table <code>{$t}</code> ignorée (inexistante).</li>";
            }
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 5. COMMANDES
    // ════════════════════════════════════════════════════
    if ($actions['orders']) {
        echo "<h2 style='color:#f90;'>🧾 Commandes</h2><ul>";
        $orderTables = ['order_timeline', 'order_items', 'orders'];
        foreach ($orderTables as $t) {
            try {
                $count = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
                $db->exec("DELETE FROM {$t}");
                echo "<li>✅ <strong>{$count}</strong> enregistrement(s) supprimé(s) de <code>{$t}</code>.</li>";
            } catch (Exception $e) {
                echo "<li style='color:#aaa;'>⏭️ Table <code>{$t}</code> ignorée : " . htmlspecialchars($e->getMessage()) . "</li>";
            }
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 6. ŒUVRES
    // ════════════════════════════════════════════════════
    if ($actions['artworks']) {
        echo "<h2 style='color:#f90;'>🎨 Œuvres</h2><ul>";
        try {
            // Vider les tables dépendantes d'abord
            foreach (['favorites', 'likes', 'cart'] as $dep) {
                try { $db->exec("DELETE FROM {$dep}"); } catch (Exception $e) {}
            }
            $count = $db->query("SELECT COUNT(*) FROM artworks")->fetchColumn();
            $db->exec("DELETE FROM artworks");
            $db->exec("ALTER SEQUENCE artworks_id_seq RESTART WITH 1");
            echo "<li>✅ <strong>{$count}</strong> œuvre(s) supprimée(s). Séquence ID réinitialisée.</li>";
        } catch (Exception $e) {
            echo "<li style='color:#f55;'>❌ " . htmlspecialchars($e->getMessage()) . "</li>";
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 7. COMPTES ARTISTES
    // ════════════════════════════════════════════════════
    if ($actions['artists']) {
        echo "<h2 style='color:#f55;'>👤 Comptes artistes</h2><ul>";
        try {
            $count = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
            $db->exec("DELETE FROM followers");
            $db->exec("DELETE FROM artists");
            $db->exec("ALTER SEQUENCE artists_id_seq RESTART WITH 1");
            echo "<li>✅ <strong>{$count}</strong> compte(s) artiste supprimé(s). Abonnements supprimés.</li>";
        } catch (Exception $e) {
            echo "<li style='color:#f55;'>❌ " . htmlspecialchars($e->getMessage()) . "</li>";
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // 8. ACTUALITÉS & POSTS (mode full uniquement)
    // ════════════════════════════════════════════════════
    if ($actions['news']) {
        echo "<h2 style='color:#f55;'>📰 Actualités & Posts</h2><ul>";
        foreach (['news', 'artist_posts'] as $t) {
            try {
                $count = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
                $db->exec("DELETE FROM {$t}");
                echo "<li>✅ <strong>{$count}</strong> entrée(s) supprimée(s) de <code>{$t}</code>.</li>";
            } catch (Exception $e) {
                echo "<li style='color:#aaa;'>⏭️ <code>{$t}</code> ignorée.</li>";
            }
        }
        echo "</ul>";
    }

    // ════════════════════════════════════════════════════
    // RÉSUMÉ
    // ════════════════════════════════════════════════════
    echo "<div style='margin-top:30px;padding:20px;background:#0a2a0a;border-radius:12px;border:1px solid #0f0;'>";
    echo "<h2 style='color:#0f0;margin-top:0;'>✅ Nettoyage terminé</h2>";
    echo "<p style='color:#aaa;'>Mode utilisé : <strong style='color:#f90;'>{$mode}</strong></p>";
    echo "<p style='color:#f55;font-weight:bold;'>⚠️ Supprime ce fichier du serveur après utilisation !</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h2 style='color:#f00;'>❌ Erreur de connexion</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
