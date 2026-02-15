<?php
/**
 * SCRIPT DE DIAGNOSTIC - À exécuter pour vérifier l'API
 * Visitez ce fichier dans votre navigateur pour voir les détails
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic API ARKYL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        h2 { color: #333; border-bottom: 2px solid #d4a574; padding-bottom: 10px; }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #d4a574;
            color: white;
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic API ARKYL</h1>
    
    <?php
    // ====== TEST 1 : Vérifier si le fichier de base de données existe ======
    echo '<div class="box">';
    echo '<h2>📁 Test 1 : Fichier de base de données</h2>';
    
    $dbFile = 'artgallery.db';
    if (file_exists($dbFile)) {
        echo '<p class="success">✅ Fichier trouvé : ' . realpath($dbFile) . '</p>';
        echo '<p>Taille : ' . number_format(filesize($dbFile) / 1024, 2) . ' KB</p>';
        echo '<p>Permissions : ' . substr(sprintf('%o', fileperms($dbFile)), -4) . '</p>';
    } else {
        echo '<p class="error">❌ Fichier NOT FOUND : ' . $dbFile . '</p>';
        echo '<p>Chemin actuel : ' . getcwd() . '</p>';
        echo '<p class="warning">⚠️ Créez la base de données ou vérifiez le chemin !</p>';
    }
    echo '</div>';
    
    // ====== TEST 2 : Connexion à la base de données ======
    echo '<div class="box">';
    echo '<h2>🔌 Test 2 : Connexion SQLite</h2>';
    
    try {
        $db = new SQLite3($dbFile);
        echo '<p class="success">✅ Connexion réussie !</p>';
        
        // ====== TEST 3 : Structure des tables ======
        echo '<h2>📊 Test 3 : Structure des tables</h2>';
        
        // Vérifier la table artworks
        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name='artworks'";
        $result = $db->query($query);
        
        if ($result && $result->fetchArray()) {
            echo '<p class="success">✅ Table "artworks" existe</p>';
            
            // Afficher les colonnes
            $columnsQuery = "PRAGMA table_info(artworks)";
            $columnsResult = $db->query($columnsQuery);
            
            echo '<table>';
            echo '<tr><th>Colonne</th><th>Type</th><th>Null autorisé</th><th>Défaut</th></tr>';
            
            $foundColumns = [];
            while ($col = $columnsResult->fetchArray(SQLITE3_ASSOC)) {
                $foundColumns[] = $col['name'];
                echo '<tr>';
                echo '<td>' . $col['name'] . '</td>';
                echo '<td>' . $col['type'] . '</td>';
                echo '<td>' . ($col['notnull'] ? 'Non' : 'Oui') . '</td>';
                echo '<td>' . ($col['dflt_value'] ?? '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Vérifier les colonnes requises
            $requiredColumns = ['id', 'title', 'price', 'image_url', 'artist_id', 'category', 'description', 'photos', 'dimensions', 'technique', 'technique_custom', 'badge'];
            
            echo '<h3>🔍 Colonnes requises pour l\'API :</h3>';
            echo '<ul>';
            foreach ($requiredColumns as $col) {
                if (in_array($col, $foundColumns)) {
                    echo '<li class="success">✅ ' . $col . '</li>';
                } else {
                    echo '<li class="error">❌ ' . $col . ' - MANQUANTE !</li>';
                }
            }
            echo '</ul>';
            
        } else {
            echo '<p class="error">❌ Table "artworks" NOT FOUND</p>';
        }
        
        // Vérifier la table artists
        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name='artists'";
        $result = $db->query($query);
        
        if ($result && $result->fetchArray()) {
            echo '<p class="success">✅ Table "artists" existe</p>';
            
            // Afficher les colonnes
            $columnsQuery = "PRAGMA table_info(artists)";
            $columnsResult = $db->query($columnsQuery);
            
            echo '<table>';
            echo '<tr><th>Colonne</th><th>Type</th><th>Null autorisé</th><th>Défaut</th></tr>';
            
            while ($col = $columnsResult->fetchArray(SQLITE3_ASSOC)) {
                echo '<tr>';
                echo '<td>' . $col['name'] . '</td>';
                echo '<td>' . $col['type'] . '</td>';
                echo '<td>' . ($col['notnull'] ? 'Non' : 'Oui') . '</td>';
                echo '<td>' . ($col['dflt_value'] ?? '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="error">❌ Table "artists" NOT FOUND</p>';
        }
        
        // ====== TEST 4 : Compter les œuvres ======
        echo '<h2>🎨 Test 4 : Contenu de la base</h2>';
        
        $countQuery = "SELECT COUNT(*) as total FROM artworks";
        $countResult = $db->query($countQuery);
        if ($countResult) {
            $row = $countResult->fetchArray(SQLITE3_ASSOC);
            $total = $row['total'];
            
            if ($total > 0) {
                echo '<p class="success">✅ ' . $total . ' œuvre(s) trouvée(s)</p>';
                
                // Afficher un échantillon
                $sampleQuery = "SELECT id, title, price, artist_id, category FROM artworks LIMIT 5";
                $sampleResult = $db->query($sampleQuery);
                
                echo '<h3>📋 Échantillon d\'œuvres :</h3>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Titre</th><th>Prix</th><th>Artist ID</th><th>Catégorie</th></tr>';
                
                while ($artwork = $sampleResult->fetchArray(SQLITE3_ASSOC)) {
                    echo '<tr>';
                    echo '<td>' . $artwork['id'] . '</td>';
                    echo '<td>' . ($artwork['title'] ?? '<i>NULL</i>') . '</td>';
                    echo '<td>' . ($artwork['price'] ?? '<i>NULL</i>') . ' FCFA</td>';
                    echo '<td>' . ($artwork['artist_id'] ?? '<i>NULL</i>') . '</td>';
                    echo '<td>' . ($artwork['category'] ?? '<i>NULL</i>') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
            } else {
                echo '<p class="warning">⚠️ La table artworks est vide (0 œuvres)</p>';
            }
        }
        
        // ====== TEST 5 : Tester la requête complète de l'API ======
        echo '<h2>🔬 Test 5 : Requête API complète</h2>';
        
        $apiQuery = "SELECT 
                    a.id, 
                    a.title, 
                    a.price, 
                    a.image_url, 
                    a.artist_id,
                    a.category,
                    a.description,
                    a.photos,
                    a.dimensions,
                    a.technique,
                    a.technique_custom,
                    a.badge,
                    a.created_at,
                    COALESCE(u.artist_name, u.name, 'Artiste ARKYL') as artist_name,
                    u.country as artist_country,
                    u.profile_image as artist_avatar
                FROM artworks a 
                LEFT JOIN artists u ON a.artist_id = u.id 
                ORDER BY a.id DESC 
                LIMIT 1";
        
        $apiResult = $db->query($apiQuery);
        
        if ($apiResult) {
            $artwork = $apiResult->fetchArray(SQLITE3_ASSOC);
            
            if ($artwork) {
                echo '<p class="success">✅ Requête API réussie !</p>';
                echo '<h3>📦 Exemple de réponse :</h3>';
                echo '<pre>' . json_encode($artwork, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
            } else {
                echo '<p class="warning">⚠️ Aucune œuvre à afficher</p>';
            }
        } else {
            echo '<p class="error">❌ Erreur requête : ' . $db->lastErrorMsg() . '</p>';
        }
        
        $db->close();
        
    } catch (Exception $e) {
        echo '<p class="error">❌ ERREUR : ' . $e->getMessage() . '</p>';
    }
    echo '</div>';
    
    // ====== TEST 6 : Vérifier les permissions PHP ======
    echo '<div class="box">';
    echo '<h2>⚙️ Test 6 : Configuration PHP</h2>';
    echo '<p>Version PHP : <strong>' . phpversion() . '</strong></p>';
    echo '<p>Extensions SQLite : ';
    if (extension_loaded('sqlite3')) {
        echo '<span class="success">✅ sqlite3 activée</span>';
    } else {
        echo '<span class="error">❌ sqlite3 NOT FOUND</span>';
    }
    echo '</p>';
    echo '</div>';
    
    // ====== RÉSUMÉ ======
    echo '<div class="box">';
    echo '<h2>📝 Résumé et recommandations</h2>';
    
    if (file_exists($dbFile) && isset($total) && $total > 0) {
        echo '<p class="success">✅ Votre base de données semble fonctionnelle !</p>';
        echo '<p>👉 Vérifiez que votre fichier <code>api_galerie_publique.php</code> est au même endroit que ce script.</p>';
        echo '<p>👉 Testez l\'API en visitant : <code>api_galerie_publique.php</code></p>';
    } else {
        echo '<p class="error">⚠️ Problèmes détectés - voir les détails ci-dessus</p>';
    }
    echo '</div>';
    ?>
    
</body>
</html>
