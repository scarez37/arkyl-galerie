<?php
/**
 * SCRIPT DE DIAGNOSTIC - ARKYL
 * Testez ce fichier pour vérifier votre configuration
 * 
 * Accédez à : http://localhost/votre-dossier/diagnostic.php
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostic ARKYL</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            max-width: 900px; 
            margin: 40px auto; 
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 30px; 
            margin: 20px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { 
            color: #667eea; 
            border-bottom: 3px solid #764ba2; 
            padding-bottom: 10px;
        }
        h2 { 
            color: #764ba2; 
            margin-top: 30px;
        }
        .success { 
            color: #10b981; 
            font-weight: bold;
        }
        .error { 
            color: #ef4444; 
            font-weight: bold;
        }
        .warning { 
            color: #f59e0b; 
            font-weight: bold;
        }
        .info { 
            background: #f0f9ff; 
            border-left: 4px solid #3b82f6; 
            padding: 15px; 
            margin: 15px 0;
            border-radius: 4px;
        }
        pre { 
            background: #1f2937; 
            color: #10b981; 
            padding: 20px; 
            border-radius: 8px; 
            overflow-x: auto;
            font-size: 13px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb;
        }
        th { 
            background: #f9fafb; 
            font-weight: 600;
            color: #667eea;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 0 5px;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-draft { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
<div class='card'>
<h1>🔍 Diagnostic ARKYL - Galerie Publique</h1>";

// 1. Vérifier la base de données
echo "<h2>1️⃣ Vérification de la base de données</h2>";

$db_path = __DIR__ . '/arkyl_database.db';

if (file_exists($db_path)) {
    echo "<p class='success'>✅ Base de données trouvée : $db_path</p>";
    
    try {
        $db = new PDO("sqlite:$db_path");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p class='success'>✅ Connexion à la base de données réussie</p>";
        
        // Compter les œuvres par statut
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM artworks GROUP BY status");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📊 Statistiques des œuvres</h3>";
        echo "<table>";
        echo "<tr><th>Statut</th><th>Nombre d'œuvres</th></tr>";
        foreach ($stats as $stat) {
            echo "<tr><td>" . ($stat['status'] ?: 'Non défini') . "</td><td><strong>{$stat['count']}</strong></td></tr>";
        }
        echo "</table>";
        
        // Afficher toutes les œuvres
        echo "<h3>🎨 Liste de toutes les œuvres</h3>";
        $stmt = $db->query("SELECT id, title, artist_name, artist_email, category, price, status FROM artworks ORDER BY created_at DESC");
        $all_artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($all_artworks) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Titre</th><th>Artiste</th><th>Email</th><th>Catégorie</th><th>Prix</th><th>Statut</th></tr>";
            foreach ($all_artworks as $art) {
                $badge_class = $art['status'] === 'active' ? 'badge-active' : 'badge-draft';
                echo "<tr>";
                echo "<td>{$art['id']}</td>";
                echo "<td><strong>{$art['title']}</strong></td>";
                echo "<td>{$art['artist_name']}</td>";
                echo "<td><small>{$art['artist_email']}</small></td>";
                echo "<td>{$art['category']}</td>";
                echo "<td>{$art['price']} FCFA</td>";
                echo "<td><span class='badge {$badge_class}'>{$art['status']}</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Aucune œuvre trouvée dans la base de données</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Erreur de connexion : {$e->getMessage()}</p>";
    }
} else {
    echo "<p class='error'>❌ Base de données introuvable : $db_path</p>";
    echo "<div class='info'>💡 Créez d'abord la base de données en utilisant le script d'initialisation.</div>";
}

// 2. Tester l'API
echo "<h2>2️⃣ Test de l'API Galerie Publique</h2>";

$api_file = __DIR__ . '/api_galerie_publique.php';

if (file_exists($api_file)) {
    echo "<p class='success'>✅ Fichier API trouvé</p>";
    
    // Lire le contenu de l'API
    $api_content = file_get_contents($api_file);
    
    // Vérifier s'il y a un filtrage par artiste (PROBLÈME FRÉQUENT)
    if (preg_match('/WHERE.*artist_email\s*=/', $api_content)) {
        echo "<p class='error'>❌ PROBLÈME DÉTECTÉ : Votre API filtre les œuvres par artiste !</p>";
        echo "<div class='info'>";
        echo "<strong>🔧 Solution :</strong><br>";
        echo "Votre fichier <code>api_galerie_publique.php</code> contient un filtre <code>WHERE artist_email = ...</code><br>";
        echo "Pour afficher TOUTES les œuvres, supprimez ce filtre et utilisez seulement :<br>";
        echo "<pre>WHERE status = 'active'</pre>";
        echo "</div>";
    } else {
        echo "<p class='success'>✅ Pas de filtrage par artiste détecté</p>";
    }
    
    // Tester l'appel à l'API
    $api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api_galerie_publique.php';
    echo "<p>🔗 URL de l'API : <a href='$api_url' target='_blank'>$api_url</a></p>";
    
    try {
        $api_response = @file_get_contents($api_url);
        
        if ($api_response) {
            $api_data = json_decode($api_response, true);
            
            if ($api_data && isset($api_data['success']) && $api_data['success']) {
                $count = count($api_data['data'] ?? []);
                echo "<p class='success'>✅ API fonctionne correctement : {$count} œuvres retournées</p>";
                
                if ($count > 0) {
                    echo "<h3>📋 Aperçu des œuvres de l'API</h3>";
                    echo "<pre>" . json_encode($api_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                }
            } else {
                echo "<p class='error'>❌ L'API retourne une erreur</p>";
                echo "<pre>" . htmlspecialchars($api_response) . "</pre>";
            }
        } else {
            echo "<p class='error'>❌ Impossible d'appeler l'API</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du test de l'API : {$e->getMessage()}</p>";
    }
    
} else {
    echo "<p class='error'>❌ Fichier API introuvable : $api_file</p>";
}

// 3. Recommandations
echo "<h2>3️⃣ Recommandations</h2>";

echo "<div class='info'>";
echo "<h3>✅ Pour résoudre le problème :</h3>";
echo "<ol>";
echo "<li>Remplacez votre <code>api_galerie_publique.php</code> par la version corrigée fournie</li>";
echo "<li>Assurez-vous que toutes les œuvres ont le statut 'active' dans la base de données</li>";
echo "<li>Testez en ouvrant directement l'URL de l'API dans votre navigateur</li>";
echo "<li>Videz le cache de votre navigateur (Ctrl+Shift+R ou Cmd+Shift+R)</li>";
echo "<li>Vérifiez la console du navigateur (F12) pour voir les erreurs JavaScript</li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>🔍 Requête SQL correcte :</h3>";
echo "<pre>SELECT * FROM artworks WHERE status = 'active' ORDER BY created_at DESC</pre>";
echo "<p><strong>❌ Ne PAS utiliser :</strong></p>";
echo "<pre>WHERE artist_email = '\$current_user_email'  // MAUVAIS - filtre par artiste</pre>";
echo "</div>";

echo "</div></body></html>";
?>
